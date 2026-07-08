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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use PHPUnit\Framework\TestCase;

final class UnitOfWorkHandleContractTest extends TestCase
{
    public function testHandleExposesContextWithoutTimingTokens(): void
    {
        $handle = new UnitOfWorkHandle([
            'attributes' => [
                'operation' => 'http',
            ],
            'correlationId' => 'corr-001',
            'type' => 'http',
            'uowId' => 'uow-001',
        ]);

        self::assertSame(
            [
                'attributes',
                'correlationId',
                'type',
                'uowId',
            ],
            \array_keys($handle->context()),
        );

        self::assertArrayNotHasKey('startedAt', $handle->context());
        self::assertArrayNotHasKey('startedAtToken', $handle->context());
        self::assertArrayNotHasKey('finishedAt', $handle->context());
    }

    public function testHandleRejectsContextWithStartedAt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => 'corr-001',
            'startedAt' => 123,
            'type' => 'http',
            'uowId' => 'uow-001',
        ]);
    }

    public function testHandleRejectsContextWithStartedAtToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => 'corr-001',
            'startedAtToken' => 123,
            'type' => 'http',
            'uowId' => 'uow-001',
        ]);
    }

    public function testHandleRejectsContextWithFinishedAt(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => 'corr-001',
            'finishedAt' => 123,
            'type' => 'http',
            'uowId' => 'uow-001',
        ]);
    }

    public function testHandleRejectsMissingRequiredContextKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => 'corr-001',
            'type' => 'http',
        ]);
    }

    public function testHandleRejectsUnknownContextKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => 'corr-001',
            'debug' => true,
            'type' => 'http',
            'uowId' => 'uow-001',
        ]);
    }
}
