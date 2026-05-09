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

final class ContextStoreRejectsNonStringMapKeysTest extends TestCase
{
    public function testNonListArrayWithIntKeyFailsDeterministically(): void
    {
        $store = new ContextStore();

        try {
            $store->set(ContextKeys::HTTP_RESPONSE_FORMAT, [1 => 'json']);
            self::fail('Expected non-string map key rejection.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                'context-write-forbidden-map-key: value at http_response_format',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('json', $exception->getMessage());
        }

        self::assertFalse($store->has(ContextKeys::HTTP_RESPONSE_FORMAT));
        self::assertSame([], $store->all());
    }

    public function testNestedNonListArrayWithIntKeyFailsDeterministically(): void
    {
        $store = new ContextStore();

        try {
            $store->set(ContextKeys::PATH_TEMPLATE, ['route' => [0 => 'first', 2 => 'third']]);
            self::fail('Expected nested non-string map key rejection.');
        } catch (ContextWriteForbiddenException $exception) {
            self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                'context-write-forbidden-map-key: value at path_template.route',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('first', $exception->getMessage());
            self::assertStringNotContainsString('third', $exception->getMessage());
        }

        self::assertFalse($store->has(ContextKeys::PATH_TEMPLATE));
        self::assertSame([], $store->all());
    }

    public function testValidListsRemainAllowed(): void
    {
        $store = new ContextStore();

        $store->set(ContextKeys::USER_AGENT, ['browser', 'version', ['nested', 'list']]);

        self::assertSame(
            ['browser', 'version', ['nested', 'list']],
            $store->get(ContextKeys::USER_AGENT),
        );
    }

    public function testValidStringKeyedMapsRemainAllowed(): void
    {
        $store = new ContextStore();

        $store->set(ContextKeys::PATH_TEMPLATE, [
            'route' => [
                'name' => 'article.show',
                'template' => '/articles/{id}',
            ],
        ]);

        self::assertSame(
            [
                'route' => [
                    'name' => 'article.show',
                    'template' => '/articles/{id}',
                ],
            ],
            $store->get(ContextKeys::PATH_TEMPLATE),
        );
    }
}
