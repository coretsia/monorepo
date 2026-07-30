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

use Coretsia\Contracts\Config\ConfigValidationViolation;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use PHPUnit\Framework\TestCase;

final class CacheVerifierInvalidConfigDoesNotBuildGraphOrReadCurrentTest extends TestCase
{
    public function testInvalidConfigStopsBeforeContainerGraphCurrentLocationAndArtifactReads(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'cache-verifier-invalid-config-gate',
        );

        CacheVerifierInvalidConfigGraphSentinelProvider::$defineCalls = 0;

        try {
            $compileResult = ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $publishedGenerationId = $compileResult['generationId'] ?? null;

            self::assertIsString($publishedGenerationId);
            self::assertMatchesRegularExpression(
                '/\A[a-f0-9]{64}\z/',
                $publishedGenerationId,
            );

            $artifactRoot = ArtifactPipelineTestSupport::artifactRoot($root);
            $currentPath = new ArtifactGenerationPathResolver()
                ->currentPath($artifactRoot);
            $currentBefore = \file_get_contents($currentPath);
            $artifactBytesBefore = ArtifactPipelineTestSupport::artifactBytes($root);
            $explicitRuleSources = self::writeFailingRuleset($root);

            self::assertIsString($currentBefore);
            self::assertSame(
                $publishedGenerationId . "\n",
                $currentBefore,
            );

            try {
                ArtifactPipelineTestSupport::cacheVerifier($this)->verify(
                    bootstrapConfig: ArtifactPipelineTestSupport::bootstrapConfig($root),
                    moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                        CacheVerifierInvalidConfigGraphSentinelProvider::class,
                    ]),
                    env: ArtifactPipelineTestSupport::envRepository(),
                    kernelConfig: ArtifactPipelineTestSupport::kernelConfig(),
                    packageDefaultSources: [],
                    packageRuleSources: [],
                    splitRoots: [],
                    explicitRuleSources: $explicitRuleSources,
                    explicitEnvOverlayMappings: [],
                    modePresetSourceCandidates: [],
                );

                self::fail('Expected ConfigInvalidException was not thrown.');
            } catch (ConfigInvalidException $exception) {
                self::assertSame(
                    ConfigInvalidException::REASON_VALIDATION_FAILED,
                    $exception->reason(),
                );

                $validation = $exception->validationResult();

                self::assertNotNull($validation);
                self::assertTrue($validation->isFailure());
                self::assertSame(
                    [
                        [
                            'actualType' => 'null',
                            'expected' => 'present',
                            'path' => 'required_value',
                            'reason' => 'required',
                            'root' => 'custom',
                            'schemaVersion' => 1,
                        ],
                    ],
                    \array_map(
                        static fn (ConfigValidationViolation $violation): array => $violation->toArray(),
                        $validation->violations(),
                    ),
                );
            }

            self::assertSame(
                0,
                CacheVerifierInvalidConfigGraphSentinelProvider::$defineCalls,
            );
            self::assertSame(
                $currentBefore,
                \file_get_contents($currentPath),
            );
            self::assertSame(
                $artifactBytesBefore,
                ArtifactPipelineTestSupport::artifactBytes($root),
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @return list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId: string,
     *     precedence: int
     * }>
     */
    private static function writeFailingRuleset(string $root): array
    {
        $filesystemPath = $root . '/test-rules/custom/config/rules.php';

        ArtifactPipelineTestSupport::writePhpReturn(
            $filesystemPath,
            [
                'schemaVersion' => 1,
                'configRoot' => 'custom',
                'additionalKeys' => true,
                'keys' => [
                    'required_value' => [
                        'required' => true,
                        'type' => 'map',
                        'additionalKeys' => true,
                    ],
                ],
            ],
        );

        return [
            [
                'root' => 'custom',
                'packageId' => 'coretsia/core-kernel-test',
                'moduleId' => null,
                'path' => 'test-rules/custom/config/rules.php',
                'filesystemPath' => $filesystemPath,
                'sourceId' => 'test.invalid.custom.rules',
                'precedence' => 1000,
            ],
        ];
    }
}

final class CacheVerifierInvalidConfigGraphSentinelProvider implements ContainerDefinitionProviderInterface
{
    public static int $defineCalls = 0;

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        ++self::$defineCalls;

        throw new \LogicException(
            'cache-verifier-container-graph-must-not-run-after-invalid-config',
        );
    }
}
