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
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use PHPUnit\Framework\TestCase;

final class ArtifactCompilerInvalidConfigDoesNotBuildGraphOrPublishTest extends TestCase
{
    public function testInvalidConfigStopsBeforeContainerGraphAndPublication(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'artifact-compiler-invalid-config-gate',
        );
        $artifactRoot = ArtifactPipelineTestSupport::artifactRoot($root);

        ArtifactCompilerInvalidConfigGraphSentinelProvider::$defineCalls = 0;

        try {
            ArtifactPipelineTestSupport::writeRootConfig(
                $root,
                ArtifactPipelineTestSupport::defaultConfig(),
            );

            $explicitRuleSources = self::writeFailingRuleset($root);

            try {
                ArtifactPipelineTestSupport::artifactCompiler($this)->compile(
                    bootstrapConfig: ArtifactPipelineTestSupport::bootstrapConfig($root),
                    moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                        ArtifactCompilerInvalidConfigGraphSentinelProvider::class,
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
                ArtifactCompilerInvalidConfigGraphSentinelProvider::$defineCalls,
            );
            self::assertDirectoryDoesNotExist($artifactRoot);
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

final class ArtifactCompilerInvalidConfigGraphSentinelProvider implements ContainerDefinitionProviderInterface
{
    public static int $defineCalls = 0;

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        ++self::$defineCalls;

        throw new \LogicException(
            'artifact-compiler-container-graph-must-not-run-after-invalid-config',
        );
    }
}
