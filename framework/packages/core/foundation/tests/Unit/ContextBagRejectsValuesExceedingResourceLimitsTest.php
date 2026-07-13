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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextBag;
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use PHPUnit\Framework\TestCase;

final class ContextBagRejectsValuesExceedingResourceLimitsTest extends TestCase
{
    public function testDirectConstructionRejectsExcessiveDepth(): void
    {
        self::assertBagRejects(
            value: self::nestedList(9),
            expectedReason: 'context-write-forbidden-max-depth',
        );
    }

    public function testDirectConstructionRejectsExcessiveNodeCount(): void
    {
        self::assertBagRejects(
            value: \array_fill(0, 257, 'value'),
            expectedReason: 'context-write-forbidden-max-nodes',
        );
    }

    public function testDirectConstructionRejectsOversizedString(): void
    {
        self::assertBagRejects(
            value: \str_repeat('x', 4097),
            expectedReason: 'context-write-forbidden-string-bytes',
        );
    }

    private static function assertBagRejects(
        mixed $value,
        string $expectedReason,
    ): void {
        try {
            new ContextBag([
                ContextKeys::PATH_TEMPLATE => $value,
            ]);

            self::fail(
                'Expected direct ContextBag construction rejection.',
            );
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(
                $expectedReason,
                $exception->reason(),
            );
        }
    }

    private static function nestedList(int $depth): array
    {
        $value = 'leaf';

        for ($i = 0; $i < $depth; $i++) {
            $value = [$value];
        }

        return $value;
    }
}
