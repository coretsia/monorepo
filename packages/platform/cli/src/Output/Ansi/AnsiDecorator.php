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

namespace Coretsia\Platform\Cli\Output\Ansi;

use Coretsia\Platform\Cli\Exception\CliOutputFormatException;

/**
 * Fixed, formatter-owned ANSI semantics. Commands cannot supply escape codes.
 *
 * @internal
 */
final class AnsiDecorator
{
    /** @var array<string, string> */
    private const array CODES = [
        'heading' => '1',
        'success' => '32',
        'warning' => '33',
        'error' => '31',
        'muted' => '2',
    ];

    public function decorate(string $text, string $role, bool $color, string $format): string
    {
        if (
            !\array_key_exists($role, self::CODES)
            || !\in_array($format, ['json', 'table', 'plain'], true)
            || \preg_match(
                '/[\x{0000}-\x{0008}\x{000B}-\x{001F}\x{007F}-\x{009F}]/u',
                $text,
            ) !== 0
        ) {
            throw CliOutputFormatException::failed();
        }

        if (!$color || $format === 'json' || $text === '') {
            return $text;
        }

        return "\x1B[" . self::CODES[$role] . 'm' . $text . "\x1B[0m";
    }
}
