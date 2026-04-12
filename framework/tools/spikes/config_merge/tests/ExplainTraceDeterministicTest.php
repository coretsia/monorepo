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

namespace Coretsia\Tools\Spikes\config_merge\tests;

use Coretsia\Tools\Spikes\config_merge\ConfigExplainer;
use PHPUnit\Framework\TestCase;

final class ExplainTraceDeterministicTest extends TestCase
{
    public function testExplainTraceIsDeterministicForSameInput(): void
    {
        $explainer = new ConfigExplainer();

        $sources = [
            [
                'sourceType' => 'module',
                'file' => 'b.php',
                'config' => [
                    'b' => 'B',
                    'a' => [
                        'z' => 'Z',
                        'y' => ['@append' => ['X']],
                    ],
                ],
            ],
            [
                'sourceType' => 'defaults',
                'file' => 'a.php',
                'config' => [
                    'a' => [
                        'y' => [],
                        'x' => ['one', 'two'],
                    ],
                ],
            ],
        ];

        $t1 = $explainer->explain($sources);
        $t2 = $explainer->explain($sources);

        self::assertSame($t1, $t2);
    }

    public function testExplainTraceOrderingIsCementedByKeyPathThenRankThenFile(): void
    {
        $explainer = new ConfigExplainer();

        $sources = [
            [
                'sourceType' => 'module',
                'file' => 'b.php',
                'config' => [
                    'b' => 'B',
                    'a' => [
                        'z' => 'Z',
                        'y' => ['@append' => ['X']],
                    ],
                ],
            ],
            [
                'sourceType' => 'defaults',
                'file' => 'a.php',
                'config' => [
                    'a' => [
                        'y' => [],
                        'x' => ['one', 'two'],
                    ],
                ],
            ],
        ];

        $trace = $explainer->explain($sources);

        $expected = [
            // keyPath 'a.x' comes before 'a.y' and 'a.z'
            [
                'sourceType' => 'defaults',
                'file' => 'a.php',
                'keyPath' => 'a.x',
                'directiveApplied' => false,
            ],
            // 'a.y' exists in defaults and module; order is by keyPath then precedenceRank then file
            [
                'sourceType' => 'defaults',
                'file' => 'a.php',
                'keyPath' => 'a.y',
                'directiveApplied' => false,
            ],
            [
                'sourceType' => 'module',
                'file' => 'b.php',
                'keyPath' => 'a.y',
                'directiveApplied' => true,
            ],
            [
                'sourceType' => 'module',
                'file' => 'b.php',
                'keyPath' => 'a.z',
                'directiveApplied' => false,
            ],
            [
                'sourceType' => 'module',
                'file' => 'b.php',
                'keyPath' => 'b',
                'directiveApplied' => false,
            ],
        ];

        self::assertSame($expected, $trace);
    }
}
