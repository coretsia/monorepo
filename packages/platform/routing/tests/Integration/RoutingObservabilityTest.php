<?php

declare(strict_types=1);

/*
 * Coretsia Framework (Monorepo)
 *
 * Project: Coretsia Framework (Monorepo)
 * Authors: Vladyslav Mudrichenko and contributors
 * Copyright (c) 2026 Vladyslav Mudrichenko
 *
 * SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
 * SPDX-License-Identifier: Apache-2.0
 *
 * For contributors list, see git history.
 * See LICENSE and NOTICE in the project root for full license information.
 */

namespace Coretsia\Platform\Routing\Tests\Integration;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Provider\RoutingServiceFactory;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class RoutingObservabilityTest extends TestCase
{
    public function testExactSignalsAndIndependentConfigurationForAllCanonicalAndUnsupportedMethods(): void
    {
        $table = self::table();
        foreach ([false, true] as $tracing) {
            foreach ([false, true] as $metrics) {
                foreach ([false, true] as $logging) {
                    $context = new ContextStore();
                    $ports = new RoutingObservabilityRecordingPorts([], $context, false);
                    $observer = RoutingServiceFactory::matchObserver(
                        $ports,
                        $ports,
                        $ports,
                        new Stopwatch(),
                        new ArrayConfigRepository([
                            'routing' => [
                                'tracing' => ['enabled' => $tracing],
                                'metrics' => ['enabled' => $metrics],
                                'logging' => ['enabled' => $logging],
                            ],
                        ], []),
                    );
                    $router = new CompiledRouter($table, $context, $observer);
                    foreach (self::cases() as [$method, $path, $bounded, $matched]) {
                        $ports->calls = [];
                        $match = $router->match($method, $path, 'private.example');
                        self::assertSame($matched ? self::expectedMatch() : null, $match?->toArray());
                        self::assertSignals($ports, $bounded, $matched, $tracing, $metrics, $logging, []);
                    }
                }
            }
        }
    }

    public function testAllowedMethodsEmitNoSignalsAndPreserveOrdinaryMethodMismatchOutcome(): void
    {
        $context = new ContextStore();
        $ports = new RoutingObservabilityRecordingPorts([], $context, false);
        $router = new CompiledRouter(
            self::table(),
            $context,
            new RoutingMatchObserver($ports, $ports, $ports, new Stopwatch(), true, true, true),
        );
        self::assertSame(self::expectedMatch(), $router->match('GET', '/items/private-value')?->toArray());
        self::assertSignals($ports, 'GET', true, true, true, true, []);
        $calls = $ports->calls;
        foreach (
            [
                ['/items/private-value', ['GET']],
                ['/missing', []],
                ['/health', []],
                ['//items/private-value', []],
            ] as [$path, $methods]
        ) {
            self::assertSame($methods, $router->allowedMethods($path, 'private.example'));
            self::assertSame($calls, $ports->calls);
        }
        $ports->calls = [];
        self::assertNull($router->match('POST', '/items/private-value', 'private.example'));
        self::assertSignals($ports, 'POST', false, true, true, true, []);
        $calls = $ports->calls;
        self::assertSame(['GET'], $router->allowedMethods('/items/private-value', 'private.example'));
        self::assertSame($calls, $ports->calls);
    }

