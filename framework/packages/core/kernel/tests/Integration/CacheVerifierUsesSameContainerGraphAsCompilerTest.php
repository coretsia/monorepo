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
use Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionProviderFixture;
use PHPUnit\Framework\TestCase;

final class CacheVerifierUsesSameContainerGraphAsCompilerTest extends TestCase
{
    public function testRebuildsExpectedContainerThroughSuppliedProviderPlan(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'cache-verifier-production-container-graph',
        );
        $compiledResolution = ArtifactPipelineTestSupport::moduleResolution([
            ContainerDefinitionProviderFixture::class,
        ]);

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                moduleResolution: $compiledResolution,
            );

            $clean = ArtifactPipelineTestSupport::verifyArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                moduleResolution: $compiledResolution,
            );

            self::assertSame('clean', $clean['outcome']);

            $changed = ArtifactPipelineTestSupport::verifyArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                    CacheVerifierAlternateContainerDefinitionProvider::class,
                ]),
            );

            self::assertSame('dirty', $changed['outcome']);
            self::assertSame(
                'fingerprint_mismatch',
                self::artifactReason(
                    artifacts: $changed['artifacts'],
                    basename: 'container.php',
                ),
            );
            self::assertSame(
                'fingerprint_mismatch',
                self::artifactReason(
                    artifacts: $changed['artifacts'],
                    basename: 'config.php',
                ),
            );
            self::assertSame(
                'fingerprint_mismatch',
                self::artifactReason(
                    artifacts: $changed['artifacts'],
                    basename: 'module-manifest.php',
                ),
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @param list<array<string, mixed>> $artifacts
     */
    private static function artifactReason(
        array $artifacts,
        string $basename,
    ): string {
        foreach ($artifacts as $artifact) {
            if (($artifact['basename'] ?? null) === $basename) {
                $reason = $artifact['reason'] ?? null;

                if (\is_string($reason)) {
                    return $reason;
                }
            }
        }

        throw new \LogicException('cache-verifier-artifact-result-missing');
    }
}

final class CacheVerifierAlternateContainerDefinitionProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions->classService(
            CacheVerifierAlternateContainerDefinitionService::class,
            CacheVerifierAlternateContainerDefinitionService::class,
        );
    }
}

final class CacheVerifierAlternateContainerDefinitionService
{
}
