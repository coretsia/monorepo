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

use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\config_merge\DirectiveProcessor;
use PHPUnit\Framework\TestCase;

final class DirectivesTypingRulesTest extends TestCase
{
    public function testEmptyArrayRuleAppendWithEmptyArrayIsAcceptedInPhaseA(): void
    {
        $p = new DirectiveProcessor();

        $config = [
            'http' => [
                'middleware' => [
                    'system_post' => [
                        '@append' => [],
                    ],
                ],
            ],
        ];

        // MUST NOT throw (empty-array rule: accepted as empty list).
        $p->validatePhaseA($config);

        self::assertTrue(true);
    }

    public function testEmptyArrayRuleMergeWithEmptyArrayIsAcceptedInPhaseA(): void
    {
        $p = new DirectiveProcessor();

        $config = [
            'http' => [
                'features' => [
                    '@merge' => [],
                ],
            ],
        ];

        // MUST NOT throw (empty-array rule: accepted as empty map).
        $p->validatePhaseA($config);

        self::assertTrue(true);
    }

    public function testEmptyArrayRuleAppendIsNoopInPhaseB(): void
    {
        $p = new DirectiveProcessor();

        $base = ['A', 'B'];
        $node = ['@append' => []];

        $out = $p->applyPhaseB($base, $node);

        self::assertSame(['A', 'B'], $out);
    }

    public function testEmptyArrayRuleMergeIsNoopInPhaseB(): void
    {
        $p = new DirectiveProcessor();

        $base = ['request_id' => true];
        $node = ['@merge' => []];

        $out = $p->applyPhaseB($base, $node);

        self::assertSame(['request_id' => true], $out);
    }

    public function testTypingRuleAppendRequiresListNonEmptyMapMustFailInPhaseA(): void
    {
        $p = new DirectiveProcessor();

        $config = [
            'x' => [
                '@append' => [
                    'k' => 'v', // non-empty map (array_is_list === false)
                ],
            ],
        ];

        try {
            $p->validatePhaseA($config);
            self::fail('Expected typing rule failure');
        } catch (\RuntimeException $e) {
            self::assertSame(ErrorCodes::CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH, $e->getMessage());
        }
    }
}
