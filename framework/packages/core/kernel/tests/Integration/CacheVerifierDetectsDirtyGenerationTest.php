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

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator;
use Coretsia\Kernel\Artifacts\Verifier\CacheVerifier;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;

final class CacheVerifierDetectsDirtyGenerationTest extends TestCase
{
    public function testDetectsExpectedGenerationMismatchWithoutMutatingCurrent(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('cache-verifier-dirty-generation');

        try {
            $container = self::container();
            $compiler = self::service(
                $container,
                ArtifactCompiler::class,
            );
            $verifier = self::service(
                $container,
                CacheVerifier::class,
            );
            $locator = self::service(
                $container,
                ArtifactGenerationLocator::class,
            );
            $validator = self::service(
                $container,
                ArtifactGenerationValidator::class,
            );

            $bootstrapConfig = ArtifactPipelineTestSupport::bootstrapConfig($root);
            $moduleResolution = ArtifactPipelineTestSupport::moduleResolution();
            $env = ArtifactPipelineTestSupport::envRepository();

            ArtifactPipelineTestSupport::writeRootConfig(
                $root,
                ArtifactPipelineTestSupport::defaultConfig('initial-value'),
            );

            $compiler->compile(
                bootstrapConfig: $bootstrapConfig,
                moduleResolution: $moduleResolution,
                env: $env,
                kernelConfig: ArtifactPipelineTestSupport::kernelConfig(),
                packageDefaultSources: [],
                packageRuleSources: [],
                splitRoots: [],
                explicitRuleSources: [],
                explicitEnvOverlayMappings: [],
                modePresetSourceCandidates: [],
            );

            $pathResolver = new ArtifactGenerationPathResolver();
            $artifactRoot = $root . '/var/cache/web';
            $currentPath = $pathResolver->currentPath($artifactRoot);
            $currentBefore = \file_get_contents($currentPath);
            $selectedBefore = $locator->locate($artifactRoot);

            self::assertIsString($currentBefore);
            self::assertInstanceOf(
                ArtifactGeneration::class,
                $selectedBefore,
            );

            $validator->validate($selectedBefore);

            self::assertSame(
                [
                    $selectedBefore
                        ->generationId()
                        ->value(),
                ],
                self::finalizedGenerationIds($artifactRoot),
            );

            ArtifactPipelineTestSupport::writeRootConfig(
                $root,
                ArtifactPipelineTestSupport::defaultConfig('changed-value'),
            );

            $result = $verifier->verify(
                bootstrapConfig: $bootstrapConfig,
                moduleResolution: $moduleResolution,
                env: $env,
                kernelConfig: ArtifactPipelineTestSupport::kernelConfig(),
                packageDefaultSources: [],
                packageRuleSources: [],
                splitRoots: [],
                explicitRuleSources: [],
                explicitEnvOverlayMappings: [],
                modePresetSourceCandidates: [],
            );

            self::assertSame('dirty', $result['outcome']);
            self::assertFalse($result['clean']);
            self::assertTrue($result['dirty']);
            self::assertFalse($result['invalid']);

            self::assertSame(
                4,
                $result['counts']
                ['expected_artifact_count'],
            );
            self::assertSame(
                4,
                $result['counts']
                ['existing_artifact_count'],
            );
            self::assertSame(
                0,
                $result['counts']
                ['missing_artifact_count'],
            );
            self::assertSame(
                4,
                $result['counts']
                ['dirty_artifact_count'],
            );
            self::assertSame(
                0,
                $result['counts']
                ['invalid_artifact_count'],
            );

            self::assertSame(
                [
                    'config.php',
                    'container.php',
                    'generation-manifest.php',
                    'module-manifest.php',
                ],
                \array_column(
                    $result['artifacts'],
                    'basename',
                ),
            );
            self::assertSame(
                [
                    'fingerprint_mismatch',
                    'fingerprint_mismatch',
                    'fingerprint_mismatch',
                    'fingerprint_mismatch',
                ],
                \array_column(
                    $result['artifacts'],
                    'reason',
                ),
            );
            self::assertSame(
                [
                    'dirty',
                    'dirty',
                    'dirty',
                    'dirty',
                ],
                \array_column(
                    $result['artifacts'],
                    'status',
                ),
            );

            self::assertSame(
                $currentBefore,
                \file_get_contents($currentPath),
            );
            self::assertSame(
                [
                    $selectedBefore
                        ->generationId()
                        ->value(),
                ],
                self::finalizedGenerationIds($artifactRoot),
            );
            self::assertSame(
                [],
                self::transientFiles($artifactRoot),
            );

            $selectedAfter = $locator->locate($artifactRoot);

            self::assertInstanceOf(
                ArtifactGeneration::class,
                $selectedAfter,
            );
            self::assertTrue(
                $selectedAfter
                    ->generationId()
                    ->equals(
                        $selectedBefore->generationId(),
                    ),
            );

            $validator->validate($selectedAfter);
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    private static function container(): Container
    {
        $builder = new ContainerBuilder(
            config: self::containerConfig(),
        );

        $builder->register(
            new FoundationServiceProvider(),
            new KernelServiceProvider(),
        );

        return $builder->build();
    }

    /**
     * @return array<string, mixed>
     */
    private static function containerConfig(): array
    {
        $foundation = require \dirname(
            __DIR__,
            3,
        ) . '/foundation/config/foundation.php';

        $kernel = require \dirname(
            __DIR__,
            2,
        ) . '/config/kernel.php';

        self::assertIsArray($foundation);
        self::assertIsArray($kernel);

        $foundation['container']
        ['autowire_concrete'] = true;
        $foundation['container']
        ['allow_reflection_for_concrete'] = true;
        $foundation['reset']['tag'] = ReservedTags::KERNEL_RESET;

        return [
            'foundation' => $foundation,
            'kernel' => $kernel,
        ];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(
        Container $container,
        string $id,
    ): object {
        self::assertTrue(
            $container->has($id),
        );

        $service = $container->get($id);

        self::assertInstanceOf(
            $id,
            $service,
        );

        return $service;
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
}
