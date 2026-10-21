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
                        $stdout[] = $this->wrapRecordLines($record['text'], $color, false);
                        break;
                    case 'json':
                        $stdout[] = $this->table($record['payload'], $color, true);
                        break;
                    case 'error':
                        $stderr[] = $this->wrapRecordLines(
                            $record['code'] . ': ' . $record['message'],
                            $color,
                            true,
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

    private function wrapRecordLines(string $value, bool $color, bool $error): string
    {
        $lines = [];

        foreach (\explode("\n", $value) as $line) {
            foreach (self::wrap(self::cell($line), $this->maxWidth) as $fragment) {
                $lines[] = $error
                    ? $this->ansi->decorate($fragment, 'error', $color, 'table')
                    : $fragment;
            }
        }

        return \implode("\n", $lines);
    }

    /** @param array<int|string, mixed> $payload */
    private function table(array $payload, bool $color, bool $catalogRoot = false): string
    {
        if ($payload === []) {
            return $this->grid(['Value'], [['[]']], $color);
        }

        if ($catalogRoot && \array_keys($payload) === ['groups']) {
            $nested = $payload['groups'];

            if (\is_array($nested) && $nested !== []) {
                $sections = [];
                foreach ($nested as $group => $items) {
                    if (!\is_string($group) || !\is_array($items) || !\array_is_list($items)) {
                        $sections = [];
                        break;
                    }

                    $sections[] = $this->sectionLabel($group) . "\n" . $this->table($items, $color);
                }

                if ($sections !== []) {
                    return \implode("\n\n", $sections);
                }
            }
        }

        if (\array_is_list($payload)) {
            return $this->listTable($payload, $color);
        }

        $rows = [];
        $sections = [];

        foreach ($payload as $key => $value) {
            if (\is_array($value) && $value !== []) {
                if ($rows !== []) {
                    $sections[] = $this->grid(['Key', 'Value'], $rows, $color);
                    $rows = [];
                }

                $sections[] = $this->sectionLabel((string) $key) . "\n" . $this->table($value, $color);
                continue;
            }

            $rows[] = [self::cell((string) $key), \is_array($value) ? '[]' : self::cell($value)];
        }

        if ($rows !== []) {
            $sections[] = $this->grid(['Key', 'Value'], $rows, $color);
        }

        return \implode("\n\n", $sections);
    }

    /** @param list<mixed> $values */
    private function listTable(array $values, bool $color): string
    {
        $columns = null;
        $scalarValuesOnly = true;
        $homogeneousScalarMaps = true;

        foreach ($values as $item) {
            if (\is_array($item)) {
                $scalarValuesOnly = false;
            }

            if (!\is_array($item) || $item === [] || \array_is_list($item)) {
                $homogeneousScalarMaps = false;
                continue;
            }

            foreach ($item as $value) {
                if (\is_array($value)) {
                    $homogeneousScalarMaps = false;
                    break;
                }
            }

            if ($columns === null) {
                $columns = \array_keys($item);
            } elseif (\array_keys($item) !== $columns) {
                $homogeneousScalarMaps = false;
            }
        }

        if ($homogeneousScalarMaps && $columns !== null && \count($columns) <= \intdiv($this->maxWidth - 1, 4)) {
            $rows = [];
            foreach ($values as $item) {
                $rows[] = \array_map(self::cell(...), \array_values($item));
            }

            return $this->grid(\array_map(self::cell(...), $columns), $rows, $color);
        }

        if ($scalarValuesOnly) {
            $rows = [];
            foreach ($values as $index => $item) {
                $rows[] = [(string) $index, self::cell($item)];
            }

            return $this->grid(['Index', 'Value'], $rows, $color);
        }

        $sections = [];
        foreach ($values as $index => $item) {
            $sections[] = $this->sectionLabel((string) $index) . "\n" . (
                \is_array($item)
                    ? $this->table($item, $color)
                    : $this->grid(['Value'], [[self::cell($item)]], $color)
            );
        }

        return \implode("\n\n", $sections);
    }

    private function sectionLabel(string $label): string
    {
        return \implode("\n", self::wrap(self::cell($label), $this->maxWidth));
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

        $desired = [];
        foreach ($headings as $index => $heading) {
            $desired[$index] = self::visibleWidth($heading);
            foreach ($rows as $cells) {
                $desired[$index] = \max(
                    $desired[$index],
                    self::visibleWidth($cells[$index] ?? ''),
                );
            }
        }

        $minimum = \min(8, \intdiv($available, $count));
        $widths = \array_fill(0, $count, $minimum);
        $remaining = $available - ($minimum * $count);

        do {
            $changed = false;
            foreach ($widths as $index => $width) {
                if ($remaining === 0 || $width >= $desired[$index]) {
                    continue;
                }

                ++$widths[$index];
                --$remaining;
                $changed = true;
            }
        } while ($changed && $remaining > 0);

        $rule = '+' . \implode(
            '+',
            \array_map(
                static fn (int $width): string => \str_repeat('-', $width + 2),
                $widths,
            ),
        ) . '+';

        $lines = [$rule, ...$this->row($headings, $widths, $color, true), $rule];
        foreach ($rows as $cells) {
            foreach ($this->row($cells, $widths, $color, false) as $line) {
                $lines[] = $line;
            }
        }
        $lines[] = $rule;

        return \implode("\n", $lines);
    }

    /** @param list<string> $cells @param list<int> $widths @return list<string> */
    private function row(array $cells, array $widths, bool $color, bool $heading): array
    {
        $wrapped = [];
        $height = 1;

        foreach ($widths as $index => $width) {
            $wrapped[$index] = self::wrap($cells[$index] ?? '', $width);
            $height = \max($height, \count($wrapped[$index]));
        }

        $lines = [];
        for ($line = 0; $line < $height; ++$line) {
            $pieces = [];
            foreach ($widths as $index => $width) {
                $content = $wrapped[$index][$line] ?? '';
                $padding = \str_repeat(' ', $width - self::visibleWidth($content));
                $plain = $content . $padding;
                $pieces[] = $heading
                    ? $this->ansi->decorate($plain, 'heading', $color, 'table')
                    : $plain;
            }

            $lines[] = '| ' . \implode(' | ', $pieces) . ' |';
        }

        return $lines;
    }

    /** @return list<string> */
    private static function wrap(string $value, int $maxWidth): array
    {
        if (self::visibleWidth($value) <= $maxWidth) {
            return [$value];
        }

        if (\preg_match_all('/\X/u', $value, $matches) === false) {
            throw CliOutputFormatException::failed();
        }

        $lines = [];
        $content = '';
        $width = 0;
        $lastWhitespaceBoundary = null;

        foreach ($matches[0] as $index => $character) {
            $characterWidth = self::visibleWidth($character);
            if ($characterWidth > $maxWidth) {
                throw CliOutputFormatException::failed();
            }

            while ($content !== '' && $width + $characterWidth > $maxWidth) {
                $wrapAtWhitespace = false;
                if ($lastWhitespaceBoundary !== null) {
                    $wordWidth = self::visibleWidth(\substr($content, $lastWhitespaceBoundary));
                    for ($next = $index; $next < \count($matches[0]); ++$next) {
                        if (\preg_match('/\A\p{Zs}\z/u', $matches[0][$next]) === 1) {
                            break;
                        }

                        $wordWidth += self::visibleWidth($matches[0][$next]);
                        if ($wordWidth > $maxWidth) {
                            break;
                        }
                    }

                    $wrapAtWhitespace = $wordWidth <= $maxWidth;
                }

                if ($wrapAtWhitespace) {
                    $lines[] = \substr($content, 0, $lastWhitespaceBoundary);
                    $content = \substr($content, $lastWhitespaceBoundary);
                    $width = self::visibleWidth($content);
                } else {
                    $lines[] = $content;
                    $content = '';
                    $width = 0;
                }

                $lastWhitespaceBoundary = null;
            }

            $content .= $character;
            $width += $characterWidth;

            if (\preg_match('/\A\p{Zs}\z/u', $character) === 1) {
                $lastWhitespaceBoundary = \strlen($content);
            }
        }

        $lines[] = $content;

        return $lines;
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