    public function testNoopsAndEachThrowablePreserveMatchesAndIndependentSignalAttempts(): void
    {
        $table = self::table();
        $store = new ContextStore();
        $router = new CompiledRouter(
            $table,
            $store,
            new RoutingMatchObserver(
                new NoopTracer(),
                new NoopMeter(),
                new NoopLogger(),
                new Stopwatch(),
                true,
                true,
                true,
            ),
        );
        foreach (self::cases() as [$method, $path, $bounded, $matched]) {
            self::assertSame(
                $matched ? self::expectedMatch() : null,
                $router->match($method, $path, 'private.example')?->toArray(),
            );
        }
        foreach ([false, true] as $throwError) {
            foreach (
                [
                    ['startSpan'],
                    ['outcome'],
                    ['path_template'],
                    ['increment'],
                    ['observe'],
                    ['log'],
                    ['end'],
                    ['startSpan', 'increment', 'observe', 'log'],
                    ['outcome', 'path_template', 'increment', 'observe', 'log', 'end'],
                ] as $failures
            ) {
                $context = new ContextStore();
                $ports = new RoutingObservabilityRecordingPorts($failures, $context, $throwError);
                $router = new CompiledRouter(
                    $table,
                    $context,
                    new RoutingMatchObserver($ports, $ports, $ports, new Stopwatch(), true, true, true),
                );
                foreach (
                    [['GET', '/items/private-value', true], ['GET', '/missing', false]] as [$method, $path, $matched]
                ) {
                    $ports->calls = [];
                    self::assertSame(
                        $matched ? self::expectedMatch() : null,
                        $router->match($method, $path, 'private.example')?->toArray(),
                    );
                    self::assertSignals($ports, 'GET', $matched, true, true, true, $failures);
                }
            }
        }
    }

    public function testUnexpectedMatchingFailureFinishesStartedSpanWithoutCompletionSignals(): void
    {
        $table = self::table();
        foreach ([false, true] as $throwError) {
            $context = new ContextStore();
            $ports = new RoutingObservabilityRecordingPorts(['end'], $context, $throwError);
            $observer = new RoutingMatchObserver($ports, $ports, $ports, new Stopwatch(), true, true, true);
            $match = $table->match('GET', '/items/private-value');
            self::assertInstanceOf(RouteMatch::class, $match);
            $attempts = 0;
            self::assertSame($match, $observer->observe('GET', static function () use ($match, &$attempts): RouteMatch {
                ++$attempts;
                return $match;
            }));
            self::assertSame(1, $attempts);
            foreach ([new \RuntimeException('private match failure'), new \Error('private match error')] as $failure) {
                $ports->calls = [];
                try {
                    $observer->observe('GET', static fn () => throw $failure);
                    self::fail('Expected the original matching failure.');
                } catch (\Throwable $exception) {
                    self::assertSame($failure, $exception);
                }
                self::assertSame([['startSpan', 'routing.match', ['method' => 'GET']], ['end']], $ports->calls);
            }
        }
    }

