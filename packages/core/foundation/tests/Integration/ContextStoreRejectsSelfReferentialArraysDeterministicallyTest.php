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

final class ContextStoreRejectsSelfReferentialArraysDeterministicallyTest extends TestCase
{
    public function testSelfReferenceIsRejectedByDepthBudget(): void
    {
        $value = [];
        $value['self'] = &$value;

        try {
            new ContextStore()->set(
                ContextKeys::PATH_TEMPLATE,
                $value,
            );

            self::fail(
                'Expected self-referential context array rejection.',
            );
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(
                'context-write-forbidden-max-depth',
                $exception->reason(),
            );
            self::assertSame(
                'path_template.self.self.self.self'
                . '.self.self.self.self',
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
        }
    }
}
