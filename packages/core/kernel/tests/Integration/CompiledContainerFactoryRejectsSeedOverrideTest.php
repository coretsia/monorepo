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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Boot\ArtifactRuntimeSeedFactory;
use Coretsia\Kernel\Container\Exception\ContainerArtifactInvalidException;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompiledContainerFactoryRejectsSeedOverrideTest extends TestCase
{
    #[DataProvider('runtimeSeedOverrideProvider')]
    public function testRejectsCompiledServiceOrAliasThatOverridesRuntimeSeed(
        string $bindingKind,
        string $runtimeSeedId,
    ): void {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'compiled-container-seed-override',
        );
        $moduleResolution =
            ArtifactPipelineTestSupport::moduleResolution();

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                applicationRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                moduleResolution: $moduleResolution,
            );

            $artifactRoot = ArtifactPipelineTestSupport::artifactRoot($root);

            $envelope = ArtifactPipelineTestSupport::artifactEnvelope(
                $root,
                'container.php',
            );
            $payload = $envelope['payload'] ?? null;

            self::assertIsArray($payload);
            self::assertIsArray($payload['services'] ?? null);
            self::assertIsArray($payload['aliases'] ?? null);

            if ($bindingKind === 'service') {
                $payload['services'][$runtimeSeedId] = [
                    'arguments' => [],
                    'construction' => [
                        'class' =>
                            CompiledContainerFactorySeedOverrideSubject::class,
                    ],
                    'id' => $runtimeSeedId,
                    'shared' => true,
                    'type' => 'class',
                ];
            } elseif ($bindingKind === 'alias') {
                $targetServiceId = \array_key_first(
                    $payload['services'],
                );

                self::assertIsString($targetServiceId);

                $payload['aliases'][$runtimeSeedId] =
                    $targetServiceId;
            } else {
                throw new \LogicException(
                    'compiled-container-seed-override-kind-invalid',
                );
            }

            \ksort($payload['services'], \SORT_STRING);
            \ksort($payload['aliases'], \SORT_STRING);

            $invalidEnvelope = $envelope;
            $invalidEnvelope['payload'] = $payload;

            new ArtifactSchemaValidator()->validateExpected(
                envelope: $invalidEnvelope,
                expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
                expectedSchemaVersion: 1,
            );

            $configPayload =
                ArtifactPipelineTestSupport::configPayloadFromArtifact(
                    $root,
                );
            $compiledConfig = $configPayload['config'] ?? null;

            self::assertIsArray($compiledConfig);

            $seedFactory = new ArtifactRuntimeSeedFactory();
            $seeds = $seedFactory->create(
                input: new ArtifactRuntimeInput(
                    applicationRoot: $root,
                    artifactRoot: $artifactRoot,
                ),
                configRepository: $seedFactory->hydrateConfigRepository($configPayload),
                modulePlan: $moduleResolution->plan(),
                configPayload: $configPayload,
                artifactGenerationLoaded: false,
            );

            $factory = ArtifactPipelineTestSupport::compiledContainerFactory();
            $container = $factory->buildFromEnvelope(
                containerEnvelope: $envelope,
                seeds: $seeds,
            );

            self::assertSame(
                $seeds->instances()[$runtimeSeedId],
                $container->get($runtimeSeedId),
            );

            try {
                $factory->buildFromEnvelope(
                    containerEnvelope: $invalidEnvelope,
                    seeds: $seeds,
                );

                self::fail(
                    'Expected compiled runtime seed override rejection.',
                );
            } catch (
                ContainerArtifactInvalidException $exception
            ) {
                self::assertSame(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                    $exception->reason(),
                );
                self::assertSame(
                    'CORETSIA_CONTAINER_ARTIFACT_INVALID: '
                    . 'container-artifact-invalid',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    $runtimeSeedId,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    $artifactRoot,
                    $exception->getMessage(),
                );
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @return iterable<string, array{
     *     bindingKind: string,
     *     runtimeSeedId: class-string
     * }>
     */
    public static function runtimeSeedOverrideProvider(): iterable
    {
        yield 'service overrides config repository seed' => [
            'bindingKind' => 'service',
            'runtimeSeedId' =>
                ConfigRepositoryInterface::class,
        ];

        yield 'service overrides module plan seed' => [
            'bindingKind' => 'service',
            'runtimeSeedId' => ModulePlan::class,
        ];

        yield 'alias overrides runtime path seed' => [
            'bindingKind' => 'alias',
            'runtimeSeedId' => RuntimePathContext::class,
        ];
        yield 'service overrides runtime diagnostics seed' => [
            'bindingKind' => 'service',
            'runtimeSeedId' => RuntimeDiagnosticsInput::class,
        ];

        yield 'alias overrides runtime diagnostics seed' => [
            'bindingKind' => 'alias',
            'runtimeSeedId' => RuntimeDiagnosticsInput::class,
        ];
    }
}

final class CompiledContainerFactorySeedOverrideSubject
{
}
