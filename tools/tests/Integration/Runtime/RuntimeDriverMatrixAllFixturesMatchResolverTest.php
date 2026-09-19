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

namespace Coretsia\Tools\Tests\Integration\Runtime;

final class RuntimeDriverMatrixAllFixturesMatchResolverTest extends RuntimeDriverMatrixTestSupport
{
    public function testAllRuntimeDriverMatrixFixturesMatchRuntimeDriverResolver(): void
    {
        $fixtureNames = $this->runtimeDriverMatrixFixtureNames();

        self::assertSame(
            [
                'ClassicHttpApp',
                'FrankenphpHttpApp',
                'FrankenphpPlusWorkerHttpApp',
                'FrankenphpPlusWorkerQueueApp',
                'RoadrunnerHttpApp',
                'RoadrunnerPlusWorkerHttpApp',
                'RoadrunnerPlusWorkerQueueApp',
                'SwooleHttpApp',
                'SwoolePlusWorkerHttpApp',
                'SwoolePlusWorkerQueueApp',
                'WorkerHttpApp',
                'WorkerQueueApp',
            ],
            $fixtureNames,
            'Runtime driver matrix fixture corpus must be explicit and deterministic.',
        );

        foreach ($fixtureNames as $fixtureName) {
            $this->assertRuntimeDriverMatrixFixtureMatchesResolver($fixtureName);
        }
    }
}
