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
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Ops\KernelOpsExecutionServices;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Provider\KernelServiceFactory;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

final class KernelOpsTargetOperationsDoNotUseConsoleHostPhaseBKernelConfigTest extends TestCase
{
    public function testFacadeAndExecutionServicesRetainBaselineKernelConfigInsteadOfFinalConsoleConfig(): void
    {
        $baseline = self::baselineConfig();
        $consolePhaseB = $baseline;
        $consolePhaseB['kernel']['boot']['default_preset'] = 'poison-console-only';
        $consolePhaseB['kernel']['modes']['overrides_path'] = 'poison-console-only';
        $consolePhaseB['kernel']['modules']['discovery']['source'] = 'poison-console-only';
        $consolePhaseB['kernel']['fingerprint']['application_ignore_prefixes'] = ['poison-console-only'];

        $logger = new NoopLogger();
        $tracer = new NoopTracer();
        $meter = new NoopMeter();
        $stopwatch = new Stopwatch();

        $execution = KernelServiceFactory::kernelOpsExecutionServices(
            baselineConfig: $baseline,
            baselineProviderClasses: [
                FoundationServiceProvider::class,
                KernelServiceProvider::class,
            ],
            logger: $logger,
            tracer: $tracer,
            meter: $meter,
            stopwatch: $stopwatch,
        );

        self::assertSame($baseline['kernel'], $execution->kernelConfig());
        self::assertNotSame($consolePhaseB['kernel'], $execution->kernelConfig());

        $builder = new ContainerBuilder(config: $consolePhaseB);
        $builder
            ->instance(KernelOpsExecutionServices::class, $execution)
            ->instance(KernelOpsHostInput::class, new KernelOpsHostInput('/fixture/application'))
            ->instance(
                ContextAccessorInterface::class,
                new class() implements ContextAccessorInterface {
                    public function has(string $key): bool
                    {
                        return false;
                    }

                    public function get(string $key): mixed
                    {
                        return null;
                    }
                },
            )
            ->instance(
                CorrelationIdProviderInterface::class,
                new class() implements CorrelationIdProviderInterface {
                    public function correlationId(): ?string
                    {
                        return null;
                    }
                },
            )
            ->instance(LoggerInterface::class, $logger)
            ->instance(TracerPortInterface::class, $tracer)
            ->instance(MeterPortInterface::class, $meter)
            ->instance(Stopwatch::class, $stopwatch);

        $facade = KernelServiceFactory::kernelOpsFacade($builder->build());

        self::assertSame(
            $baseline['kernel'],
            new ReflectionProperty(KernelOpsFacade::class, 'kernelConfig')->getValue($facade),
        );

        self::assertSame(
            $execution->kernelArtifactOperation(),
            new ReflectionProperty(KernelOpsFacade::class, 'kernelArtifactOperation')->getValue($facade),
        );
        self::assertSame(
            $execution->fingerprintCalculator(),
            new ReflectionProperty(KernelOpsFacade::class, 'fingerprintCalculator')->getValue($facade),
        );

        $operationKernelConfig = new ReflectionProperty(
            KernelArtifactOperation::class,
            'kernelConfig',
        )->getValue($execution->kernelArtifactOperation());

        self::assertSame($baseline['kernel'], $operationKernelConfig);
        self::assertNotSame($consolePhaseB['kernel'], $operationKernelConfig);

        $compiler = new ReflectionProperty(
            KernelArtifactOperation::class,
            'artifactCompiler',
        )->getValue($execution->kernelArtifactOperation());

        self::assertSame(
            $execution->fingerprintCalculator(),
            new ReflectionProperty(
                ArtifactCompiler::class,
                'fingerprintCalculator',
            )->getValue($compiler),
        );

        foreach (
            [
                'bootstrapConfigResolver' => $execution->bootstrapConfigResolver(),
                'envRepositoryBuilder' => $execution->envRepositoryBuilder(),
                'moduleResolutionOrchestrator' => $execution->moduleResolutionOrchestrator(),
                'configKernel' => $execution->configKernel(),
                'runtimeContainerGraphCompiler' => $execution->runtimeContainerGraphCompiler(),
                'configFingerprintInputBuilder' => $execution->configFingerprintInputBuilder(),
                'fingerprintCalculator' => $execution->fingerprintCalculator(),
                'configSourceLocationBuilder' => $execution->configSourceLocationBuilder(),
                'kernelArtifactOperation' => $execution->kernelArtifactOperation(),
            ] as $property => $expected
        ) {
            self::assertSame(
                $expected,
                new ReflectionProperty(KernelOpsFacade::class, $property)->getValue($facade),
                $property,
            );
        }

        $source = self::facadeSource();

        $operationSource = self::source('../../src/Artifacts/Operation/KernelArtifactOperation.php');
        $compilerSource = self::source('../../src/Artifacts/Compiler/ArtifactCompiler.php');
        $publicationSetSource = self::source('../../src/Artifacts/Generation/ArtifactPublicationSet.php');

        self::assertStringContainsString(
            'kernelConfig: $this->kernelConfig',
            $operationSource,
        );
        self::assertStringContainsString(
            '$fingerprint = $this->fingerprintCalculator->calculate($fingerprintInput);',
            $compilerSource,
        );
        self::assertStringContainsString(
            '$generationId = $this->fingerprintCalculator->calculate($fingerprintInput);',
            $source,
        );
        self::assertStringContainsString(
            '$this->generationId = ArtifactGenerationId::fromString($moduleManifestFingerprint);',
            $publicationSetSource,
        );

        self::assertStringContainsString('kernelConfig: $this->kernelConfig', $source);
        self::assertStringContainsString('$this->kernelArtifactOperation->compile($input)', $source);
        self::assertStringContainsString("'generation_id' => \$generationId", $source);

        self::assertSame(
            3,
            \substr_count($compilerSource, 'fingerprint: $fingerprint,'),
        );
        self::assertStringContainsString(
            '$publishedGeneration = $this->generationPublisher->publish(',
            $compilerSource,
        );
        self::assertStringContainsString(
            'return self::compileResult($publishedGeneration);',
            $compilerSource,
        );
        self::assertStringContainsString(
            "'generationId' => \$generation->generationId()->value(),",
            $compilerSource,
        );

        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'kernel-ops-hash-compile-generation-parity',
        );

        try {
            $moduleResolution = ArtifactPipelineTestSupport::moduleResolution();

            $compileResult = ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                applicationRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                moduleResolution: $moduleResolution,
            );

            $fingerprint = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
                testCase: $this,
                applicationRoot: $root,
                moduleResolution: $moduleResolution,
            );

            self::assertSame(
                $fingerprint,
                $compileResult['generationId'] ?? null,
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    private static function baselineConfig(): array
    {
        $foundation = require \dirname(__DIR__, 3) . '/foundation/config/foundation.php';
        $kernel = require \dirname(__DIR__, 2) . '/config/kernel.php';

        self::assertIsArray($foundation);
        self::assertIsArray($kernel);

        return [
            FoundationModule::CONFIG_ROOT => $foundation,
            KernelModule::CONFIG_ROOT => $kernel,
        ];
    }

    private static function source(string $relative): string
    {
        $source = \file_get_contents(__DIR__ . '/' . $relative);

        self::assertIsString($source);

        return $source;
    }

    private static function facadeSource(): string
    {
        return self::source('../../src/Ops/KernelOpsFacade.php');
    }
}
