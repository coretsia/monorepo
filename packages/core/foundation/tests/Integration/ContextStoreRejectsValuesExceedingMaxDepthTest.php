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

final class ContextStoreRejectsValuesExceedingMaxDepthTest extends TestCase
{
    public function testRejectsListDepthAboveCanonicalLimit(): void
    {
        self::assertDepthRejected(
            value: self::nestedList(9),
            expectedPath: 'path_template[0][0][0][0][0][0][0][0]',
        );
    }

    public function testRejectsMapDepthAboveCanonicalLimit(): void
    {
        self::assertDepthRejected(
            value: self::nestedMap(9),
            expectedPath: 'path_template.level.level.level.level'
            . '.level.level.level.level',
        );
    }

    private static function assertDepthRejected(
        array $value,
        string $expectedPath,
    ): void {
        try {
            new ContextStore()->set(
                ContextKeys::PATH_TEMPLATE,
                $value,
            );

            self::fail(
                'Expected context value depth rejection.',
            );
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(
                'context-write-forbidden-max-depth',
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
                JsonLikeNormalizationException::REASON_MAX_DEPTH_EXCEEDED,
                $previous->reason(),
            );
            self::assertSame(
                $expectedPath,
                $previous->path(),
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

    private static function nestedMap(int $depth): array
    {
        $value = 'leaf';

        for ($i = 0; $i < $depth; $i++) {
            $value = [
                'level' => $value,
            ];
        }

        return $value;
    }
}
