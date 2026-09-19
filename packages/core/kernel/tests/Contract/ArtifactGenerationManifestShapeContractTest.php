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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestBuilder;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactPublicationSet;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;

final class ArtifactGenerationManifestShapeContractTest extends TestCase
{
    public function testGenerationManifestUsesExactCanonicalEnvelopeAndPayloadShape(): void
    {
        $publicationSet = self::publicationSet();
        $envelope = new ArtifactGenerationManifestBuilder(
            self::envelopeFactory(),
        )->build($publicationSet);

        self::assertSame(['_meta', 'payload'], \array_keys($envelope));

        $header = $envelope['_meta'];
        $payload = $envelope['payload'];

        self::assertSame(
            ['fingerprint', 'generator', 'name', 'schemaVersion'],
            \array_keys($header),
        );
        self::assertSame(
            ArtifactEnvelopeFactory::ARTIFACT_GENERATION,
            $header['name'],
        );
        self::assertSame(
            ArtifactEnvelopeFactory::SCHEMA_VERSION_ARTIFACT_GENERATION,
            $header['schemaVersion'],
        );
        self::assertSame(
            $publicationSet->fingerprint(),
            $header['fingerprint'],
        );
        self::assertSame(
            'core/kernel/artifacts',
            $header['generator'],
        );
        self::assertArrayNotHasKey('requires', $header);

        self::assertSame(
            ['artifacts', 'generationId', 'schemaVersion'],
            \array_keys($payload),
        );
        self::assertSame(
            ['config.php', 'container.php', 'module-manifest.php'],
            \array_keys($payload['artifacts']),
        );
        self::assertSame(
            $publicationSet->fingerprint(),
            $payload['generationId'],
        );
        self::assertSame(
            ArtifactEnvelopeFactory::SCHEMA_VERSION_ARTIFACT_GENERATION,
            $payload['schemaVersion'],
        );

        foreach ($publicationSet->artifactBytes() as $basename => $bytes) {
            self::assertSame(
                ['bytes', 'sha256'],
                \array_keys($payload['artifacts'][$basename]),
            );
            self::assertSame(
                \strlen($bytes),
                $payload['artifacts'][$basename]['bytes'],
            );
            self::assertSame(
                \hash('sha256', $bytes),
                $payload['artifacts'][$basename]['sha256'],
            );
        }

        new ArtifactGenerationManifestValidator()->validate($envelope);
    }

    public function testGenerationManifestDoesNotExportPathLikeFieldsOrValues(): void
    {
        $envelope = new ArtifactGenerationManifestBuilder(
            self::envelopeFactory(),
        )->build(self::publicationSet());

        self::assertNoPathLikeData($envelope);
    }

    private static function publicationSet(): ArtifactPublicationSet
    {
        $factory = self::envelopeFactory();
        $fingerprint = self::fingerprint();

        $moduleManifestEnvelope = new ModuleManifestBuilder($factory)->build(
            modulePlan: self::modulePlan(),
            fingerprint: $fingerprint,
        );
        $configEnvelope = new CompiledConfigBuilder($factory)->build(
            compiledConfig: self::compiledConfig(),
            fingerprint: $fingerprint,
        );
        $containerEnvelope = new CompiledContainerBuilder($factory)->build(
            graph: DefinitionGraph::empty(),
            fingerprint: $fingerprint,
        );

        return new ArtifactPublicationSet(
            moduleManifestEnvelope: $moduleManifestEnvelope,
            moduleManifestBytes: StablePhpArrayDumper::dumpStableEnvelope(
                $moduleManifestEnvelope,
            ),
            configEnvelope: $configEnvelope,
            configBytes: StablePhpArrayDumper::dumpStableEnvelope(
                $configEnvelope,
            ),
            containerEnvelope: $containerEnvelope,
            containerBytes: StablePhpArrayDumper::dumpStableEnvelope(
                $containerEnvelope,
            ),
        );
    }

    private static function envelopeFactory(): ArtifactEnvelopeFactory
    {
        return new ArtifactEnvelopeFactory(new PayloadNormalizer());
    }

    private static function modulePlan(): ModulePlan
    {
        return new ModulePlan(
            app: 'api',
            preset: 'micro',
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
    private static function compiledConfig(): array
    {
        return [
            'config' => [
                'kernel' => [
                    'boot' => [
                        'default_artifacts_cache_dir' => 'var/cache',
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
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'kernel',
                        'validation' => 'validated',
                    ],
                ],
            ],
        ];
    }

    private static function fingerprint(): string
    {
        return \str_repeat('a', 64);
    }

    private static function assertNoPathLikeData(mixed $value): void
    {
        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                if (\is_string($key)) {
                    self::assertNotContains(
                        $key,
                        self::pathLikeKeys(),
                        'Generation manifest must not export path-like keys.',
                    );
                }

                self::assertNoPathLikeData($item);
            }

            return;
        }

        if (!\is_string($value)) {
            return;
        }

        self::assertDoesNotMatchRegularExpression(
            '/\A(?:\/|\\\\|[A-Za-z]:[\/\\\\])/',
            $value,
            'Generation manifest must not export absolute path values.',
        );
    }

    /**
     * @return list<string>
     */
    private static function pathLikeKeys(): array
    {
        return [
            'absolutePath',
            'artifactRoot',
            'currentPath',
            'generationDirectory',
            'generationLockPath',
            'path',
            'paths',
            'relativePath',
            'sourcePath',
            'stagingDirectory',
        ];
    }
}