    public function testFinalFoundationStopwatchFailureIsolationAndIndependentCounterAreStructuralContracts(): void
    {
        self::assertTrue(new \ReflectionClass(Stopwatch::class)->isFinal());
        $constructor = new \ReflectionMethod(RoutingMatchObserver::class, '__construct');
        $parameter = $constructor->getParameters()[3];
        self::assertSame(Stopwatch::class, (string) $parameter->getType());
        self::assertFalse($parameter->isOptional());
        $start = self::methodCode('startDuration');
        self::assertMatchesRegularExpression(
            '~if\s*\(\s*!\$this->metricsEnabled\s*\)\s*\{\s*return\s+null\s*;\s*}\s*'
            . 'try\s*\{\s*return\s+\$this->stopwatch->start\(\)\s*;\s*}\s*catch\s*\(\\\\Throwable\)\s*\{\s*return\s+null\s*;\s*}~',
            $start,
        );
        self::assertSame(
            4_294_967_295,
            new \ReflectionClass(RoutingMatchObserver::class)->getConstant('MAX_DURATION_MS'),
        );
        $complete = self::methodCode('complete');
        // Counter has its own Throwable boundary before any duration-token condition.
        self::assertMatchesRegularExpression(
            '~if\s*\(\$this->metricsEnabled\)\s*\{\s*\$labels\s*=\s*[^;]+;\s*'
            . 'try\s*\{\s*\$this->meter->increment\(self::METRIC_TOTAL,\s*1,\s*\$labels\)\s*;\s*}'
            . '\s*catch\s*\(\\\\Throwable\)\s*\{\s*}\s*if\s*\(\$startedAt\s*!==\s*null\)\s*\{\s*'
            . 'try\s*\{\s*\$durationMs\s*=\s*\$this->stopwatch->stop\(\$startedAt\)\s*;~',
            $complete,
        );
        self::assertMatchesRegularExpression(
            '~\$this->stopwatch->stop\(\$startedAt\)\s*;\s*if\s*\(\s*\$durationMs\s*>=\s*0\s*'
            . '&&\s*\$durationMs\s*<=\s*self::MAX_DURATION_MS\s*\)\s*\{\s*try\s*\{\s*'
            . '\$this->meter->observe\(self::METRIC_DURATION,\s*\$durationMs,\s*\$labels\)\s*;\s*}'
            . '\s*catch\s*\(\\\\Throwable\)\s*\{\s*}\s*}\s*}\s*catch\s*\(\\\\Throwable\)\s*\{\s*}~',
            $complete,
        );
        $observe = self::methodCode('observe');
        self::assertMatchesRegularExpression('~\$startedAt\s*=\s*\$this->startDuration\(\)\s*;~', $observe);
        self::assertStringContainsString(
            '$this->complete($span, $boundedMethod, $outcome, $match, $startedAt);',
            $observe,
        );
        $source = \file_get_contents(new \ReflectionClass(RoutingMatchObserver::class)->getFileName());
        self::assertIsString($source);
        self::assertSame(1, \substr_count($source, '->stopwatch->start('));
        self::assertSame(1, \substr_count($source, '->stopwatch->stop('));
        self::assertStringNotContainsString('new Stopwatch(', $source);
    }

    public function testCounterAndLoggingSurviveUnavailableDurationMeasurement(): void
    {
        $context = new ContextStore();
        $ports = new RoutingObservabilityRecordingPorts([], $context, false);
        $observer = new RoutingMatchObserver($ports, $ports, $ports, new Stopwatch(), true, true, true);
        $complete = new \ReflectionMethod(RoutingMatchObserver::class, 'complete');
        $labels = ['method' => 'GET', 'outcome' => 'not_matched'];
        // A failed start leaves no duration token; no stopwatch implementation is replaced.
        $complete->invoke($observer, $ports, 'GET', 'not_matched', null, null);
        self::assertSame([
            ['outcome', 'not_matched'],
            ['increment', 'routing.match_total', 1, $labels],
            ['log', 'debug', 'routing.match.completed', $labels, null],
        ], $ports->calls);
    }

