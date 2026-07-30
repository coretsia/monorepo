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

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestBuilder;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactPublicationSet;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;

final class ArtifactGenerationPublisherRejectsIncompleteGenerationTest extends TestCase
{
    public function testRejectsIncompleteExistingGenerationWithoutChangingCurrent(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-generation-incomplete');
        $artifactRoot = $root . '/var/cache/web';

        try {
            \mkdir($artifactRoot, 0777, true);

            $publisher = self::publisher($this);
            $previousPublicationSet = self::publicationSet(
                fingerprint: self::fingerprint('a'),
                configValue: 'previous-value',
            );
            $nextPublicationSet = self::publicationSet(
                fingerprint: self::fingerprint('b'),
                configValue: 'next-value',
            );
            $previousGeneration = $publisher->publish(
                artifactRoot: $artifactRoot,
                publicationSet: $previousPublicationSet,
            );

            $pathResolver = new ArtifactGenerationPathResolver();
            $currentPath = $pathResolver->currentPath($artifactRoot);
            $currentBefore = \file_get_contents($currentPath);
            $incompleteGeneration = $pathResolver->generation(
                artifactRoot: $artifactRoot,
                generationId: ArtifactGenerationId::fromString(
                    self::fingerprint('b'),
                ),
            );

            self::assertIsString($currentBefore);
            self::assertTrue(
                \mkdir(
                    $incompleteGeneration
                        ->generationDirectory(),
                    0777,
                    true,
                ),
            );
            self::assertSame(
                \strlen($nextPublicationSet->configBytes()),
                \file_put_contents(
                    $incompleteGeneration->configPath(),
                    $nextPublicationSet->configBytes(),
                ),
            );

            try {
                $publisher->publish(
                    artifactRoot: $artifactRoot,
                    publicationSet: $nextPublicationSet,
                );

                self::fail('Expected ArtifactGenerationPublishException was not thrown.');
            } catch (
                ArtifactGenerationPublishException
                $exception
            ) {
                self::assertSame(
                    ArtifactGenerationPublishException::ERROR_CODE,
                    $exception->errorCode(),
                );
                self::assertSame(
                    ArtifactGenerationPublishException::REASON_GENERATION_CONFLICT,
                    $exception->reason(),
                );
                self::assertSame(
                    ArtifactGenerationPublishException::ERROR_CODE
                    . ': '
                    . ArtifactGenerationPublishException::REASON_GENERATION_CONFLICT,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    $artifactRoot,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    self::fingerprint('b'),
                    $exception->getMessage(),
                );
            }

            self::assertSame(
                $currentBefore,
                \file_get_contents($currentPath),
            );

            $located = self::locator()->locate($artifactRoot);

            self::assertInstanceOf(
                ArtifactGeneration::class,
                $located,
            );
            self::assertTrue(
                $located->generationId()->equals(
                    $previousGeneration->generationId(),
                ),
            );

            self::validator()->validate($located);

            try {
                self::validator()->validate($incompleteGeneration);
                self::fail('Expected incomplete generation validation to fail.');
            } catch (
                ArtifactInvalidException $exception
            ) {
                self::assertSame(
                    ArtifactInvalidException::ERROR_CODE,
                    $exception->errorCode(),
                );
                self::assertSame(
                    ArtifactInvalidException::REASON_INVALID,
                    $exception->reason(),
                );
            }

            self::assertDirectoryExists(
                $incompleteGeneration->generationDirectory(),
            );
            self::assertFileExists(
                $incompleteGeneration->configPath(),
            );
            self::assertFileDoesNotExist(
                $incompleteGeneration->moduleManifestPath(),
            );
            self::assertFileDoesNotExist(
                $incompleteGeneration->containerPath(),
            );
            self::assertFileDoesNotExist(
                $incompleteGeneration->generationManifestPath(),
            );
            self::assertSame(
                [],
                self::stagingDirectories($artifactRoot),
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    private static function publisher(
        TestCase $testCase,
    ): ArtifactGenerationPublisher {
        $normalizer = new PayloadNormalizer();
        $envelopeFactory = new ArtifactEnvelopeFactory($normalizer);
        $dumper = new StablePhpArrayDumper($normalizer);
        $pathResolver = new ArtifactGenerationPathResolver();

        return new ArtifactGenerationPublisher(
            artifactWriter: ArtifactPipelineTestSupport::artifactWriter($testCase),
            phpArrayDumper: $dumper,
            manifestBuilder: new ArtifactGenerationManifestBuilder($envelopeFactory),
            validator: self::validator(),
            lock: new ArtifactGenerationLock($pathResolver),
            pathResolver: $pathResolver,
        );
    }

    private static function locator(): ArtifactGenerationLocator
    {
        $pathResolver = new ArtifactGenerationPathResolver();

        return new ArtifactGenerationLocator(
            lock: new ArtifactGenerationLock($pathResolver),
            pathResolver: $pathResolver,
            validator: self::validator(),
        );
    }

    private static function validator(): ArtifactGenerationValidator
    {
        $schemaValidator = new ArtifactSchemaValidator();

        return new ArtifactGenerationValidator(
            artifactReader: new PhpArtifactReader(),
            schemaValidator: $schemaValidator,
            manifestValidator: new ArtifactGenerationManifestValidator($schemaValidator),
        );
    }

    private static function publicationSet(
        string $fingerprint,
        string $configValue,
    ): ArtifactPublicationSet {
        $normalizer = new PayloadNormalizer();
        $envelopeFactory = new ArtifactEnvelopeFactory($normalizer);
        $dumper = new StablePhpArrayDumper($normalizer);

        $moduleManifestEnvelope = new ModuleManifestBuilder($envelopeFactory)->build(
            modulePlan: self::modulePlan(),
            fingerprint: $fingerprint,
        );

        $configEnvelope = new CompiledConfigBuilder($envelopeFactory)->build(
            compiledConfig: self::compiledConfig($configValue),
            fingerprint: $fingerprint,
        );

        $containerEnvelope = new CompiledContainerBuilder($envelopeFactory)->build(
            graph: DefinitionGraph::empty(),
            fingerprint: $fingerprint,
        );

        return new ArtifactPublicationSet(
            moduleManifestEnvelope: $moduleManifestEnvelope,
            moduleManifestBytes: $dumper->dumpEnvelope($moduleManifestEnvelope),
            configEnvelope: $configEnvelope,
            configBytes: $dumper->dumpEnvelope($configEnvelope),
            containerEnvelope: $containerEnvelope,
            containerBytes: $dumper->dumpEnvelope($containerEnvelope),
        );
    }

    private static function modulePlan(): ModulePlan
    {
        return new ModulePlan(
            app: 'web',
            preset: 'default',
            enabled: [],
            disabled: [],
            optionalMissing: [],
            topologicalOrder: [],
            modules: [],
            warnings: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function compiledConfig(
        string $configValue,
    ): array {
        return [
            'config' => [
                'custom' => [
                    'feature' => [
                        'value' => $configValue,
                    ],
                ],
            ],
            'sources' => [],
            'owners' => [],
            'envOverlayMappings' => [],
            'configSourceFiles' => [],
            'validation' =>
                ConfigValidationResult::success(),
            'validationSubjects' => [
                'unvalidated' => [],
                'validated' => [],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function stagingDirectories(
        string $artifactRoot,
    ): array {
        $matches = \glob(
            $artifactRoot
            . '/generations/.staging-*',
        );

        if ($matches === false) {
            return [];
        }

        \sort($matches, \SORT_STRING);

        return \array_values($matches);
    }

    private static function fingerprint(
        string $character,
    ): string {
        return \str_repeat(
            $character,
            64,
        );
    }
}
