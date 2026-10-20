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
use Coretsia\Platform\Cli\Output\CommandOutputBatch;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use PHPUnit\Framework\TestCase;

final class TableFormatterNestedJsonLikePresentationTest extends TestCase
{
    public function testNestedMapsAreLabeledAndScalarListsUseOriginalIndexedOrder(): void
    {
        $batch = self::batch([
            'z-section' => ['inner' => ['z-last' => 'second', 'a-first' => 'first']],
            'a-items' => ['third-item', 'first-item', 'second-item'],
        ]);
        $text = self::format($batch, 60);

        self::assertStringContainsString('a-items', $text);
        self::assertStringContainsString('z-section', $text);
        self::assertStringContainsString('inner', $text);
        self::assertStringContainsString('a-first', $text);
        self::assertStringContainsString('z-last', $text);
        self::assertStringContainsString('Index', $text);
        self::assertStringContainsString('Value', $text);
        self::assertTrue(\strpos($text, 'a-items') < \strpos($text, 'z-section'));
        self::assertTrue(\strpos($text, 'third-item') < \strpos($text, 'first-item'));
        self::assertTrue(\strpos($text, 'first-item') < \strpos($text, 'second-item'));
        self::assertTrue(\strpos($text, 'a-first') < \strpos($text, 'z-last'));
        self::assertStringNotContainsString('{"inner"', $text);
        self::assertStringNotContainsString('["third-item"', $text);
        self::assertWidth($text, 60);
    }

    public function testHomogeneousScalarMapsRetainTableColumnsRatherThanIndexedSections(): void
    {
        $text = self::format(self::batch([
            'commands' => [
                ['name' => 'config:validate', 'summary' => 'Validate config.'],
                ['name' => 'cache:verify', 'summary' => 'Verify cache.'],
            ],
        ]), 80);

        self::assertStringContainsString('name', $text);
        self::assertStringContainsString('summary', $text);
        self::assertStringContainsString('config:validate', $text);
        self::assertStringContainsString('cache:verify', $text);
        self::assertStringNotContainsString('Index', $text);
        self::assertStringNotContainsString('{"name"', $text);
        self::assertWidth($text, 80);
    }

    public function testHomogeneousMapsContainingCollectionsRenderIndexedStructuredSections(): void
    {
        $text = self::format(self::batch([
            'steps' => [
                ['command' => 'first:step', 'records' => [['type' => 'text', 'text' => 'FIRST_RECORD']]],
                [
                    'command' => 'second:step',
                    'records' => [['type' => 'json', 'data' => ['result' => 'SECOND_RECORD']]],
                ],
            ],
        ]), 55);

        self::assertStringContainsString('steps', $text);
        self::assertStringContainsString('records', $text);
        self::assertStringContainsString('FIRST_RECORD', $text);
        self::assertStringContainsString('SECOND_RECORD', $text);
        self::assertTrue(\strpos($text, 'FIRST_RECORD') < \strpos($text, 'SECOND_RECORD'));
        self::assertStringNotContainsString('{"type"', $text);
        self::assertStringNotContainsString('[{"type"', $text);
        self::assertWidth($text, 55);
    }

    public function testHeterogeneousListsKeepEveryEntryInOriginalOrder(): void
    {
        $text = self::format(self::batch([
            'items' => [
                ['name' => 'FIRST_ENTRY'],
                'SECOND_ENTRY',
                ['data' => ['value' => 'THIRD_ENTRY']],
                ['name' => 'FOURTH_ENTRY', 'extra' => ['nested' => true]],
            ],
        ]), 50);

        foreach (['FIRST_ENTRY', 'SECOND_ENTRY', 'THIRD_ENTRY', 'FOURTH_ENTRY'] as $value) {
            self::assertStringContainsString($value, $text);
        }
        self::assertTrue(\strpos($text, 'FIRST_ENTRY') < \strpos($text, 'SECOND_ENTRY'));
        self::assertTrue(\strpos($text, 'SECOND_ENTRY') < \strpos($text, 'THIRD_ENTRY'));
        self::assertTrue(\strpos($text, 'THIRD_ENTRY') < \strpos($text, 'FOURTH_ENTRY'));
        self::assertStringContainsString('nested', $text);
        self::assertStringNotContainsString('{"data"', $text);
        self::assertWidth($text, 50);
    }

