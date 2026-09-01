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

use Coretsia\Kernel\Config\Source\ConfigSourceSet;
use PHPUnit\Framework\TestCase;

final class ArtifactPhysicalLayoutReproducibilityTest extends TestCase
{
    public function testEquivalentPhysicalLayoutsProduceIdenticalIdentityAndArtifactBytes(): void
    {
        $firstRoot = ArtifactPipelineTestSupport::temporaryRoot('artifact-layout-a');
        $secondRoot = ArtifactPipelineTestSupport::temporaryRoot('artifact-layout-b');
        $config = ArtifactPipelineTestSupport::defaultConfig();

        try {
            self::writeSplitConfigFiles(
                root: $firstRoot,
                config: $config,
                order: [
                    'custom',
                    'kernel',
                ],
            );

            self::writeSplitConfigFiles(
                root: $secondRoot,
                config: $config,
                order: [
                    'kernel',
                    'custom',
                ],
            );

            $firstSources = self::configSources([
                'custom',
                'kernel',
            ]);

            $secondSources = self::configSources([
                'kernel',
                'custom',
            ]);

            $first = ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $firstRoot,
                config: $config,
                configSources: $firstSources,
            );

            $second = ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $secondRoot,
                config: $config,
                configSources: $secondSources,
            );

            $firstFingerprint = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
                testCase: $this,
                skeletonRoot: $firstRoot,
                configSources: $firstSources,
            );

            $secondFingerprint = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
                testCase: $this,
                skeletonRoot: $secondRoot,
                configSources: $secondSources,
            );

            self::assertSame($firstFingerprint, $secondFingerprint);

            self::assertSame(
                $firstFingerprint,
                $first['generationId'],
            );

            self::assertSame(
                $secondFingerprint,
                $second['generationId'],
            );

            self::assertSame(
                $first['generationId'],
                $second['generationId'],
            );

            self::assertSame(
                ArtifactPipelineTestSupport::artifactBytes($firstRoot),
                ArtifactPipelineTestSupport::artifactBytes($secondRoot),
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($firstRoot);
            ArtifactPipelineTestSupport::removeTree($secondRoot);
        }
    }

    /**
     * @param array<string,mixed> $config
     * @param list<'custom'|'kernel'> $order
     */
    private static function writeSplitConfigFiles(
        string $root,
        array $config,
        array $order,
    ): void {
        foreach ($order as $configRoot) {
            $value = $config[$configRoot] ?? null;

            self::assertIsArray($value);

            ArtifactPipelineTestSupport::writePhpReturn(
                $root . '/config/' . $configRoot . '.php',
                $value,
            );
        }
    }

    /**
     * @param list<non-empty-string> $splitRoots
     */
    private static function configSources(array $splitRoots): ConfigSourceSet
    {
        return new ConfigSourceSet(
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: $splitRoots,
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: [],
        );
    }
}
