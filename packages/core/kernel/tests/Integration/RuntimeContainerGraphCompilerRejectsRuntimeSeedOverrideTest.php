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
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerGraphCompilerRejectsRuntimeSeedOverrideTest extends TestCase
{
    /**
     * @param class-string<ContainerDefinitionProviderInterface> $providerClass
     */
    #[DataProvider('runtimeSeedOverrideProvider')]
    public function testRejectsRuntimeSeedBinding(string $providerClass): void
    {
        try {
            ArtifactPipelineTestSupport::runtimeContainerGraphCompiler($this)->compile(
                moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                    $providerClass,
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

    /**
     * @return iterable<string, array{class-string<ContainerDefinitionProviderInterface>}>
     */
    public static function runtimeSeedOverrideProvider(): iterable
    {
        yield 'module plan service' => [
            RuntimeContainerGraphCompilerRuntimeSeedOverrideProvider::class,
        ];
        yield 'runtime diagnostics service' => [
            RuntimeContainerGraphCompilerDiagnosticsSeedOverrideProvider::class,
        ];
        yield 'runtime diagnostics alias' => [
            RuntimeContainerGraphCompilerDiagnosticsSeedAliasOverrideProvider::class,
        ];
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

final class RuntimeContainerGraphCompilerDiagnosticsSeedOverrideProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions->classService(
            id: RuntimeDiagnosticsInput::class,
            class: RuntimeContainerGraphCompilerRuntimeSeedOverrideSubject::class,
        );
    }
}

final class RuntimeContainerGraphCompilerDiagnosticsSeedAliasOverrideProvider implements ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->classService(
                id: RuntimeContainerGraphCompilerRuntimeSeedOverrideSubject::class,
                class: RuntimeContainerGraphCompilerRuntimeSeedOverrideSubject::class,
            )
            ->alias(
                RuntimeDiagnosticsInput::class,
                RuntimeContainerGraphCompilerRuntimeSeedOverrideSubject::class,
            );
    }
}
