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
use Coretsia\Contracts\Context\ContextKeys;
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

final class KernelOpsFacadeContextBoundaryTest extends TestCase
{
    public function testCorrelationIdComesOnlyFromProviderAndContextAccessorReadsOnlyUowId(): void
    {
        $context = new KernelOpsFacadeContextBoundaryAccessor(
            present: true,
            value: 'uow-safe-001',
        );
        $correlation = new KernelOpsFacadeContextBoundaryCorrelationProvider(
            value: 'corr-safe-001',
        );
        $tracer = new KernelOpsFacadeContextBoundaryTracer();
        $meter = new KernelOpsFacadeContextBoundaryMeter();
        $logger = new KernelOpsFacadeContextBoundaryLogger();

        $result = self::executeSuccess(
            self::facade(
                context: $context,
                correlation: $correlation,
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
            ),
        );

        self::assertSame('success', $result->outcome());
        self::assertSame(1, $correlation->calls);
        self::assertSame([ContextKeys::UOW_ID], $context->hasKeys);
        self::assertSame([ContextKeys::UOW_ID], $context->getKeys);

        self::assertCount(1, $logger->records);
        self::assertSame(
            'corr-safe-001',
            $logger->records[0]['context']['correlation_id'] ?? null,
        );
        self::assertSame(
            'uow-safe-001',
            $logger->records[0]['context']['uow_id'] ?? null,
        );

        self::assertCount(1, $tracer->spans);

        foreach ($tracer->spans[0]->attributes as $key => $_value) {
            self::assertNotContains($key, ['correlation_id', 'uow_id']);
        }

        foreach (
            [
                ...$meter->increments,
                ...$meter->observations,
            ] as $metric
        ) {
            self::assertArrayNotHasKey('correlation_id', $metric['labels']);
            self::assertArrayNotHasKey('uow_id', $metric['labels']);
        }

        $source = self::facadeSource();

        self::assertStringNotContainsString('ContextKeys::CORRELATION_ID', $source);
        self::assertStringNotContainsString('ContextStore', $source);
        self::assertDoesNotMatchRegularExpression(
            '/\$this->contextAccessor->(?:set|put|remove|delete|clear|replace|push)\s*\(/',
            $source,
        );
    }

    public function testMissingAndUnsafeUowIdsAreOmittedWithoutChangingSuccessfulResult(): void
    {
        $cases = [
            [
                'present' => false,
                'value' => null,
            ],
            [
                'present' => true,
                'value' => null,
            ],
            [
                'present' => true,
                'value' => 123,
            ],
            [
                'present' => true,
                'value' => 'uow with whitespace',
            ],
            [
                'present' => true,
                'value' => "uow\nwith-newline",
            ],
            [
                'present' => true,
                'value' => "uow\x01control",
            ],
        ];

        foreach ($cases as $case) {
            $context = new KernelOpsFacadeContextBoundaryAccessor(
                present: $case['present'],
                value: $case['value'],
            );
            $logger = new KernelOpsFacadeContextBoundaryLogger();

            $result = self::executeSuccess(
                self::facade(
                    context: $context,
                    correlation: new KernelOpsFacadeContextBoundaryCorrelationProvider(null),
                    tracer: new KernelOpsFacadeContextBoundaryTracer(),
                    meter: new KernelOpsFacadeContextBoundaryMeter(),
                    logger: $logger,
                ),
            );

            self::assertSame('success', $result->outcome());
            self::assertCount(1, $logger->records);
            self::assertArrayNotHasKey(
                'uow_id',
                $logger->records[0]['context'],
            );

            self::assertSame([ContextKeys::UOW_ID], $context->hasKeys);

            if ($case['present']) {
                self::assertSame([ContextKeys::UOW_ID], $context->getKeys);
            } else {
                self::assertSame([], $context->getKeys);
            }
        }
    }

