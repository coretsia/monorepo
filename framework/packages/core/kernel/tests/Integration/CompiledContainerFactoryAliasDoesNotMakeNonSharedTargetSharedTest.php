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

use PHPUnit\Framework\TestCase;

final class CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedTest extends TestCase
{
    public function testCompiledAliasDoesNotMakeNonSharedTargetShared(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('compiled-container-non-shared-alias');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                containerDescriptors: [
                    [
                        'kind' => 'service.class',
                        'id' => CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject::class,
                        'class' => CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject::class,
                        'shared' => false,
                    ],
                    [
                        'kind' => 'alias',
                        'alias' => 'test.non_shared.alias',
                        'serviceId' => CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject::class,
                    ],
                ],
            );

            $envelope = ArtifactPipelineTestSupport::artifactEnvelope($root, 'container.php');
            $payload = $envelope['payload'] ?? null;

            self::assertIsArray($payload);

            $services = $payload['services'] ?? null;
            $aliases = $payload['aliases'] ?? null;

            self::assertIsArray($services);
            self::assertIsArray($aliases);

            self::assertArrayHasKey(
                CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject::class,
                $services,
            );

            $definition = $services[CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject::class];

            self::assertIsArray($definition);
            self::assertFalse(
                $definition['shared'] ?? true,
                'The compiled target service must be shared=false.',
            );

            self::assertSame(
                CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject::class,
                $aliases['test.non_shared.alias'] ?? null,
                'The compiled alias must point to the non-shared target service.',
            );

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);

            $first = $container->get('test.non_shared.alias');
            $second = $container->get('test.non_shared.alias');

            self::assertInstanceOf(
                CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject::class,
                $first,
            );
            self::assertInstanceOf(
                CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject::class,
                $second,
            );
            self::assertNotSame(
                $first,
                $second,
                'A compiled alias must not accidentally cache a non-shared target.',
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}

final class CompiledContainerFactoryAliasDoesNotMakeNonSharedTargetSharedSubject
{
}
