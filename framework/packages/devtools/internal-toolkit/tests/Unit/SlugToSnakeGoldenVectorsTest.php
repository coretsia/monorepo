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

final class SlugToSnakeGoldenVectorsTest extends TestCase
{
    #[DataProvider('provideGoldenVectors')]
    public function testToSnakeGoldenVectors(string $input, string $expected): void
    {
        self::assertSame($expected, Slug::toSnake($input));
    }

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function provideGoldenVectors(): array
    {
        return [
            'studly simple' => ['CliSpikes', 'cli_spikes'],
            'acronym boundary' => ['JSONEncoder', 'json_encoder'],
            'mixed acronym boundary' => ['CoreDTOAttribute', 'core_dto_attribute'],
            'kebab' => ['cli-spikes', 'cli_spikes'],
            'dot slash space' => ['foo.bar/baz qux', 'foo_bar_baz_qux'],
            'backslash namespace-like' => ['Coretsia\\InternalToolkit', 'coretsia_internal_toolkit'],
            'already snake' => ['already__snake', 'already_snake'],
            'trim' => ['  InternalToolkit  ', 'internal_toolkit'],
            'empty' => ['', ''],
        ];
    }
}
