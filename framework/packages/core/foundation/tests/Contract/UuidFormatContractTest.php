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

use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UuidGenerator;
use PHPUnit\Framework\TestCase;

final class UuidFormatContractTest extends TestCase
{
    public function testUuidGeneratorImplementsIdGeneratorInterface(): void
    {
        $generator = new UuidGenerator();

        self::assertInstanceOf(IdGeneratorInterface::class, $generator);
    }

    public function testGeneratedUuidUsesCanonicalLowercaseVersion4Format(): void
    {
        $generator = new UuidGenerator();

        for ($i = 0; $i < 16; $i++) {
            $uuid = $generator->generate();

            self::assertIsString($uuid);
            self::assertMatchesRegularExpression(
                '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
                $uuid,
            );

            self::assertSame(\strtolower($uuid), $uuid);
        }
    }
}
