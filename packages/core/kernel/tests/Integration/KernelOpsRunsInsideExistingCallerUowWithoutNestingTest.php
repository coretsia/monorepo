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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsRunsInsideExistingCallerUowWithoutNestingTest extends TestCase
{
    public function testExistingCallerUowStateIsNotOwnedOrResetByFacade(): void
    {
        $context = new ContextStore();
        $context->set(ContextKeys::UOW_ID, 'caller-owned-uow-001');

        self::assertSame('caller-owned-uow-001', $context->get(ContextKeys::UOW_ID));

        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsFacade.php');
        self::assertIsString($source);

        foreach (
            [
                'KernelRuntimeInterface',
                'KernelRuntime',
                'ResetOrchestrator',
                'kernel.reset',
                'beginUnitOfWork',
                'beginUow',
                'afterUnitOfWork',
                'afterUow',
                'ContextStore',
                'Coretsia\\Platform\\Cli',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }

        self::assertSame('caller-owned-uow-001', $context->get(ContextKeys::UOW_ID));

        $facade = new KernelOpsFacade(
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
            contextAccessor: $context,
            correlationIdProvider: new class() implements CorrelationIdProviderInterface {
                public function correlationId(): ?string
                {
                    return null;
                }
            },
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            logger: new NoopLogger(),
            stopwatch: new Stopwatch(),
        );

        $execute = new ReflectionMethod(KernelOpsFacade::class, 'execute');
        $result = $execute->invoke(
            $facade,
            'config.validate',
            new KernelOpsRequest('console'),
            static function (
                BootstrapInput $_input,
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
                    data: ['valid' => true],
                );
            },
        );

        self::assertInstanceOf(OpsResult::class, $result);
        self::assertSame('success', $result->outcome());
        self::assertSame('caller-owned-uow-001', $context->get(ContextKeys::UOW_ID));

        $testSource = \file_get_contents(__FILE__);
        self::assertIsString($testSource);
        self::assertStringNotContainsString('Coretsia\\Platform\\Cli\\', $testSource);
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
