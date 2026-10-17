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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use PHPUnit\Framework\TestCase;

final class TableFormatterHonorsConfiguredMaxWidthTest extends TestCase
{
    public function testGroupedCommandsRemainReadableAndWithinConfiguredWidth(): void
    {
        $longName = 'external:' . \str_repeat('very-long-command-', 5);
        $longSummary = \str_repeat('COMPLETE-SUMMARY-PART-', 9);
        $group = \str_repeat('long-group-', 7);
        $buffer = new CommandOutputBuffer();
        $buffer->json([
            'groups' => [
                $group => [
                    ['name' => $longName, 'summary' => $longSummary],
                    ['name' => 'external:second', 'summary' => 'Second description.'],
                ],
                'utilities' => [
                    ['name' => 'doctor', 'summary' => 'Safe diagnostics.'],
                ],
            ],
        ]);
        $batch = $buffer->finalize();
        foreach ([40, 60, 120] as $maxWidth) {
            $formatter = new TableFormatter($maxWidth, new AnsiDecorator());
            $plain = $formatter->format($batch, false);
            $colored = $formatter->format($batch, true);
            self::assertSame('', $plain->stderrBytes());
            self::assertStringNotContainsString("\x1B", $plain->stdoutBytes());
            self::assertFalse(\str_ends_with($plain->stdoutBytes(), "\n"));
            self::assertSame($plain->stdoutBytes(), $formatter->format($batch, false)->stdoutBytes());
            self::assertWidth($plain->stdoutBytes(), $maxWidth);
            self::assertWidth($colored->stdoutBytes(), $maxWidth);
            $plainLines = \preg_split('/\n/', $plain->stdoutBytes());
            $colorStripped = \preg_replace('/\x1B\[[0-9;]*m/', '', $colored->stdoutBytes());
            self::assertSame($plain->stdoutBytes(), $colorStripped);
            self::assertStringContainsString($group, self::groupLabels($plainLines));
            self::assertStringContainsString($longName, self::column($plainLines, 0));
            self::assertStringContainsString($longSummary, self::column($plainLines, 1));
            self::assertStringContainsString('external:second', self::column($plainLines, 0));
            self::assertStringContainsString('doctor', self::column($plainLines, 0));
        }
    }

    public function testCommandsListIsRenderedAsRowsNotSerializedJson(): void
    {
        $buffer = new CommandOutputBuffer();
        $buffer->json([
            'commands' => [
                ['name' => 'cache:verify', 'summary' => 'Check cache state.'],
                ['name' => 'worker:start', 'summary' => 'Run a worker process.'],
            ],
        ]);
        $output = new TableFormatter(50, new AnsiDecorator())->format($buffer->finalize(), false);
        self::assertWidth($output->stdoutBytes(), 50);
        self::assertStringContainsString('cache:verify', self::column(\explode("\n", $output->stdoutBytes()), 0));
        self::assertStringContainsString('worker:start', self::column(\explode("\n", $output->stdoutBytes()), 0));
        self::assertStringNotContainsString('{"name"', $output->stdoutBytes());
    }

    public function testUnicodeWideCharactersUseVisibleColumnsWithoutLosingContent(): void
    {
        $buffer = new CommandOutputBuffer();
        $buffer->json([
            'groups' => [
                '表示名' => [
                    ['name' => '工作:長い仕事', 'summary' => \str_repeat('漢字', 12)],
                ],
            ],
        ]);

        $output = new TableFormatter(40, new AnsiDecorator())->format($buffer->finalize(), false);
        $text = $output->stdoutBytes();

        self::assertStringContainsString('表示名', $text);
        self::assertStringContainsString('工作:長い仕事', $text);
        self::assertStringContainsString('漢字漢字漢字漢字漢字', $text);

        foreach (\explode("\n", $text) as $line) {
            $count = \preg_match_all('/\X/u', $line, $graphemes);
            self::assertNotFalse($count);

            $visibleWidth = 0;
            foreach ($graphemes[0] as $grapheme) {
                $visibleWidth += \preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $grapheme) === 1 ? 2 : 1;
            }

            self::assertLessThanOrEqual(40, $visibleWidth, $line);
        }
    }

    public function testTextAndErrorRecordsRespectPhysicalWidth(): void
    {
        $buffer = new CommandOutputBuffer();
        $buffer->text(\str_repeat('A', 90) . "\n" . \str_repeat('B', 90));
        $buffer->error('SAFE', \str_repeat('C', 90));
        $batch = $buffer->finalize();

        foreach ([false, true] as $color) {
            $output = new TableFormatter(40, new AnsiDecorator())->format($batch, $color);

            self::assertWidth($output->stdoutBytes(), 40);
            self::assertWidth($output->stderrBytes(), 40);
            self::assertSame(
                \str_repeat('A', 90) . \str_repeat('B', 90),
                \str_replace("\n", '', $output->stdoutBytes()),
            );

            $withoutAnsi = \preg_replace('/\x1B\[[0-9;]*m/', '', $output->stderrBytes());
            self::assertSame(
                'SAFE: ' . \str_repeat('C', 90),
                \str_replace("\n", '', $withoutAnsi),
            );
        }
    }

    private static function assertWidth(string $text, int $maximum): void
    {
        foreach (\explode("\n", $text) as $line) {
            $withoutAnsi = \preg_replace('/\x1B\[[0-9;]*m/', '', $line);
            self::assertNotNull($withoutAnsi);
            self::assertLessThanOrEqual($maximum, \strlen($withoutAnsi), $line);
        }
    }

    /** @param list<string> $lines */
    private static function column(array $lines, int $index): string
    {
        $fragments = [];
        foreach ($lines as $line) {
            if (!\str_starts_with($line, '|')) {
                continue;
            }
            $cells = \explode('|', \trim($line, '|'));
            $fragments[] = \trim($cells[$index] ?? '');
        }
        return \implode('', $fragments);
    }

    /** @param list<string> $lines */
    private static function groupLabels(array $lines): string
    {
        $labels = [];
        foreach ($lines as $line) {
            if (!\str_starts_with($line, '|') && !\str_starts_with($line, '+')) {
                $labels[] = \trim($line);
            }
        }
        return \implode('', $labels);
    }
}
