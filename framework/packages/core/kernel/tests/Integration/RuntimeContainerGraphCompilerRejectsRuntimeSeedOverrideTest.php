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
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerGraphCompilerRejectsRuntimeSeedOverrideTest extends TestCase
{
    public function testRejectsRuntimeSeedServiceBinding(): void
    {
        try {
            ArtifactPipelineTestSupport::runtimeContainerGraphCompiler($this)->compile(
                moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                    RuntimeContainerGraphCompilerRuntimeSeedOverrideProvider::class,
                ]),
                compiledConfig: ArtifactPipelineTestSupport::defaultConfig(),
            );

            self::fail('Expected runtime seed override rejection.');
        } catch (ContainerDefinitionInvalidException $exception) {
            self::assertSame(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_CONTAINER_DEFINITION_INVALID: container-definition-invalid',
                $exception->getMessage(),
            );
        }
    }
}

final class RuntimeContainerGraphCompilerRuntimeSeedOverrideProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions->classService(
            id: ModulePlan::class,
            class: RuntimeContainerGraphCompilerRuntimeSeedOverrideSubject::class,
        );
    }
}

final class RuntimeContainerGraphCompilerRuntimeSeedOverrideSubject
{
}
