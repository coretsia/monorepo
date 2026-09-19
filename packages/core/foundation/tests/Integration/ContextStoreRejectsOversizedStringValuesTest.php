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

final class ContextStoreRejectsOversizedStringValuesTest extends TestCase
{
    public function testRejectsLimitPlusOneStringWithoutLeakingIt(): void
    {
        $sentinel = 'CORETSIA_OVERSIZED_CONTEXT_STRING';
        $value = $sentinel . \str_repeat(
            'x',
            4097 - \strlen($sentinel),
        );

        try {
            new ContextStore()->set(
                ContextKeys::PATH_TEMPLATE,
                $value,
            );

            self::fail(
                'Expected oversized context string rejection.',
            );
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(
                'context-write-forbidden-string-bytes',
                $exception->reason(),
            );
            self::assertSame(
                'path_template',
                $exception->safePath(),
            );
            self::assertStringNotContainsString(
                $sentinel,
                $exception->getMessage(),
            );

            $previous = $exception->getPrevious();

            self::assertInstanceOf(
                JsonLikeNormalizationException::class,
                $previous,
            );
            self::assertSame(
                JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
                $previous->reason(),
            );
            self::assertStringNotContainsString(
                $sentinel,
                $previous->getMessage(),
            );
        }
    }

    public function testRejectsLimitPlusOneMapKeyUsingSafePlaceholder(): void
    {
        $prefix = 'Authorization_Bearer_';
        $unsafeKey = $prefix . \str_repeat(
            'x',
            4097 - \strlen($prefix),
        );

        try {
            new ContextStore()->set(
                ContextKeys::PATH_TEMPLATE,
                [
                    $unsafeKey => 'value',
                ],
            );

            self::fail(
                'Expected oversized context map-key rejection.',
            );
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(
                'context-write-forbidden-string-bytes',
                $exception->reason(),
            );
            self::assertSame(
                'path_template[<key>]',
                $exception->safePath(),
            );
            self::assertStringNotContainsString(
                $unsafeKey,
                $exception->getMessage(),
            );

            $previous = $exception->getPrevious();

            self::assertInstanceOf(
                JsonLikeNormalizationException::class,
                $previous,
            );
            self::assertSame(
                JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
                $previous->reason(),
            );
            self::assertSame(
                'path_template[<key>]',
                $previous->path(),
            );
        }
    }
}
