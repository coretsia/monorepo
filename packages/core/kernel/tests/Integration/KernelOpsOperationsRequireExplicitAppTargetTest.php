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

namespace Coretsia\Kernel\Tests\Integration;

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

final class KernelOpsOperationsRequireExplicitAppTargetTest extends TestCase
{
    public function testNonCanonicalTargetFailsBeforeTargetAndPresetBecomeObservable(): void
    {
        $tracer = new KernelOpsExplicitTargetTracer();
        $meter = new KernelOpsExplicitTargetMeter();
        $logger = new KernelOpsExplicitTargetLogger();
        $facade = self::facade($tracer, $meter, $logger);
        $rawTarget = 'structurally-valid-but-not-canonical';
        $callbackCalled = false;

        $execute = new ReflectionMethod(KernelOpsFacade::class, 'execute');
        $result = $execute->invoke(
            $facade,
            'config.validate',
            new KernelOpsRequest($rawTarget),
            static function () use (&$callbackCalled): OpsResult {
                $callbackCalled = true;
                throw new \LogicException('must-not-run');
            },
        );

        self::assertInstanceOf(OpsResult::class, $result);
        self::assertFalse($callbackCalled);
        self::assertSame('handled_error', $result->outcome());
        self::assertSame('bootstrap-invalid-app-target', $result->reason());
        self::assertNull($result->appTarget());
        self::assertNull($result->preset());
        self::assertSame([], $result->data());

        $surface = \var_export(
            [
                'data' => $result->data(),
                'logs' => $logger->records,
                'spans' => $tracer->spans,
                'metrics' => [$meter->increments, $meter->observations],
            ],
            true,
        );

        self::assertStringNotContainsString($rawTarget, $surface);
        self::assertArrayNotHasKey('app_target', $tracer->spans[0]->attributes);
        self::assertArrayNotHasKey('preset', $tracer->spans[0]->attributes);

        foreach ([...$meter->increments, ...$meter->observations] as $metric) {
            self::assertSame(['operation', 'outcome'], \array_keys($metric['labels']));
        }
    }

    private static function facade(
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        AbstractLogger $logger,
    ): KernelOpsFacade {
        return new KernelOpsFacade(
            hostInput: new KernelOpsHostInput('/fixture/application'),
            bootstrapConfigResolver: self::u(BootstrapConfigResolver::class),
            envRepositoryBuilder: self::u(EnvRepositoryBuilder::class),
            moduleResolutionOrchestrator: self::u(ModuleResolutionOrchestrator::class),
            configKernel: self::u(ConfigKernel::class),
            runtimeContainerGraphCompiler: self::u(RuntimeContainerGraphCompiler::class),
            configFingerprintInputBuilder: self::u(ConfigFingerprintInputBuilder::class),
            fingerprintCalculator: self::u(FingerprintCalculator::class),
            configSourceLocationBuilder: self::u(ConfigSourceLocationBuilder::class),
            kernelArtifactOperation: self::u(KernelArtifactOperation::class),
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
    private static function u(string $class): object
    {
        /** @var T $instance */
        $instance = new ReflectionClass($class)->newInstanceWithoutConstructor();

        return $instance;
    }
}

final class KernelOpsExplicitTargetTracer implements TracerPortInterface
{
    public array $spans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new KernelOpsExplicitTargetSpan($name, $attributes);
        $this->spans[] = $span;

        return $span;
    }

    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        return $callback($this->startSpan($name, $attributes));
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final class KernelOpsExplicitTargetSpan implements SpanInterface
{
    public function __construct(private readonly string $spanName, public array $attributes)
    {
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
        $this->attributes = [...$this->attributes, ...$attributes];
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

final class KernelOpsExplicitTargetMeter implements MeterPortInterface
{
    public array $increments = [];
    public array $observations = [];

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->increments[] = ['name' => $name, 'labels' => $labels];
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->observations[] = ['name' => $name, 'labels' => $labels];
    }
}

final class KernelOpsExplicitTargetLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['message' => (string) $message, 'context' => $context];
    }
}
