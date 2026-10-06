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

final class KernelOpsUsesHostApplicationRootForTargetBootstrapTest extends TestCase
{
    public function testExactHostApplicationRootIsPassedUnchangedIntoTargetBootstrapInput(): void
    {
        $applicationRoot = '/fixture//application-root/with/../literal-segments';
        $capturedRoot = null;
        $hostInput = new KernelOpsHostInput($applicationRoot);
        $facade = new KernelOpsFacade(
            hostInput: $hostInput,
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
                BootstrapInput $input,
                string $appTarget,
                callable $markPreset,
            ) use (&$capturedRoot): OpsResult {
                $capturedRoot = $input->applicationRoot();
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

        self::assertSame('success', $result->outcome());
        self::assertSame($applicationRoot, $hostInput->applicationRoot());
        self::assertSame($applicationRoot, $capturedRoot);

        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsHostInput.php');
        self::assertIsString($source);

        foreach (
            [
                'realpath(',
                'getcwd(',
                'DIRECTORY_SEPARATOR',
                'str_replace(',
                'is_file(',
                'is_dir(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
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