    private static function methodCode(string $name): string
    {
        $method = new \ReflectionMethod(RoutingMatchObserver::class, $name);
        $file = $method->getFileName();
        self::assertIsString($file);
        $lines = \file($file);
        self::assertIsArray($lines);
        $body = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );
        $code = '';
        foreach (\token_get_all('<?php ' . $body) as $token) {
            if (\is_array($token)) {
                if (!\in_array($token[0], [\T_OPEN_TAG, \T_COMMENT, \T_DOC_COMMENT], true)) {
                    $code .= $token[1];
                }
            } else {
                $code .= $token;
            }
        }
        return $code;
    }

    /** @return list<array{string, string, string, bool}> */
    private static function cases(): array
    {
        $cases = [['GET', '/items/private-value', 'GET', true], ['GET', '/missing', 'GET', false]];
        foreach (['HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE', 'CONNECT'] as $method) {
            $cases[] = [$method, '/items/private-value', $method, false];
        }
        foreach (['get', 'OTHER', '', 'private-method', "GET\n", \str_repeat('X', 257)] as $method) {
            $cases[] = [$method, '/items/private-value', 'OTHER', false];
        }
        return $cases;
    }

    /** @param list<string> $failures */
    private static function assertSignals(
        RoutingObservabilityRecordingPorts $ports,
        string $method,
        bool $matched,
        bool $tracing,
        bool $metrics,
        bool $logging,
        array $failures,
    ): void {
        $outcome = $matched ? 'matched' : 'not_matched';
        $labels = ['method' => $method, 'outcome' => $outcome];
        $template = $matched ? '/items/{id}' : null;
        $expected = [];
        $spanStarted = $tracing && !\in_array('startSpan', $failures, true);
        if ($tracing) {
            $expected[] = ['startSpan', 'routing.match', ['method' => $method]];
        }
        if ($spanStarted) {
            $expected[] = ['outcome', $outcome];
            if ($matched) {
                $expected[] = ['path_template', $template];
            }
        }
        if ($metrics) {
            $expected[] = ['increment', 'routing.match_total', 1, $labels];
            $duration = $ports->calls[\count($expected)][2] ?? null;
            self::assertIsInt($duration);
            self::assertTrue($duration >= 0 && $duration <= 4_294_967_295);
            $expected[] = ['observe', 'routing.match_duration_ms', $duration, $labels];
        }
        if ($logging) {
            $expected[] = [
                'log',
                'debug',
                'routing.match.completed',
                $matched ? $labels + ['path_template' => $template] : $labels,
                $template,
            ];
        }
        if ($spanStarted) {
            $expected[] = ['end'];
        }
        self::assertSame($expected, $ports->calls);
        $serialized = \json_encode($ports->calls, \JSON_THROW_ON_ERROR);
        foreach (
            [
                'private-value',
                'private.example',
                'private-method',
                'Handler::show',
                'http.request.completed',
                'cli.command.completed',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $serialized);
        }
    }

    /** @return array<string, mixed> */
    private static function expectedMatch(): array
    {
        return [
            'handler' => 'Handler::show',
            'metadata' => [],
            'name' => 'detail',
            'parameters' => ['id' => 'private-value'],
            'pathTemplate' => '/items/{id}',
            'schemaVersion' => 1,
        ];
    }

    private static function table(): CompiledRouteTable
    {
        $root = RoutingIntegrationFixture::root();
        try {
            self::assertTrue(
                RoutingIntegrationFixture::writer($root)->write(RoutingIntegrationFixture::envelope('detail')),
            );
            return new CompiledRouteTable(RoutingIntegrationFixture::reader($root));
        } finally {
            RoutingIntegrationFixture::remove($root);
        }
    }
}

final class RoutingObservabilityRecordingPorts extends AbstractLogger implements
    TracerPortInterface,
    SpanInterface,
    MeterPortInterface
{
    /** @var list<array<mixed>> */
    public array $calls = [];

    /** @param list<string> $failures */
    public function __construct(
        private readonly array $failures,
        private readonly ContextStore $context,
        private readonly bool $throwError,
    ) {
    }

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $this->record(['startSpan', $name, $attributes]);

        return $this;
    }

    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        $this->record(['inSpan']);

        return $callback($this);
    }

    public function currentSpan(): ?SpanInterface
    {
        $this->record(['currentSpan']);

        return null;
    }

    public function name(): string
    {
        $this->record(['name']);

        return 'routing.match';
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->record([$key, $value]);
    }

    public function setAttributes(array $attributes): void
    {
        $this->record(['setAttributes', $attributes]);
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        $this->record(['addEvent', $name, $attributes]);
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        $this->record(['recordException']);
    }

    public function end(): void
    {
        $this->record(['end']);
    }

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->record(['increment', $name, $delta, $labels]);
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->record(['observe', $name, $value, $labels]);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->record(['log', $level, (string) $message, $context, $this->context->get(ContextKeys::PATH_TEMPLATE)]);
    }

    /** @param non-empty-list<mixed> $call */
    private function record(array $call): void
    {
        $this->calls[] = $call;
        if (\in_array($call[0], $this->failures, true)) {
            if ($this->throwError) {
                throw new \Error('private observability failure');
            }
            throw new \RuntimeException('private observability failure');
        }
    }
}