    public function testMixedTextJsonAndErrorWorkflowRecordsRemainReadable(): void
    {
        $payload = [
            'workflow' => 'verify',
            'outcome' => 'failure',
            'exit_code' => 2,
            'steps' => [
                [
                    'index' => 0,
                    'command' => 'config:validate',
                    'exit_code' => 2,
                    'records' => [
                        ['type' => 'text', 'text' => 'CHILD_TEXT'],
                        [
                            'type' => 'json',
                            'data' => ['details' => ['messages' => ['CHILD_JSON_FIRST', 'CHILD_JSON_SECOND']]],
                        ],
                        ['type' => 'error', 'code' => 'CHILD_ERROR', 'message' => 'CHILD_ERROR_MESSAGE'],
                    ],
                ],
            ],
        ];
        $buffer = new CommandOutputBuffer();
        $buffer->text('TOP_LEVEL_TEXT');
        $buffer->json($payload);
        $buffer->error('TOP_LEVEL_ERROR', 'TOP_LEVEL_ERROR_MESSAGE');
        $batch = $buffer->finalize();

        foreach ([40, 60, 120] as $width) {
            $formatter = new TableFormatter($width, new AnsiDecorator());
            $formatted = $formatter->format($batch, false);
            $stdout = $formatted->stdoutBytes();
            $stderr = $formatted->stderrBytes();

            foreach (
                [
                    'TOP_LEVEL_TEXT',
                    'CHILD_TEXT',
                    'CHILD_JSON_FIRST',
                    'CHILD_JSON_SECOND',
                    'CHILD_ERROR_MESSAGE',
                    'records',
                    'steps',
                ] as $value
            ) {
                self::assertStringContainsString($value, $stdout);
            }
            self::assertStringContainsString(
                'TOP_LEVEL_ERROR: TOP_LEVEL_ERROR_MESSAGE',
                \str_replace("\n", '', $stderr),
            );
            self::assertTrue(\strpos($stdout, 'CHILD_JSON_FIRST') < \strpos($stdout, 'CHILD_JSON_SECOND'));
            self::assertStringNotContainsString('{"details"', $stdout);
            self::assertStringNotContainsString('{"type"', $stdout);
            self::assertWidth($stdout, $width);
            self::assertWidth($stderr, $width);
            self::assertSame($stdout, $formatter->format($batch, false)->stdoutBytes());
            self::assertSame($stderr, $formatter->format($batch, false)->stderrBytes());
        }
    }

    public function testEmptyCollectionsRemainVisibleAndNeverInventValues(): void
    {
        $text = self::format(self::batch([
            'empty-list' => [],
            'empty-map' => [],
            'present' => ['nested-empty' => []],
        ]), 45);

        self::assertStringContainsString('empty-list', $text);
        self::assertStringContainsString('empty-map', $text);
        self::assertStringContainsString('nested-empty', $text);
        self::assertGreaterThanOrEqual(3, \substr_count($text, '[]'));
        self::assertWidth($text, 45);
        self::assertStringContainsString('[]', self::format(self::batch([]), 40));
    }

    public function testLongCellsAreWrappedWithoutTruncationAndOutputRemainsByteIdentical(): void
    {
        $longValue = \str_repeat('UNBROKENVALUE', 20);
        $batch = self::batch([
            'items' => [
                ['name' => 'first', 'summary' => $longValue],
                ['name' => 'second', 'summary' => 'short'],
            ],
        ]);

        foreach ([40, 60, 120] as $width) {
            $formatter = new TableFormatter($width, new AnsiDecorator());
            $first = $formatter->format($batch, false);
            $second = $formatter->format($batch, false);
            $colored = $formatter->format($batch, true);
            self::assertSame($first->stdoutBytes(), $second->stdoutBytes());
            self::assertSame($first->stderrBytes(), $second->stderrBytes());
            self::assertSame('', $first->stderrBytes());
            self::assertSame($first->stdoutBytes(), \preg_replace('/\x1B\[[0-9;]*m/', '', $colored->stdoutBytes()));
            self::assertStringNotContainsString("\x1B", $first->stdoutBytes());
            self::assertWidth($first->stdoutBytes(), $width);
            self::assertWidth($colored->stdoutBytes(), $width);
            self::assertStringContainsString($longValue, self::column(\explode("\n", $first->stdoutBytes()), 1));
        }
    }

    /** @param array<string, mixed>|list<mixed> $payload */
    private static function batch(array $payload): CommandOutputBatch
    {
        $buffer = new CommandOutputBuffer();
        $buffer->json($payload);

        return $buffer->finalize();
    }

    private static function format(CommandOutputBatch $batch, int $width): string
    {
        $output = new TableFormatter($width, new AnsiDecorator())->format($batch, false);
        self::assertSame('', $output->stderrBytes());
        self::assertFalse(\str_ends_with($output->stdoutBytes(), "\n"));

        return $output->stdoutBytes();
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
}
