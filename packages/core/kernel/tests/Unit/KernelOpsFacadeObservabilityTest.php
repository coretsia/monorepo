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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsFacadeObservabilityTest extends TestCase
{
    public function testFacadeEmitsExactlyOneCanonicalAggregateSpanAndMetricPair(): void
    {
        $tracer = new KernelOpsFacadeObservabilityRecordingTracer();
        $meter = new KernelOpsFacadeObservabilityRecordingMeter();
        $logger = new KernelOpsFacadeObservabilityRecordingLogger();
        $absolutePath = self::absolutePathFixture();
        $rawValue = 'raw-sensitive-config-value';
        $generationId = 'generation-sensitive-fixture';
        $fingerprint = 'fingerprint-sensitive-fixture';

        $facade = self::facade(
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
            applicationRoot: $absolutePath,
        );

        $result = self::execute(
            facade: $facade,
            operation: 'config.hash',
            callback: static function (
                object $_input,
                string $appTarget,
                callable $markPreset,
            ) use (
                $fingerprint,
                $generationId,
                $rawValue,
            ): OpsResult {
                $markPreset('test');

                return new OpsResult(
                    operation: 'config.hash',
                    appTarget: $appTarget,
                    preset: 'test',
                    outcome: 'success',
                    reason: null,
                    data: [
                        'fingerprint' => $fingerprint,
                        'generation_id' => $generationId,
                        'raw_value' => $rawValue,
                    ],
                );
            },
        );

        self::assertSame('success', $result->outcome());

        self::assertCount(1, $tracer->spans);
        $span = $tracer->spans[0];

        self::assertSame('kernel.operation', $span->name());
        self::assertSame(
            [
                'operation' => 'config.hash',
                'outcome' => 'success',
                'app_target' => 'console',
                'preset' => 'test',
            ],
            $span->attributes,
        );
        self::assertSame(1, $span->endCount);

        self::assertCount(1, $meter->increments);
        self::assertSame(
            [
                'name' => 'kernel.operation_total',
                'delta' => 1,
                'labels' => [
                    'operation' => 'config.hash',
                    'outcome' => 'success',
                ],
            ],
            $meter->increments[0],
        );

        self::assertCount(1, $meter->observations);
        self::assertSame('kernel.operation_duration_ms', $meter->observations[0]['name']);
        self::assertGreaterThanOrEqual(0, $meter->observations[0]['value']);
        self::assertSame(
            [
                'operation' => 'config.hash',
                'outcome' => 'success',
            ],
            $meter->observations[0]['labels'],
        );

        self::assertSame(
            ['operation', 'outcome'],
            \array_keys($meter->increments[0]['labels']),
        );
        self::assertSame(
            ['operation', 'outcome'],
            \array_keys($meter->observations[0]['labels']),
        );

        $observabilitySurface = \var_export(
            [
                'spans' => $tracer->spans,
                'increments' => $meter->increments,
                'observations' => $meter->observations,
                'logs' => $logger->records,
            ],
            true,
        );

        foreach (
            [
                $absolutePath,
                $rawValue,
                $generationId,
                $fingerprint,
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $observabilitySurface);
        }
    }

    /**
     * @param callable(object,string,callable(string):void):OpsResult $callback
     */
    private static function execute(
        KernelOpsFacade $facade,
        string $operation,
        callable $callback,
    ): OpsResult {
        $method = new ReflectionMethod(KernelOpsFacade::class, 'execute');
        $result = $method->invoke(
            $facade,
            $operation,
            new KernelOpsRequest('console'),
            $callback,
        );

        self::assertInstanceOf(OpsResult::class, $result);

        return $result;
    }

    private static function facade(
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        AbstractLogger $logger,
        string $applicationRoot,
    ): KernelOpsFacade {
        return new KernelOpsFacade(
            hostInput: new KernelOpsHostInput($applicationRoot),
            bootstrapConfigResolver: self::uninitialized(BootstrapConfigResolver::class),
            envRepositoryBuilder: self::uninitialized(EnvRepositoryBuilder::class),
            moduleResolutionOrchestrator: self::uninitialized(ModuleResolutionOrchestrator::class),
            configKernel: self::uninitialized(ConfigKernel::class),
            runtimeContainerGraphCompiler: self::uninitialized(RuntimeContainerGraphCompiler::class),
            configFingerprintInputBuilder: self::uninitialized(ConfigFingerprintInputBuilder::class),
            fingerprintCalculator: self::uninitialized(FingerprintCalculator::class),
            configSourceLocationBuilder: self::uninitialized(ConfigSourceLocationBuilder::class),
            kernelArtifactOperation: self::uninitialized(KernelArtifactOperation::class),
            kernelConfig: [],
            contextAccessor: new class() implements ContextAccessorInterface {
                public function has(string $key): bool
                {
                    return false;
                }

                public function get(string $key): mixed
                {
                    throw new \LogicException('context-value-not-present');
                }
            },
            correlationIdProvider: new class() implements CorrelationIdProviderInterface {
                public function correlationId(): ?string
                {
                    return null;
                }
            },
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
            stopwatch: new Stopwatch(),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function uninitialized(string $class): object
    {
        return new ReflectionClass($class)->newInstanceWithoutConstructor();
    }

    private static function absolutePathFixture(): string
    {
        return \DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\private\\kernel-ops\\application'
            : '/private/kernel-ops/application';
    }
}

final class KernelOpsFacadeObservabilityRecordingTracer implements TracerPortInterface
{
    /**
     * @var list<KernelOpsFacadeObservabilityRecordingSpan>
     */
    public array $spans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new KernelOpsFacadeObservabilityRecordingSpan(
            spanName: $name,
            attributes: $attributes,
        );

        $this->spans[] = $span;

        return $span;
    }

    public function inSpan(
        string $name,
        callable $callback,
        array $attributes = [],
    ): mixed {
        $span = $this->startSpan($name, $attributes);

        try {
            return $callback($span);
        } finally {
            $span->end();
        }
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final class KernelOpsFacadeObservabilityRecordingSpan implements SpanInterface
{
    public int $endCount = 0;

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(
        private readonly string $spanName,
        public array $attributes,
    ) {
    }

    public function name(): string
    {
        return $this->spanName;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = [
            ...$this->attributes,
            ...$attributes,
        ];
    }

    public function addEvent(string $name, array $attributes = []): void
    {
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
    }

    public function end(): void
    {
        ++$this->endCount;
    }
}

final class KernelOpsFacadeObservabilityRecordingMeter implements MeterPortInterface
{
    /**
     * @var list<array{
     *     name: string,
     *     delta: int,
     *     labels: array<string,string|int|bool>
     * }>
     */
    public array $increments = [];

    /**
     * @var list<array{
     *     name: string,
     *     value: int,
     *     labels: array<string,string|int|bool>
     * }>
     */
    public array $observations = [];

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->increments[] = [
            'name' => $name,
            'delta' => $delta,
            'labels' => $labels,
        ];
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->observations[] = [
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
        ];
    }
}

final class KernelOpsFacadeObservabilityRecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{
     *     level: string,
     *     message: string,
     *     context: array<string,mixed>
     * }>
     */
    public array $records = [];

    public function log(
        $level,
        string|\Stringable $message,
        array $context = [],
    ): void {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
