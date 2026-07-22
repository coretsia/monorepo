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

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionProviderFixture;
use PHPUnit\Framework\TestCase;

final class CompilerAndVerifierUseSameGraphFingerprintTest extends TestCase
{
    private string $skeletonRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skeletonRoot = ArtifactPipelineTestSupport::temporaryRoot(
            'compiler-verifier-graph-fingerprint',
        );
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->skeletonRoot);

        parent::tearDown();
    }

    public function testCompilerAndVerifierUseSameGraphBoundFingerprint(): void
    {
        $moduleResolution = ArtifactPipelineTestSupport::moduleResolution([
            ContainerDefinitionProviderFixture::class,
        ]);

        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
            moduleResolution: $moduleResolution,
        );

        $expectedFingerprint = ArtifactPipelineTestSupport::fingerprintForCurrentConfig(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            moduleResolution: $moduleResolution,
        );

        self::assertSame(
            [
                $expectedFingerprint,
            ],
            \array_values(
                \array_unique(
                    self::artifactFingerprints($this->skeletonRoot),
                )
            ),
        );

        $clean = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            moduleResolution: $moduleResolution,
        );

        self::assertSame('clean', $clean['outcome']);
        self::assertTrue($clean['clean']);
        self::assertFalse($clean['dirty']);
        self::assertFalse($clean['invalid']);

        $changed = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            moduleResolution: self::alternateGraphResolution(),
        );

        self::assertSame('dirty', $changed['outcome']);
        self::assertFalse($changed['clean']);
        self::assertTrue($changed['dirty']);
        self::assertFalse($changed['invalid']);

        foreach (
            [
                'module-manifest.php',
                'config.php',
                'container.php',
            ] as $basename
        ) {
            self::assertSame(
                'fingerprint_mismatch',
                self::artifactReason(
                    artifacts: $changed['artifacts'],
                    basename: $basename,
                ),
                $basename . ' must be invalidated by the changed graph-bound fingerprint.',
            );
        }
    }

    /**
     * @return array<string, non-empty-string>
     */
    private static function artifactFingerprints(string $skeletonRoot): array
    {
        $fingerprints = [];

        foreach (
            [
                'module-manifest.php',
                'config.php',
                'container.php',
            ] as $basename
        ) {
            $envelope = ArtifactPipelineTestSupport::artifactEnvelope(
                skeletonRoot: $skeletonRoot,
                basename: $basename,
            );
            $header = $envelope['_meta'] ?? null;

            self::assertIsArray($header);

            $fingerprint = $header['fingerprint'] ?? null;

            self::assertIsString($fingerprint);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $fingerprint);

            $fingerprints[$basename] = $fingerprint;
        }

        /** @var array<string, non-empty-string> $fingerprints */
        return $fingerprints;
    }

    /**
     * @param list<array<string, mixed>> $artifacts
     */
    private static function artifactReason(
        array $artifacts,
        string $basename,
    ): string {
        foreach ($artifacts as $artifact) {
            if (($artifact['basename'] ?? null) !== $basename) {
                continue;
            }

            $reason = $artifact['reason'] ?? null;

            if (\is_string($reason)) {
                return $reason;
            }
        }

        throw new \LogicException('compiler-verifier-artifact-result-missing');
    }

    private static function alternateGraphResolution(): ModuleResolution
    {
        return ArtifactPipelineTestSupport::moduleResolution([
            CompilerVerifierAlternateGraphProvider::class,
        ]);
    }
}

final class CompilerVerifierAlternateGraphProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions->classService(
            id: CompilerVerifierAlternateGraphService::class,
            class: CompilerVerifierAlternateGraphService::class,
        );
    }
}

final class CompilerVerifierAlternateGraphService
{
}
