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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArtifactGenerationPathResolverTest extends TestCase
{
    public function testResolvesExactUnixGenerationLayout(): void
    {
        $resolver = new ArtifactGenerationPathResolver();
        $generationId = self::generationId();
        $artifactRoot = '/var/cache/coretsia/web';
        $generationDirectory = $artifactRoot
            . '/generations/'
            . $generationId->value();

        $generation = $resolver->generation(
            artifactRoot: $artifactRoot,
            generationId: $generationId,
        );

        self::assertSame(
            $artifactRoot . '/generations',
            $resolver->generationsDirectory($artifactRoot),
        );
        self::assertSame(
            $generationDirectory,
            $generation->generationDirectory(),
        );
        self::assertSame(
            $generationDirectory . '/module-manifest.php',
            $generation->moduleManifestPath(),
        );
        self::assertSame(
            $generationDirectory . '/config.php',
            $generation->configPath(),
        );
        self::assertSame(
            $generationDirectory . '/container.php',
            $generation->containerPath(),
        );
        self::assertSame(
            $generationDirectory . '/generation-manifest.php',
            $generation->generationManifestPath(),
        );
    }

    public function testNormalizesWindowsDriveRootToForwardSlashes(): void
    {
        $resolver = new ArtifactGenerationPathResolver();
        $generationId = self::generationId();

        self::assertSame(
            'C:/cache/coretsia/web/generations/'
            . $generationId->value(),
            $resolver->generationDirectory(
                artifactRoot: 'C:\\cache\\coretsia\\web\\',
                generationId: $generationId,
            ),
        );
        self::assertSame(
            'C:/cache/coretsia/web/current',
            $resolver->currentPath(
                'C:\\cache\\coretsia\\web\\',
            ),
        );
        self::assertSame(
            'C:/cache/coretsia/web/generation.lock',
            $resolver->generationLockPath(
                'C:\\cache\\coretsia\\web\\',
            ),
        );
    }

    public function testNormalizesCanonicalUncRootAndResolvesGenerationLayout(): void
    {
        $resolver = new ArtifactGenerationPathResolver();
        $generationId = self::generationId();

        self::assertSame(
            '//server/share/cache/generations/'
            . $generationId->value(),
            $resolver->generationDirectory(
                artifactRoot: '//server/share/cache/',
                generationId: $generationId,
            ),
        );
        self::assertSame(
            '//server/share/cache/current',
            $resolver->currentPath('//server/share/cache/'),
        );
        self::assertSame(
            '//server/share/cache/generation.lock',
            $resolver->generationLockPath('//server/share/cache/'),
        );
    }

    #[DataProvider('malformedUncRootProvider')]
    public function testRejectsMalformedUncArtifactRoots(
        string $artifactRoot,
    ): void {
        try {
            new ArtifactGenerationPathResolver()->generationsDirectory(
                $artifactRoot,
            );
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                'artifact-generation-root-invalid',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $artifactRoot,
                $exception->getMessage(),
            );

            return;
        }

        self::fail(
            'Malformed UNC artifact root must be rejected.',
        );
    }

    public function testResolvesExplicitAndGeneratedStagingSuffixes(): void
    {
        $resolver = new ArtifactGenerationPathResolver();
        $generationId = self::generationId();
        $artifactRoot = '/var/cache/coretsia/web';
        $explicitSuffix = \str_repeat('b', 32);

        self::assertSame(
            $artifactRoot
            . '/generations/.staging-'
            . $generationId->value()
            . '-'
            . $explicitSuffix,
            $resolver->stagingDirectory(
                artifactRoot: $artifactRoot,
                generationId: $generationId,
                randomSuffix: $explicitSuffix,
            ),
        );

        self::assertMatchesRegularExpression(
            '/\A\/var\/cache\/coretsia\/web\/generations\/\.staging-'
            . $generationId->value()
            . '-[a-f0-9]{32}\z/',
            $resolver->newStagingDirectory(
                artifactRoot: $artifactRoot,
                generationId: $generationId,
            ),
        );
    }

    public function testResolvesCurrentAndGenerationLockAsArtifactRootChildren(): void
    {
        $resolver = new ArtifactGenerationPathResolver();

        self::assertSame(
            '/var/cache/coretsia/web/current',
            $resolver->currentPath('/var/cache/coretsia/web'),
        );
        self::assertSame(
            '/var/cache/coretsia/web/generation.lock',
            $resolver->generationLockPath('/var/cache/coretsia/web'),
        );
    }

    /**
     * @return iterable<string, array{0:non-empty-string}>
     */
    public static function malformedUncRootProvider(): iterable
    {
        yield 'triple-leading-separator' => [
            '///server/share/cache',
        ];

        yield 'missing-share-component' => [
            '//server',
        ];

        yield 'empty-share-component' => [
            '//server//cache',
        ];
    }

    private static function generationId(): ArtifactGenerationId
    {
        return new ArtifactGenerationId(
            \str_repeat('a', 64),
        );
    }
}
