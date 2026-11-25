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

namespace Coretsia\Platform\Routing\Tests\Contract;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactPath;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testFoundationNoopsWithAllChannelsEnabledPreserveMatchAndNonMatch(): void
    {
        $table = self::table();
        $context = new ContextStore();
        $router = new CompiledRouter(
            $table,
            $context,
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
        self::assertSame(self::expectedMatch(), $router->match('GET', '/items/42', 'private.example')?->toArray());
        self::assertSame('/items/{id}', $context->get(ContextKeys::PATH_TEMPLATE));
        self::assertNull($router->match('GET', '/missing'));
        self::assertFalse($context->has(ContextKeys::PATH_TEMPLATE));
        self::assertNull($router->match('OTHER', '/items/42'));
    }

    public function testEachThrowingPortOperationIsIsolatedFromMatchingAndOtherSignals(): void
    {
        self::assertThrowingPortOperationsAreIsolated(false);
    }

    public function testEachThrowingPortErrorIsIsolatedFromMatchingAndOtherSignals(): void
    {
        self::assertThrowingPortOperationsAreIsolated(true);
    }

    public function testObserverPreservesMatchIdentityAndUnexpectedThrowableEvenWhenPortsFail(): void
    {
        foreach ([false, true] as $throwError) {
            $context = new ContextStore();
            $ports = new RoutingThrowingObservabilityFixture(
                ['outcome', 'path_template', 'increment', 'observe', 'log', 'end'],
                $context,
                $throwError,
            );
            $observer = new RoutingMatchObserver($ports, $ports, $ports, new Stopwatch(), true, true, true);
            $match = self::table()->match('GET', '/items/42');
            self::assertInstanceOf(RouteMatch::class, $match);
            self::assertSame($match, $observer->observe('GET', static fn (): RouteMatch => $match));
            $ports->calls = [];
            $failure = new \RuntimeException('private match failure');
            try {
                $observer->observe('GET', static fn () => throw $failure);
                self::fail('Expected original matching failure.');
            } catch (\RuntimeException $exception) {
                self::assertSame($failure, $exception);
            }
            self::assertSame([['startSpan', 'routing.match', ['method' => 'GET']], ['end']], $ports->calls);
        }
    }

    private static function assertThrowingPortOperationsAreIsolated(bool $throwError): void
    {
        $table = self::table();
        foreach (
            [
                [],
                ['startSpan'],
                ['outcome'],
                ['path_template'],
                ['increment'],
                ['observe'],
                ['log'],
                ['end'],
                ['outcome', 'path_template', 'increment', 'observe', 'log', 'end'],
                ['startSpan', 'increment', 'observe', 'log'],
            ] as $failures
        ) {
            $context = new ContextStore();
            $ports = new RoutingThrowingObservabilityFixture($failures, $context, $throwError);
            $router = new CompiledRouter(
                $table,
                $context,
                new RoutingMatchObserver(
                    $ports,
                    $ports,
                    $ports,
                    new Stopwatch(),
                    true,
                    true,
                    true,
                ),
            );
            foreach (
                [
                    ['GET', '/items/42', true],
                    ['GET', '/missing', false],
                    ['raw-private-method', '/items/42', false],
                ] as [$method, $path, $matched]
            ) {
                $ports->calls = [];
                $match = $router->match($method, $path, 'private.example');
                self::assertSame($matched ? self::expectedMatch() : null, $match?->toArray());
                self::assertSame($matched ? '/items/{id}' : null, $context->get(ContextKeys::PATH_TEMPLATE));
                $outcome = $matched ? 'matched' : 'not_matched';
                $boundedMethod = $method === 'GET' ? 'GET' : 'OTHER';
                $labels = ['method' => $boundedMethod, 'outcome' => $outcome];
                $expected = [['startSpan', 'routing.match', ['method' => $boundedMethod]]];
                $spanStarted = !\in_array('startSpan', $failures, true);
                if ($spanStarted) {
                    $expected[] = ['outcome', $outcome];
                    if ($matched) {
                        $expected[] = ['path_template', '/items/{id}'];
                    }
                }
                $expected[] = ['increment', 'routing.match_total', 1, $labels];
                $expected[] = ['observe', 'routing.match_duration_ms', $labels];
                $expected[] = [
                    'log',
                    'debug',
                    'routing.match.completed',
                    $matched ? $labels + ['path_template' => '/items/{id}'] : $labels,
                    $matched ? '/items/{id}' : null,
                ];
                if ($spanStarted) {
                    $expected[] = ['end'];
                }
                self::assertSame($expected, $ports->calls, \implode(',', $failures));
            }
        }
    }

    /** @return array<string, mixed> */
    private static function expectedMatch(): array
    {
        return [
            'handler' => 'Handler::show',
            'metadata' => ['flag' => true],
            'name' => 'detail',
            'parameters' => ['id' => '42'],
            'pathTemplate' => '/items/{id}',
            'schemaVersion' => 1,
        ];
    }

    private static function table(): CompiledRouteTable
    {
        $schema = self::schema();
        $route = new RouteDefinition(
            'detail',
            ['GET'],
            '/items/{id}',
            'Handler::show',
            ['id' => '[0-9]+'],
            metadata: ['flag' => true],
        );
        $root = \sys_get_temp_dir() . '/coretsia-routing-noop-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($root, 0700));
        try {
            $paths = new RoutesArtifactPath(new RuntimePathContext($root, $root));
            $bytes = $schema->serializeEnvelope($schema->createEnvelope([$route], 'web', 'local'), 'web', 'local');
            self::assertSame(\strlen($bytes), \file_put_contents($paths->artifactPath(), $bytes));

            return new CompiledRouteTable(
                new RoutesArtifactReader(
                    $paths,
                    new StablePhpArrayParser(),
                    $schema,
                    new ModulePlan('web', [], [], [], []),
                    new RuntimeDiagnosticsInput('local', true),
                ),
            );
        } finally {
            if (\is_file($root . '/routes.php')) {
                \unlink($root . '/routes.php');
            }
            \rmdir($root);
        }
    }

    private static function schema(): RoutesArtifactSchema
    {
        $classifier = new SensitiveKeyClassifier();

        return new RoutesArtifactSchema(
            new ReservedRoutesGuard(),
            $classifier,
            new DefaultSensitiveDataRedactor($classifier, new SensitiveValueClassifier(), new StableRedactionHasher()),
            new StablePhpArrayDumper(),
        );
    }
}

final class RoutingThrowingObservabilityFixture extends AbstractLogger implements
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
        // Retain the exact observed label set, but not nondeterministic duration.
        $this->record(['observe', $name, $labels]);
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
