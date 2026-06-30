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

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Kernel\Container\Exception\ContainerArtifactMissingException;
use Coretsia\Kernel\Provider\KernelServiceFactory;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;

final class ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactMissingTest extends TestCase
{
    public function testProductionRuntimeContainerHardFailsWhenCompiledContainerArtifactIsMissing(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        new KernelServiceProvider()->register($builder);

        $container = $builder->build();

        $missingPath = self::missingContainerArtifactPath();

        try {
            KernelServiceFactory::productionRuntimeContainer(
                container: $container,
                containerArtifactPath: $missingPath,
                configPayload: [
                    'config' => self::runtimeConfigSnapshot(),
                ],
                modulePlan: ArtifactPipelineTestSupport::modulePlan(),
            );

            self::fail('Expected missing compiled container artifact failure.');
        } catch (ContainerArtifactMissingException $exception) {
            self::assertSame(
                ContainerArtifactMissingException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ContainerArtifactMissingException::MESSAGE_TOKEN,
                $exception->messageToken(),
            );
            self::assertSame(
                ContainerArtifactMissingException::REASON_MISSING,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_CONTAINER_ARTIFACT_MISSING: container-artifact-missing',
                $exception->getMessage(),
            );

            self::assertStringNotContainsString($missingPath, $exception->getMessage());
            self::assertStringNotContainsString(\sys_get_temp_dir(), $exception->getMessage());
            self::assertStringNotContainsString('No such file', $exception->getMessage());
            self::assertStringNotContainsString('failed to open stream', $exception->getMessage());
        }
    }

    private static function missingContainerArtifactPath(): string
    {
        return \sys_get_temp_dir()
            . '/coretsia-missing-container-'
            . \bin2hex(\random_bytes(8))
            . '/container.php';
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'kernel' => [
                'uow' => [
                    'attributes' => [
                        'max_depth' => 10,
                        'max_keys' => 200,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runtimeConfigSnapshot(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
            ],
            'kernel' => [
                'runtime' => [
                    'frankenphp' => [
                        'enabled' => false,
                    ],
                    'swoole' => [
                        'enabled' => false,
                    ],
                    'roadrunner' => [
                        'enabled' => false,
                    ],
                ],
                'uow' => [
                    'attributes' => [
                        'max_depth' => 10,
                        'max_keys' => 200,
                    ],
                ],
            ],
            'worker' => [
                'enabled' => false,
            ],
        ];
    }
}
