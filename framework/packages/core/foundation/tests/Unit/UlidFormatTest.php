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

use Coretsia\Foundation\Id\UlidGenerator;
use PHPUnit\Framework\TestCase;

final class UlidFormatTest extends TestCase
{
    public function testGeneratedUlidUsesCanonicalUppercaseCrockfordBase32Format(): void
    {
        $generator = new UlidGenerator();

        for ($i = 0; $i < 32; $i++) {
            $ulid = $generator->generate();

            self::assertMatchesRegularExpression(
                '/\A[0-9A-HJKMNP-TV-Z]{26}\z/',
                $ulid,
            );
        }
    }

    public function testGeneratedUlidIsDeterministicFormatString(): void
    {
        $generator = new UlidGenerator();

        $ulid = $generator->generate();

        self::assertIsString($ulid);
        self::assertSame(26, \strlen($ulid));
        self::assertSame(\strtoupper($ulid), $ulid);
    }
}
