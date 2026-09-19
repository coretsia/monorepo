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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\UlidGenerator;
use PHPUnit\Framework\TestCase;

final class CorrelationIdFormatContractTest extends TestCase
{
    private const string ULID_PATTERN = '/\A[0-9A-HJKMNP-TV-Z]{26}\z/';

    public function testCorrelationIdUsesCanonicalUppercaseUlidFormat(): void
    {
        $generator = new CorrelationIdGenerator(new UlidGenerator());

        for ($i = 0; $i < 10; $i++) {
            $correlationId = $generator->generate();

            self::assertMatchesRegularExpression(self::ULID_PATTERN, $correlationId);
            self::assertSame(26, \strlen($correlationId));
            self::assertSame(\strtoupper($correlationId), $correlationId);
        }
    }

    public function testCanonicalUlidSourceUsesSameFormatContract(): void
    {
        $generator = new UlidGenerator();

        for ($i = 0; $i < 10; $i++) {
            $ulid = $generator->generate();

            self::assertMatchesRegularExpression(self::ULID_PATTERN, $ulid);
            self::assertSame(26, \strlen($ulid));
            self::assertSame(\strtoupper($ulid), $ulid);
        }
    }
}
