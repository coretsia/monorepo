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

namespace Coretsia\Tools\Tests\Unit;

use Coretsia\Tools\Support\MarkdownSectionReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MarkdownSectionReaderTest extends TestCase
{
    public function testSectionReturnsFirstMatchAndSectionsReturnAllMatchesInDocumentOrder(): void
    {
        $markdown = implode("\n", [
            '# Document',
            'Ignored introduction',
            '## Registry',
            'First body',
            '## Other',
            'Ignored other body',
            '## Registry',
            'Second body',
            '# Next chapter',
            'Ignored chapter body',
            '## Registry',
            'Third body',
        ]);

        self::assertSame('First body', MarkdownSectionReader::section($markdown, '## Registry'));
        self::assertSame(
            ['First body', 'Second body', 'Third body'],
            MarkdownSectionReader::sections($markdown, '## Registry'),
        );
    }

    public function testMissingSectionsAndEmptyMatchingBodiesRemainDistinct(): void
    {
        $markdown = "# Document\n## Registry\n## Registry\nFirst nonempty body\n## Registry";

        self::assertNull(MarkdownSectionReader::section($markdown, '## Missing'));
        self::assertSame([], MarkdownSectionReader::sections($markdown, '## Missing'));
        self::assertNull(MarkdownSectionReader::section('', '## Registry'));
        self::assertSame([], MarkdownSectionReader::sections('', '## Registry'));
        self::assertSame('', MarkdownSectionReader::section($markdown, '## Registry'));
        self::assertSame(['', 'First nonempty body', ''], MarkdownSectionReader::sections($markdown, '## Registry'));
        self::assertSame('', MarkdownSectionReader::section('## Registry', '## Registry'));
        self::assertSame([''], MarkdownSectionReader::sections('## Registry', '## Registry'));
    }

    public function testSameAndHigherLevelHeadingsTerminateSectionsButNestedHeadingsArePreserved(): void
    {
        $markdown = implode("\n", [
            '# Document',
            '## Registry',
            'First body',
            '### Nested',
            'Nested body',
            '#### Deep nested',
            'Deep body',
            '',
            '## Other',
            'Not part of the first body',
            '## Registry',
            'Second body',
            '# New chapter',
            'Not part of the second body',
        ]);
        $first = "First body\n### Nested\nNested body\n#### Deep nested\nDeep body\n";

        self::assertSame($first, MarkdownSectionReader::section($markdown, '## Registry'));
        self::assertSame([$first, 'Second body'], MarkdownSectionReader::sections($markdown, '## Registry'));
    }

    #[DataProvider('fenceMarkers')]
    public function testMatchingAndBoundaryHeadingsInsideFencesAreIgnored(
        string $opening,
        string $shorter,
        string $different,
        string $closing,
    ): void {
        $body = [
            'First real body',
            '  ' . $opening . 'markdown',
            '## Registry',
            'Fenced body',
            $shorter,
            '## Registry',
            $different,
            '# Fenced higher-level heading',
            $opening . ' not-a-closing-fence',
            '## Fenced same-level heading',
            '  ' . $closing,
            'After fence',
        ];
        $markdown = implode("\n", [
            '# Document',
            $opening . 'markdown',
            '## Registry',
            'Not a real section',
            $closing,
            '## Registry',
            ...$body,
            '## Registry',
            'Second real body',
        ]);
        $expected = implode("\n", $body);

        self::assertSame($expected, MarkdownSectionReader::section($markdown, '## Registry'));
        self::assertSame([$expected, 'Second real body'], MarkdownSectionReader::sections($markdown, '## Registry'));
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function fenceMarkers(): iterable
    {
        yield 'backticks' => ['````', '```', '~~~~', '`````'];
        yield 'tildes' => ['~~~~', '~~~', '````', '~~~~~'];
    }

    public function testEquivalentLineEndingsProduceIdenticalSectionBodies(): void
    {
        $lf = "## Registry\nFirst body\n\n### Nested\nNested body\n## Registry\nSecond body\n";
        $crlf = str_replace("\n", "\r\n", $lf);
        $cr = str_replace("\n", "\r", $lf);
        $first = "First body\n\n### Nested\nNested body";
        $expected = [$first, "Second body\n"];

        foreach ([$lf, $crlf, $cr] as $markdown) {
            self::assertSame($first, MarkdownSectionReader::section($markdown, '## Registry'));
            self::assertSame($expected, MarkdownSectionReader::sections($markdown, '## Registry'));
        }
    }

    #[DataProvider('invalidHeadings')]
    public function testBothApisPreserveTheExistingInvalidHeadingDiagnostic(string $heading): void
    {
        foreach (['section', 'sections'] as $method) {
            try {
                MarkdownSectionReader::$method('## Registry', $heading);
                self::fail('markdown-section-heading-invalid-not-reported');
            } catch (\RuntimeException $exception) {
                self::assertSame(\RuntimeException::class, $exception::class);
                self::assertSame('markdown-section-heading-invalid', $exception->getMessage());
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidHeadings(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace-only' => [" \t"];
        yield 'missing-marker' => ['Registry'];
        yield 'missing-title' => ['##'];
        yield 'missing-heading-separator' => ['##Registry'];
        yield 'too-many-markers' => ['####### Registry'];
        yield 'multiline-heading' => ["## Registry\nExtra"];
    }

    public function testRepeatedExtractionDoesNotRetainStateBetweenCalls(): void
    {
        $markdown = "## Registry\nFirst\n## Other\nOther body\n## Registry\nSecond";
        $expected = ['First', 'Second'];

        $copy = MarkdownSectionReader::sections($markdown, '## Registry');
        self::assertSame($expected, $copy);
        $copy[0] = 'Changed';

        self::assertSame(['Other body'], MarkdownSectionReader::sections($markdown, '## Other'));
        self::assertSame([], MarkdownSectionReader::sections($markdown, '## Missing'));
        self::assertSame('First', MarkdownSectionReader::section($markdown, '## Registry'));
        self::assertSame($expected, MarkdownSectionReader::sections($markdown, '## Registry'));
        self::assertSame($expected, MarkdownSectionReader::sections($markdown, '## Registry'));
    }
}
