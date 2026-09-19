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

use Coretsia\Foundation\Clock\SystemClock;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionFixtureClock;
use Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionFixtureService;
use Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionProviderFixture;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class RuntimeContainerGraphCompilerUsesProviderPlanTest extends TestCase
{
    public function testUsesDeclaredProviderPlanOrderWithoutResortingProviders(): void
    {
        $compiler = ArtifactPipelineTestSupport::runtimeContainerGraphCompiler($this);
        $compiledConfig = ArtifactPipelineTestSupport::defaultConfig();

        $foundationThenFixture = $compiler->compile(
            moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                FoundationServiceProvider::class,
                ContainerDefinitionProviderFixture::class,
            ]),
            compiledConfig: $compiledConfig,
        )->toArray();

        self::assertArrayHasKey(
            ContainerDefinitionFixtureService::class,
            $foundationThenFixture['services'],
        );
        self::assertSame(
            ContainerDefinitionFixtureClock::class,
            $foundationThenFixture['aliases'][ClockInterface::class] ?? null,
            'The later fixture provider must override the earlier Foundation alias.',
        );

        $fixtureThenFoundation = $compiler->compile(
            moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                ContainerDefinitionProviderFixture::class,
                FoundationServiceProvider::class,
            ]),
            compiledConfig: $compiledConfig,
        )->toArray();

        self::assertSame(
            SystemClock::class,
            $fixtureThenFoundation['aliases'][ClockInterface::class] ?? null,
            'The later Foundation provider must override the earlier fixture alias.',
        );
    }
}
