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
use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
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

final class KernelOpsObservabilityFailureDoesNotChangeOutcomeTest extends TestCase
{
    public function testTracerFailureDoesNotChangeSuccessfulResult(): void
    {
        foreach (
            [
                new KernelOpsObservabilityFailureThrowingTracer(),
                new KernelOpsObservabilityFailureThrowingSpanTracer(),
            ] as $tracer
        ) {
            $result = self::executeSuccess(
                self::facade(
                    tracer: $tracer,
                    meter: new KernelOpsObservabilityFailureNoopMeter(),
                    logger: new KernelOpsObservabilityFailureNoopLogger(),
                ),
            );

            self::assertSame('success', $result->outcome());
            self::assertSame('config.validate', $result->operation());
        }
    }

    public function testMeterFailureDoesNotChangeSuccessfulResult(): void
    {
        $result = self::executeSuccess(
            self::facade(
                tracer: new KernelOpsObservabilityFailureNoopTracer(),
                meter: new KernelOpsObservabilityFailureThrowingMeter(),
                logger: new KernelOpsObservabilityFailureNoopLogger(),
            ),
        );

        self::assertSame('success', $result->outcome());
        self::assertSame('config.validate', $result->operation());
    }

    public function testLoggerFailureDoesNotReplaceSuccessfulResultOrPrimaryException(): void
    {
        $facade = self::facade(
            tracer: new KernelOpsObservabilityFailureNoopTracer(),
            meter: new KernelOpsObservabilityFailureNoopMeter(),
            logger: new KernelOpsObservabilityFailureThrowingLogger(),
        );

        $result = self::executeSuccess($facade);

        self::assertSame('success', $result->outcome());

        $rawPrimaryFailure = 'raw-primary-operation-failure';

        try {
            self::execute(
                facade: $facade,
                callback: static function () use ($rawPrimaryFailure): OpsResult {
                    throw new \RuntimeException($rawPrimaryFailure);
                },
            );

            self::fail('Expected Kernel Ops operation failure.');
        } catch (KernelOpsFailedException $exception) {
            self::assertSame(
                KernelOpsFailedException::REASON_OPERATION_FAILED,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_KERNEL_OPS_FAILED: operation-failed',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString(
                $rawPrimaryFailure,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                KernelOpsObservabilityFailureThrowingLogger::FAILURE_MESSAGE,
                $exception->getMessage(),
            );
        }
    }

    private static function executeSuccess(KernelOpsFacade $facade): OpsResult
    {
        return self::execute(
            facade: $facade,
            callback: static function (
                object $_input,
                string $appTarget,
                callable $markPreset,
            ): OpsResult {
                $markPreset('test');

                return new OpsResult(
                    operation: 'config.validate',
                    appTarget: $appTarget,
                    preset: 'test',
                    outcome: 'success',
                    reason: null,
                    data: [
                        'valid' => true,
                    ],
                );
            },
        );
    }

    private static function execute(
        KernelOpsFacade $facade,
        callable $callback,
    ): OpsResult {
        $method = new ReflectionMethod(KernelOpsFacade::class, 'execute');
        $result = $method->invoke(
            $facade,
            'config.validate',
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
    ): KernelOpsFacade {
        return new KernelOpsFacade(
            hostInput: new KernelOpsHostInput(self::absolutePathFixture()),
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
                    return null;
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

final class KernelOpsObservabilityFailureThrowingTracer implements TracerPortInterface
{
    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        throw new \RuntimeException('kernel-ops-test-tracer-failure');
    }

    public function inSpan(
        string $name,
        callable $callback,
        array $attributes = [],
    ): mixed {
        throw new \RuntimeException('kernel-ops-test-tracer-failure');
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final class KernelOpsObservabilityFailureThrowingSpanTracer implements TracerPortInterface
{
    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        return new KernelOpsObservabilityFailureThrowingSpan($name);
    }

    public function inSpan(
        string $name,
        callable $callback,
        array $attributes = [],
    ): mixed {
        return $callback($this->startSpan($name, $attributes));
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final class KernelOpsObservabilityFailureThrowingSpan implements SpanInterface
{
    public function __construct(
        private readonly string $spanName,
    ) {
    }

    public function name(): string
    {
        return $this->spanName;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        throw new \RuntimeException('kernel-ops-test-span-failure');
    }

    public function setAttributes(array $attributes): void
    {
        throw new \RuntimeException('kernel-ops-test-span-failure');
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        throw new \RuntimeException('kernel-ops-test-span-failure');
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        throw new \RuntimeException('kernel-ops-test-span-failure');
    }

    public function end(): void
    {
        throw new \RuntimeException('kernel-ops-test-span-failure');
    }
}

final class KernelOpsObservabilityFailureNoopTracer implements TracerPortInterface
{
    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        return new KernelOpsObservabilityFailureNoopSpan($name);
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

final class KernelOpsObservabilityFailureNoopSpan implements SpanInterface
{
    public function __construct(
        private readonly string $spanName,
    ) {
    }

    public function name(): string
    {
        return $this->spanName;
    }

    public function setAttribute(string $key, mixed $value): void
    {
    }

    public function setAttributes(array $attributes): void
    {
    }

    public function addEvent(string $name, array $attributes = []): void
    {
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
    }

    public function end(): void
    {
    }
}

final class KernelOpsObservabilityFailureThrowingMeter implements MeterPortInterface
{
    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        throw new \RuntimeException('kernel-ops-test-meter-failure');
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        throw new \RuntimeException('kernel-ops-test-meter-failure');
    }
}

final class KernelOpsObservabilityFailureNoopMeter implements MeterPortInterface
{
    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
    }
}

final class KernelOpsObservabilityFailureNoopLogger extends AbstractLogger
{
    public function log(
        $level,
        string|\Stringable $message,
        array $context = [],
    ): void {
    }
}

final class KernelOpsObservabilityFailureThrowingLogger extends AbstractLogger
{
    public const string FAILURE_MESSAGE = 'kernel-ops-test-logger-failure';

    public function log(
        $level,
        string|\Stringable $message,
        array $context = [],
    ): void {
        throw new \RuntimeException(self::FAILURE_MESSAGE);
    }
}
