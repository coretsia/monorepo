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

final class ContextStoreRejectsResourceValuesTest extends TestCase
{
    public function testTopLevelResourceFailsDeterministicallyWithSafeMessage(): void
    {
        $store = new ContextStore();
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            try {
                $store->set(ContextKeys::HOST, $resource);
                self::fail('Expected resource value rejection.');
            } catch (ContextWriteForbiddenException $exception) {
                self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
                self::assertSame(
                    'context-write-forbidden-resource: value at host',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString('Resource id', $exception->getMessage());
            }
        } finally {
            if (\is_resource($resource)) {
                \fclose($resource);
            }
        }

        self::assertFalse($store->has(ContextKeys::HOST));
        self::assertSame([], $store->all());
    }

    public function testNestedResourceFailsDeterministicallyWithSafePathOnly(): void
    {
        $store = new ContextStore();
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            try {
                $store->set(ContextKeys::USER_AGENT, ['metadata' => ['stream' => $resource]]);
                self::fail('Expected nested resource value rejection.');
            } catch (ContextWriteForbiddenException $exception) {
                self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
                self::assertSame(
                    'context-write-forbidden-resource: value at user_agent.metadata.stream',
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString('Resource id', $exception->getMessage());
            }
        } finally {
            if (\is_resource($resource)) {
                \fclose($resource);
            }
        }

        self::assertFalse($store->has(ContextKeys::USER_AGENT));
        self::assertSame([], $store->all());
    }
}
