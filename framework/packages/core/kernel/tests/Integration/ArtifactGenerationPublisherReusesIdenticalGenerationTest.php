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
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
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

final class ArtifactGenerationPublisherReusesIdenticalGenerationTest extends TestCase
{
    private const int FIXED_MTIME = 1_700_000_000;

    public function testReusesValidatedByteIdenticalGenerationWithoutRewritingFiles(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-generation-reuse');
        $artifactRoot = $root . '/var/cache/web';

        try {
            \mkdir($artifactRoot, 0777, true);

            $publicationSet = self::publicationSet(
                fingerprint: self::fingerprint('a'),
                configValue: 'stable-value',
            );
            $publisher = self::publisher($this);

            $first = $publisher->publish(
                artifactRoot: $artifactRoot,
                publicationSet: $publicationSet,
            );

            self::validator()->validate($first);

            $firstBytes = self::generationBytes($first);

            foreach (
                self::generationPaths($first) as $path
            ) {
                self::assertTrue(
                    \touch(
                        $path,
                        self::FIXED_MTIME,
                    ),
                );
                self::assertSame(
                    self::FIXED_MTIME,
                    \filemtime($path),
                );
            }

            $second = $publisher->publish(
                artifactRoot: $artifactRoot,
                publicationSet: $publicationSet,
            );

            self::assertTrue(
                $first->generationId()->equals(
                    $second->generationId(),
                ),
            );
            self::assertSame(
                $first->generationDirectory(),
                $second->generationDirectory(),
            );
            self::assertSame(
                self::fingerprint('a') . "\n",
                \file_get_contents(
                    new ArtifactGenerationPathResolver()->currentPath(
                        $artifactRoot,
                    ),
                ),
            );

            self::validator()->validate($second);
            self::assertSame(
                $firstBytes,
                self::generationBytes($second),
            );

            foreach (
                self::generationPaths($second) as $path
            ) {
                self::assertSame(
                    self::FIXED_MTIME,
                    \filemtime($path),
                    'Reused generation files must not be rewritten.',
                );
            }

            self::assertSame(
                [
                    self::fingerprint('a'),
                ],
                self::finalizedGenerationIds($artifactRoot),
            );
            self::assertSame(
                [],
                self::transientFiles($artifactRoot),
            );

            $located = self::locator()->locate($artifactRoot);

            self::assertInstanceOf(
                ArtifactGeneration::class,
                $located,
            );
            self::assertTrue(
                $located->generationId()->equals(
                    $second->generationId(),
                ),
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
            'validation' => ConfigValidationResult::success(),
            'validationSubjects' => [
                'unvalidated' => [],
                'validated' => [],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function generationBytes(
        ArtifactGeneration $generation,
    ): array {
        $bytes = [];

        foreach (
            self::generationPaths(
                $generation,
            ) as $basename => $path
        ) {
            $content =
                \file_get_contents($path);

            self::assertIsString($content);

            $bytes[$basename] = $content;
        }

        return $bytes;
    }

    /**
     * @return array<string, string>
     */
    private static function generationPaths(
        ArtifactGeneration $generation,
    ): array {
        return [
            ArtifactGeneration::CONFIG_BASENAME => $generation->configPath(),
            ArtifactGeneration::CONTAINER_BASENAME => $generation->containerPath(),
            ArtifactGeneration::GENERATION_MANIFEST_BASENAME => $generation->generationManifestPath(),
            ArtifactGeneration::MODULE_MANIFEST_BASENAME => $generation->moduleManifestPath(),
        ];
    }

    /**
     * @return list<string>
     */
    private static function finalizedGenerationIds(
        string $artifactRoot,
    ): array {
        $entries = \scandir($artifactRoot . '/generations');

        self::assertIsArray($entries);

        $generationIds = \array_values(
            \array_filter(
                $entries,
                static fn (
                    string $entry,
                ): bool => \preg_match(
                    '/\A[a-f0-9]{64}\z/',
                    $entry,
                ) === 1,
            ),
        );

        \sort(
            $generationIds,
            \SORT_STRING,
        );

        return $generationIds;
    }

    /**
     * @return list<string>
     */
    private static function transientFiles(
        string $artifactRoot,
    ): array {
        $patterns = [
            $artifactRoot . '/generations/.staging-*',
            $artifactRoot . '/.current-*',
            $artifactRoot . '/.current-backup-*',
        ];
        $files = [];

        foreach ($patterns as $pattern) {
            $matches = \glob($pattern);

            if ($matches !== false) {
                $files = [
                    ...$files,
                    ...$matches,
                ];
            }
        }

        \sort($files, \SORT_STRING);

        return \array_values($files);
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
