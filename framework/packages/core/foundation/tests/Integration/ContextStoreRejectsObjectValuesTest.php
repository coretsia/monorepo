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

use Coretsia\Foundation\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use PHPUnit\Framework\TestCase;

final class ContextStoreRejectsObjectValuesTest extends TestCase
{
    public function testTopLevelObjectFailsDeterministicallyWithSafeMessage(): void
    {
        $store = new ContextStore();

        try {
            $store->set(ContextKeys::USER_AGENT, new \stdClass());
            self::fail('Expected object value rejection.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                'context-write-forbidden-object: value at user_agent',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('stdClass Object', $exception->getMessage());
        }

        self::assertFalse($store->has(ContextKeys::USER_AGENT));
        self::assertSame([], $store->all());
    }

    public function testNestedObjectFailsDeterministicallyWithSafePathOnly(): void
    {
        $store = new ContextStore();

        try {
            $store->set(ContextKeys::PATH_TEMPLATE, ['route' => ['compiled' => new \stdClass()]]);
            self::fail('Expected nested object value rejection.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                'context-write-forbidden-object: value at path_template.route.compiled',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('stdClass Object', $exception->getMessage());
        }

        self::assertFalse($store->has(ContextKeys::PATH_TEMPLATE));
        self::assertSame([], $store->all());
    }

    public function testClosureFailsAsForbiddenClosureWithSafePathOnly(): void
    {
        $store = new ContextStore();

        try {
            $store->set(
                ContextKeys::UOW_TYPE,
                ['callback' => static fn (): string => 'closure-raw-value-sentinel'],
            );

            self::fail('Expected closure value rejection.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                'context-write-forbidden-closure: value at uow_type.callback',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('closure-raw-value-sentinel', $exception->getMessage());
        }

        self::assertFalse($store->has(ContextKeys::UOW_TYPE));
        self::assertSame([], $store->all());
    }
}
