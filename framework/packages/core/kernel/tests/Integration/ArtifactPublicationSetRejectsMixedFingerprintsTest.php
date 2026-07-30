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
use Coretsia\Kernel\Artifacts\Generation\ArtifactPublicationSet;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArtifactPublicationSetRejectsMixedFingerprintsTest extends TestCase
{
    #[DataProvider('mixedFingerprintProvider')]
    public function testRejectsMixedFingerprintInEveryPublicationSetPosition(
        string $moduleManifestFingerprint,
        string $configFingerprint,
        string $containerFingerprint,
    ): void {
        $envelopes = self::artifactEnvelopes(
            moduleManifestFingerprint: $moduleManifestFingerprint,
            configFingerprint: $configFingerprint,
            containerFingerprint: $containerFingerprint,
        );
        $bytes = self::canonicalBytes($envelopes);

        try {
            self::publicationSet($envelopes, $bytes);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                'artifact-publication-set-fingerprint-mismatch',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $moduleManifestFingerprint,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $configFingerprint,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $containerFingerprint,
                $exception->getMessage(),
            );

            return;
        }

        self::fail(
            'ArtifactPublicationSet must reject mixed fingerprints.',
        );
    }

    #[DataProvider('artifactBasenameProvider')]
    public function testRejectsCanonicalBytesMismatchForEveryArtifact(
        string $basename,
    ): void {
        $fingerprint = self::fingerprint('a');
        $envelopes = self::artifactEnvelopes(
            moduleManifestFingerprint: $fingerprint,
            configFingerprint: $fingerprint,
            containerFingerprint: $fingerprint,
        );
        $bytes = self::canonicalBytes($envelopes);
        $bytes[$basename] .= "\n";

        try {
            self::publicationSet($envelopes, $bytes);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                'artifact-publication-set-bytes-invalid',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $bytes[$basename],
                $exception->getMessage(),
            );

            return;
        }

        self::fail(
            'ArtifactPublicationSet must reject non-canonical artifact bytes.',
        );
    }

    public function testAcceptsCanonicalPublicationSetAndExportsStableArtifactOrder(): void
    {
        $fingerprint = self::fingerprint('a');
        $envelopes = self::artifactEnvelopes(
            moduleManifestFingerprint: $fingerprint,
            configFingerprint: $fingerprint,
            containerFingerprint: $fingerprint,
        );
        $bytes = self::canonicalBytes($envelopes);
        $publicationSet = self::publicationSet(
            $envelopes,
            $bytes,
        );

        self::assertSame(
            $fingerprint,
            $publicationSet->fingerprint(),
        );
        self::assertSame(
            ['config.php', 'container.php', 'module-manifest.php'],
            \array_keys($publicationSet->artifactBytes()),
        );
        self::assertSame(
            $bytes,
            $publicationSet->artifactBytes(),
        );
    }

    /**
     * @return iterable<string, array{0:string, 1:string, 2:string}>
     */
    public static function mixedFingerprintProvider(): iterable
    {
        yield 'module-manifest-mismatch' => [
            self::fingerprint('b'),
            self::fingerprint('a'),
            self::fingerprint('a'),
        ];

        yield 'config-mismatch' => [
            self::fingerprint('a'),
            self::fingerprint('b'),
            self::fingerprint('a'),
        ];

        yield 'container-mismatch' => [
            self::fingerprint('a'),
            self::fingerprint('a'),
            self::fingerprint('b'),
        ];
    }

    /**
     * @return iterable<string, array{0:non-empty-string}>
     */
    public static function artifactBasenameProvider(): iterable
    {
        yield 'module-manifest' => [
            'module-manifest.php',
        ];

        yield 'config' => [
            'config.php',
        ];

        yield 'container' => [
            'container.php',
        ];
    }

    /**
     * @return array{
     *     'module-manifest.php': array<string, mixed>,
     *     'config.php': array<string, mixed>,
     *     'container.php': array<string, mixed>
     * }
     */
    private static function artifactEnvelopes(
        string $moduleManifestFingerprint,
        string $configFingerprint,
        string $containerFingerprint,
    ): array {
        $factory = self::envelopeFactory();

        return [
            'module-manifest.php' => new ModuleManifestBuilder(
                $factory,
            )->build(
                modulePlan: self::modulePlan(),
                fingerprint: $moduleManifestFingerprint,
            ),
            'config.php' => new CompiledConfigBuilder(
                $factory,
            )->build(
                compiledConfig: self::compiledConfig(),
                fingerprint: $configFingerprint,
            ),
            'container.php' => new CompiledContainerBuilder(
                $factory,
            )->build(
                graph: DefinitionGraph::empty(),
                fingerprint: $containerFingerprint,
            ),
        ];
    }

    /**
     * @param array{
     *     'module-manifest.php': array<string, mixed>,
     *     'config.php': array<string, mixed>,
     *     'container.php': array<string, mixed>
     * } $envelopes
     *
     * @return array{
     *     'config.php': string,
     *     'container.php': string,
     *     'module-manifest.php': string
     * }
     */
    private static function canonicalBytes(array $envelopes): array
    {
        return [
            'config.php' => StablePhpArrayDumper::dumpStableEnvelope(
                $envelopes['config.php'],
            ),
            'container.php' => StablePhpArrayDumper::dumpStableEnvelope(
                $envelopes['container.php'],
            ),
            'module-manifest.php' => StablePhpArrayDumper::dumpStableEnvelope(
                $envelopes['module-manifest.php'],
            ),
        ];
    }

    /**
     * @param array{
     *     'module-manifest.php': array<string, mixed>,
     *     'config.php': array<string, mixed>,
     *     'container.php': array<string, mixed>
     * } $envelopes
     * @param array{
     *     'config.php': string,
     *     'container.php': string,
     *     'module-manifest.php': string
     * } $bytes
     */
    private static function publicationSet(
        array $envelopes,
        array $bytes,
    ): ArtifactPublicationSet {
        return new ArtifactPublicationSet(
            moduleManifestEnvelope: $envelopes['module-manifest.php'],
            moduleManifestBytes: $bytes['module-manifest.php'],
            configEnvelope: $envelopes['config.php'],
            configBytes: $bytes['config.php'],
            containerEnvelope: $envelopes['container.php'],
            containerBytes: $bytes['container.php'],
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

    private static function fingerprint(string $character): string
    {
        return \str_repeat($character, 64);
    }
}
