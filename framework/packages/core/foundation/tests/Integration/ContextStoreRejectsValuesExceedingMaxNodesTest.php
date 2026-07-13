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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use PHPUnit\Framework\TestCase;

final class ContextStoreRejectsValuesExceedingMaxNodesTest extends TestCase
{
    public function testListItemsConsumeNodeBudget(): void
    {
        self::assertNodeBudgetRejected(
            value: \array_fill(0, 257, 'value'),
            expectedPath: 'path_template[256]',
        );
    }

    public function testMapEntriesConsumeNodeBudget(): void
    {
        $value = [];

        for ($i = 0; $i < 257; $i++) {
            $value[\sprintf('node_%03d', $i)] = 'value';
        }

        self::assertNodeBudgetRejected(
            value: $value,
            expectedPath: 'path_template',
        );
    }

    public function testNestedScalarValuesConsumeNodeBudget(): void
    {
        $value = \array_fill(0, 255, 'value');
        $value[] = ['nested'];

        self::assertNodeBudgetRejected(
            value: $value,
            expectedPath: 'path_template[255][0]',
        );
    }

    private static function assertNodeBudgetRejected(
        array $value,
        string $expectedPath,
    ): void {
        try {
            new ContextStore()->set(
                ContextKeys::PATH_TEMPLATE,
                $value,
            );

            self::fail(
                'Expected context value node-budget rejection.',
            );
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(
                'context-write-forbidden-max-nodes',
                $exception->reason(),
            );
            self::assertSame(
                $expectedPath,
                $exception->safePath(),
            );

            $previous = $exception->getPrevious();

            self::assertInstanceOf(
                JsonLikeNormalizationException::class,
                $previous,
            );
            self::assertSame(
                JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED,
                $previous->reason(),
            );
            self::assertSame(
                $expectedPath,
                $previous->path(),
            );
        }
    }
}
