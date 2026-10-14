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

namespace Coretsia\Platform\Cli\Output\Formatter;

use Coretsia\Platform\Cli\Exception\CliOutputFormatException;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CommandOutputBatch;
use Coretsia\Platform\Cli\Output\FormattedOutput;

/**
 * Stateless deterministic tabular renderer with configured, terminal-independent width.
 *
 * @internal
 */
final readonly class TableFormatter
{
    public function __construct(
        private int $maxWidth,
        private AnsiDecorator $ansi,
    ) {
        if ($maxWidth < 40 || $maxWidth > 240) {
            throw CliOutputFormatException::failed();
        }
    }

    public function format(CommandOutputBatch $batch, bool $color): FormattedOutput
    {
        $stdout = [];
        $stderr = [];

        try {
            foreach ($batch->records() as $record) {
                switch ($record['type']) {
                    case 'text':
                        $stdout[] = $record['text'];
                        break;
                    case 'json':
                        $stdout[] = $this->table($record['payload'], $color);
                        break;
                    case 'error':
                        $stderr[] = $this->ansi->decorate(
                            $record['code'] . ': ' . $record['message'],
                            'error',
                            $color,
                            'table',
                        );
                        break;
                    default:
                        throw CliOutputFormatException::failed();
                }
            }

            return new FormattedOutput(
                \rtrim(\implode("\n", $stdout), "\r\n"),
                \rtrim(\implode("\n", $stderr), "\r\n"),
            );
        } catch (\Throwable) {
            throw CliOutputFormatException::failed();
        }
    }

    /** @param array<int|string, mixed> $payload */
    private function table(array $payload, bool $color): string
    {
        if ($payload === []) {
            return $this->grid(['Value'], [['[]']], $color);
        }

        if (\array_is_list($payload)) {
            $columns = null;
            foreach ($payload as $item) {
                if (!\is_array($item) || \array_is_list($item)) {
                    $columns = null;
                    break;
                }

                if ($columns === null) {
                    $columns = \array_keys($item);
                } elseif (\array_keys($item) !== $columns) {
                    $columns = null;
                    break;
                }
            }

            if ($columns !== null && \count($columns) <= \intdiv($this->maxWidth - 1, 4)) {
                $rows = [];
                foreach ($payload as $item) {
                    $rows[] = \array_map(self::cell(...), \array_values($item));
                }

                return $this->grid(\array_map(self::cell(...), $columns), $rows, $color);
            }

            $rows = [];
            foreach ($payload as $index => $item) {
                $rows[] = [(string) $index, self::cell($item)];
            }

            return $this->grid(['Index', 'Value'], $rows, $color);
        }

        $rows = [];
        foreach ($payload as $key => $value) {
            $rows[] = [self::cell((string) $key), self::cell($value)];
        }

        return $this->grid(['Key', 'Value'], $rows, $color);
    }

    private static function cell(mixed $value): string
    {
        $string = \is_string($value) ? $value : PlainFormatter::stableValue($value);

        return \str_replace(
            ["\r\n", "\r", "\n", "\t", "\u{2028}", "\u{2029}"],
            ['\\n', '\\n', '\\n', '\\t', '\\u2028', '\\u2029'],
            $string,
        );
    }

    /**
     * @param list<string> $headings
     * @param list<list<string>> $rows
     */
    private function grid(array $headings, array $rows, bool $color): string
    {
        $count = \count($headings);
        $widths = [];
        $available = $this->maxWidth - ($count * 3 + 1);

        if ($count === 0 || $available < $count) {
            throw CliOutputFormatException::failed();
        }

        $base = \intdiv($available, $count);
        $remainder = $available % $count;

        foreach ($headings as $index => $heading) {
            $widths[$index] = $base + ($index < $remainder ? 1 : 0);
        }

        $rule = '+' . \implode(
            '+',
            \array_map(
                static fn (int $width): string => \str_repeat('-', $width + 2),
                $widths,
            ),
        ) . '+';

        $lines = [$rule, $this->row($headings, $widths, $color, true), $rule];
        foreach ($rows as $cells) {
            $lines[] = $this->row($cells, $widths, $color, false);
        }
        $lines[] = $rule;

        return \implode("\n", $lines);
    }

    /** @param list<string> $cells @param list<int> $widths */
    private function row(array $cells, array $widths, bool $color, bool $heading): string
    {
        $pieces = [];
        foreach ($widths as $index => $width) {
            $content = self::truncate($cells[$index] ?? '', $width);
            $padding = \str_repeat(' ', $width - self::visibleWidth($content));
            $plain = $content . $padding;
            $pieces[] = $heading
                ? $this->ansi->decorate($plain, 'heading', $color, 'table')
                : $plain;
        }

        return '| ' . \implode(' | ', $pieces) . ' |';
    }

    private static function truncate(string $value, int $maxWidth): string
    {
        if (self::visibleWidth($value) <= $maxWidth) {
            return $value;
        }

        $remaining = $maxWidth - 1;
        $out = '';
        if (\preg_match_all('/\X/u', $value, $matches) === false) {
            throw CliOutputFormatException::failed();
        }
        foreach ($matches[0] as $character) {
            $width = self::visibleWidth($character);
            if ($width > $remaining) {
                break;
            }
            $out .= $character;
            $remaining -= $width;
        }

        return $out . '…';
    }

    private static function visibleWidth(string $value): int
    {
        $plain = \preg_replace('/\x1B\\[[0-9;]*m/', '', $value);
        if ($plain === null) {
            throw CliOutputFormatException::failed();
        }

        $count = \preg_match_all('/\X/u', $plain, $matches);
        if ($count === false) {
            throw CliOutputFormatException::failed();
        }

        $width = 0;
        foreach ($matches[0] as $character) {
            if (\preg_match('/\A[\p{Mn}\p{Me}\p{Cf}]+\z/u', $character) === 1) {
                continue;
            }

            $width += \preg_match(
                '/[\x{1100}-\x{115F}\x{2329}-\x{232A}\x{2E80}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{1B000}-\x{1B2FF}\x{1F200}-\x{1F2FF}\x{1F300}-\x{1FAFF}\x{20000}-\x{3FFFD}]|\p{Han}|\p{Hangul}|\p{Hiragana}|\p{Katakana}|\p{Tangut}|\p{Nushu}|\p{Khitan_Small_Script}|\p{Emoji_Presentation}|\p{Regional_Indicator}|\p{Emoji}\x{FE0F}|\x{20E3}/u',
                $character,
            ) === 1 ? 2 : 1;
        }

        return $width;
    }
}