    public function testThrowingCorrelationProviderDoesNotAlterResultOrPrimaryFailure(): void
    {
        $correlation = new KernelOpsFacadeContextBoundaryCorrelationProvider(
            value: null,
            failure: new \RuntimeException('raw-correlation-provider-failure'),
        );

        $facade = self::facade(
            context: new KernelOpsFacadeContextBoundaryAccessor(false, null),
            correlation: $correlation,
            tracer: new KernelOpsFacadeContextBoundaryTracer(),
            meter: new KernelOpsFacadeContextBoundaryMeter(),
            logger: new KernelOpsFacadeContextBoundaryLogger(),
        );

        $result = self::executeSuccess($facade);

        self::assertSame('success', $result->outcome());

        self::assertPrimaryFailurePreserved(
            facade: $facade,
            rawFailureMessage: 'raw-primary-operation-failure-correlation-case',
        );
    }

    public function testThrowingContextAccessorHasOrGetDoesNotAlterResultOrPrimaryFailure(): void
    {
        foreach (
            [
                new KernelOpsFacadeContextBoundaryAccessor(
                    present: false,
                    value: null,
                    hasFailure: new \RuntimeException('raw-context-has-failure'),
                ),
                new KernelOpsFacadeContextBoundaryAccessor(
                    present: true,
                    value: 'uow-safe',
                    getFailure: new \RuntimeException('raw-context-get-failure'),
                ),
            ] as $context
        ) {
            $facade = self::facade(
                context: $context,
                correlation: new KernelOpsFacadeContextBoundaryCorrelationProvider(null),
                tracer: new KernelOpsFacadeContextBoundaryTracer(),
                meter: new KernelOpsFacadeContextBoundaryMeter(),
                logger: new KernelOpsFacadeContextBoundaryLogger(),
            );

            $result = self::executeSuccess($facade);

            self::assertSame('success', $result->outcome());

            self::assertPrimaryFailurePreserved(
                facade: $facade,
                rawFailureMessage: 'raw-primary-operation-failure-context-case',
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

    private static function assertPrimaryFailurePreserved(
        KernelOpsFacade $facade,
        string $rawFailureMessage,
    ): void {
        try {
            self::execute(
                facade: $facade,
                callback: static function () use ($rawFailureMessage): OpsResult {
                    throw new \RuntimeException($rawFailureMessage);
                },
            );

            self::fail('Expected Kernel Ops primary operation failure.');
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
                $rawFailureMessage,
                $exception->getMessage(),
            );
        }
    }

    /**
     * @param callable $callback
     */
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
        ContextAccessorInterface $context,
        CorrelationIdProviderInterface $correlation,
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
            contextAccessor: $context,
            correlationIdProvider: $correlation,
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

    private static function facadeSource(): string
    {
        $path = \realpath(__DIR__ . '/../../src/Ops/KernelOpsFacade.php');

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function absolutePathFixture(): string
    {
        return \DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\private\\kernel-ops\\application'
            : '/private/kernel-ops/application';
    }
}

final class KernelOpsFacadeContextBoundaryAccessor implements ContextAccessorInterface
{
    /**
     * @var list<string>
     */
    public array $hasKeys = [];

    /**
     * @var list<string>
     */
    public array $getKeys = [];

    public function __construct(
        private readonly bool $present,
        private readonly mixed $value,
        private readonly ?\Throwable $hasFailure = null,
        private readonly ?\Throwable $getFailure = null,
    ) {
    }

    public function has(string $key): bool
    {
        $this->hasKeys[] = $key;

        if ($this->hasFailure !== null) {
            throw $this->hasFailure;
        }

        return $this->present;
    }

    public function get(string $key): mixed
    {
        $this->getKeys[] = $key;

        if ($this->getFailure !== null) {
            throw $this->getFailure;
        }

        return $this->value;
    }
}

final class KernelOpsFacadeContextBoundaryCorrelationProvider implements CorrelationIdProviderInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly ?string $value,
        private readonly ?\Throwable $failure = null,
    ) {
    }

    public function correlationId(): ?string
    {
        ++$this->calls;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->value;
    }
}

final class KernelOpsFacadeContextBoundaryTracer implements TracerPortInterface
{
    /**
     * @var list<KernelOpsFacadeContextBoundarySpan>
     */
    public array $spans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new KernelOpsFacadeContextBoundarySpan(
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

final class KernelOpsFacadeContextBoundarySpan implements SpanInterface
{
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
    }
}

final class KernelOpsFacadeContextBoundaryMeter implements MeterPortInterface
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

final class KernelOpsFacadeContextBoundaryLogger extends AbstractLogger
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
