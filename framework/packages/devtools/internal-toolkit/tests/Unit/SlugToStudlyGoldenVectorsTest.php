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

namespace Coretsia\Devtools\InternalToolkit\Tests\Unit;

use Coretsia\Devtools\InternalToolkit\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SlugToStudlyGoldenVectorsTest extends TestCase
{
    #[DataProvider('provideGoldenVectors')]
    public function testToStudlyGoldenVectors(string $input, string $expected): void
    {
        self::assertSame($expected, Slug::toStudly($input));
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function provideGoldenVectors(): array
    {
        return [
            'psr-7' => ['psr-7', 'Psr7'],
            'oauth2' => ['oauth2', 'Oauth2'],
            'token123' => ['token123', 'Token123'],
            'double--dash' => ['double--dash', 'DoubleDash'],
            'trim' => ['  psr-7  ', 'Psr7'],
            'empty' => ['', ''],
        ];
    }
}
