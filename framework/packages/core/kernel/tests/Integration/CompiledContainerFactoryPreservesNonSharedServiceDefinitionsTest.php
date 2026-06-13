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

final class CompiledContainerFactoryPreservesNonSharedServiceDefinitionsTest extends TestCase
{
    public function testCompiledContainerFactoryPreservesNonSharedServiceDefinitions(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('compiled-container-non-shared-service');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
                containerDescriptors: [
                    [
                        'kind' => 'service.class',
                        'id' => CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class,
                        'class' => CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class,
                        'shared' => false,
                    ],
                ],
            );

            $envelope = ArtifactPipelineTestSupport::artifactEnvelope($root, 'container.php');
            $payload = $envelope['payload'] ?? null;

            self::assertIsArray($payload);

            $services = $payload['services'] ?? null;

            self::assertIsArray($services);
            self::assertArrayHasKey(
                CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class,
                $services,
            );

            $definition = $services[CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class];

            self::assertIsArray($definition);
            self::assertSame(
                CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class,
                $definition['id'] ?? null,
            );
            self::assertFalse(
                $definition['shared'] ?? true,
                'The container artifact service definition must preserve shared=false.',
            );

            $container = ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);

            $first = $container->get(CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class);
            $second = $container->get(CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class);

            self::assertInstanceOf(
                CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class,
                $first,
            );
            self::assertInstanceOf(
                CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject::class,
                $second,
            );
            self::assertNotSame(
                $first,
                $second,
                'A compiled service with shared=false must resolve to a fresh object on every get().',
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}

final class CompiledContainerFactoryPreservesNonSharedServiceDefinitionsSubject
{
}
