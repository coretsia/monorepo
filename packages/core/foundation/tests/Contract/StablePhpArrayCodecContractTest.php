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

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\Exception\PhpArraySerializationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use PHPUnit\Framework\TestCase;

final class StablePhpArrayCodecContractTest extends TestCase
{
    public function testCanonicalBytesSortMapsRecursivelyAndPreserveListOrder(): void
    {
        $input = [
            'z' => [3, false, null, -7, true, []],
            'a' => ['z' => "\x00\x1F\x7F", 'a' => "quote\" slash\\ dollar\$\r\n\t"],
        ];
        $normalized = [
            'a' => ['a' => "quote\" slash\\ dollar\$\r\n\t", 'z' => "\x00\x1F\x7F"],
            'z' => [3, false, null, -7, true, []],
        ];
        $expected = <<<'PHP'
<?php

return [
    "a" => [
        "a" => "quote\" slash\\ dollar\$\r\n\t",
        "z" => "\x00\x1F\x7F",
    ],
    "z" => [
        3,
        false,
        null,
        -7,
        true,
        [],
    ],
];
PHP;
        $expected .= "\n";
        $dumper = new StablePhpArrayDumper();
        $parser = new StablePhpArrayParser();

        self::assertSame($normalized, JsonLikeNormalizer::normalize($input));
        self::assertSame($expected, $dumper->dump($input));
        self::assertSame($expected, $dumper->dump($normalized));
        self::assertSame($expected, $dumper->dumpNormalized($normalized));
        self::assertSame($expected, StablePhpArrayDumper::dumpStable($input));
        self::assertSame($normalized, $parser->parse($expected));
        self::assertSame($normalized, StablePhpArrayParser::parseStable($expected));
        self::assertSame(
            $normalized,
            $parser->parseBounded($expected, new JsonLikeNormalizationLimits(4, 32, 64, 128)),
        );
        self::assertSame($expected, $dumper->dump($parser->parse($expected)));
    }

    public function testBothParserEntrypointsRoundTripTheSameCanonicalValueDomain(): void
    {
        $dumper = new StablePhpArrayDumper();
        $parser = new StablePhpArrayParser();
        $limits = new JsonLikeNormalizationLimits(8, 64, 128, 512);

        foreach (
            [
                [],
                [\PHP_INT_MIN, \PHP_INT_MAX, 0, -1, true, false, null, '', []],
                ['z' => 'é', 'A' => ['10a' => [[], 'first', 'second'], '2a' => "\x01\xFF"]],
                ['' => '', '01' => 'string-key', 'a' => ['z', 'a', 'z']],
                ["<?php return [];", "\x00\x0B\x0C\x1B\x7F", "\"\\\$\r\n\t"],
            ] as $input
        ) {
            $expected = JsonLikeNormalizer::normalize($input, limits: $limits);
            $bytes = $dumper->dump($input);
            self::assertSame($expected, $parser->parse($bytes));
            self::assertSame($expected, $parser->parseBounded($bytes, $limits));
            self::assertSame($bytes, $dumper->dumpNormalized($parser->parse($bytes)));
            self::assertStringNotContainsString("\r", $bytes);
            self::assertStringEndsWith(";\n", $bytes);
        }
    }

    public function testBothParserEntrypointsRejectNonCanonicalAndExecutableBytes(): void
    {
        $parser = new StablePhpArrayParser();
        $limits = new JsonLikeNormalizationLimits(16, 128, 256, 1024);
        $canonical = "<?php\n\nreturn [\n    \"a\" => 1,\n    \"z\" => 2,\n];\n";
        $invalid = [
            '',
            "<?php\n\nreturn true;\n",
            "<?php return [];\n",
            "<?php\n\nreturn [];\n\n",
            \str_replace("\n", "\r\n", $canonical),
            \str_replace('    ', '  ', $canonical),
            "<?php\n\nreturn [\n    \"z\" => 2,\n    \"a\" => 1,\n];\n",
            "<?php\n\nreturn [\n    \"a\" => 1,\n    \"a\" => 2,\n];\n",
            "<?php\n\nreturn [\n    \"1\" => 1,\n];\n",
            "<?php\n\nreturn [\n    1,\n    \"a\" => 2,\n];\n",
            "<?php\n\nreturn [\n    01,\n];\n",
            "<?php\n\nreturn [\n    -0,\n];\n",
            "<?php\n\nreturn [\n    1.0,\n];\n",
            "<?php\n\nreturn [\n    TRUE,\n];\n",
            "<?php\n\nreturn [\n    1\n];\n",
            "<?php\n\nreturn [\n    '\\n',\n];\n",
            "<?php\n\nreturn [\n    \"\\x0a\",\n];\n",
            "<?php\n\nreturn [\n    \"\\x41\",\n];\n",
            "<?php\n\nreturn [\n    \"\$variable\",\n];\n",
            "<?php\n\nreturn [\n    new \\stdClass(),\n];\n",
            "<?php\n\nreturn [\n    strlen(\"secret\"),\n];\n",
            "<?php\n\nreturn [\n    1, // comment\n];\n",
            $canonical . 'echo "unexpected";',
        ];

        foreach ($invalid as $bytes) {
            self::assertParserRejects(
                static fn (): array => $parser->parse($bytes),
                PhpArraySerializationException::REASON_SERIALIZATION_INVALID,
            );
            self::assertParserRejects(
                static fn (): array => $parser->parseBounded($bytes, $limits),
                PhpArraySerializationException::REASON_SERIALIZATION_INVALID,
            );
        }
    }

    public function testExecutableInputIsNeverEvaluated(): void
    {
        $parser = new StablePhpArrayParser();
        $bytes = "<?php\n\nreturn [\n    print(\"codec-must-not-execute\"),\n];\n";
        \ob_start();

        try {
            self::assertParserRejects(
                static fn (): array => $parser->parse($bytes),
                PhpArraySerializationException::REASON_SERIALIZATION_INVALID,
            );
            self::assertParserRejects(
                static fn (): array => $parser->parseBounded($bytes, new JsonLikeNormalizationLimits(8, 32, 64)),
                PhpArraySerializationException::REASON_SERIALIZATION_INVALID,
            );
        } finally {
            $output = \ob_get_clean();
        }

        self::assertSame('', $output);
    }

    public function testBoundedDecodingMatchesNormalizerAccountingAtExactLimits(): void
    {
        // Root map entry + two list entries + nested map entry = four nodes.
        // Root depth is one; the nested map is depth three.
        // Decoded string bytes: key (2) + escaped value (3) + key (1) + UTF-8 value (2).
        $value = ['ab' => ["\n\x00\$", ['k' => 'é']]];
        $dumper = new StablePhpArrayDumper();
        $parser = new StablePhpArrayParser();
        $bytes = $dumper->dump($value);
        $exact = new JsonLikeNormalizationLimits(3, 4, 3, 8);

        self::assertSame($value, JsonLikeNormalizer::normalize($value, limits: $exact));
        self::assertSame($value, $parser->parseBounded($bytes, $exact));
        self::assertSame($value, $parser->parse($bytes));
        self::assertSame([], $parser->parseBounded($dumper->dump([]), new JsonLikeNormalizationLimits(1, 1, 1, 1)));

        foreach (
            [
                [
                    new JsonLikeNormalizationLimits(2, 4, 3, 8),
                    JsonLikeNormalizationException::REASON_MAX_DEPTH_EXCEEDED,
                    PhpArraySerializationException::REASON_MAX_DEPTH_EXCEEDED,
                ],
                [
                    new JsonLikeNormalizationLimits(3, 3, 3, 8),
                    JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED,
                    PhpArraySerializationException::REASON_MAX_NODES_EXCEEDED,
                ],
                [
                    new JsonLikeNormalizationLimits(3, 4, 2, 8),
                    JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
                    PhpArraySerializationException::REASON_STRING_BYTES_EXCEEDED,
                ],
                [
                    new JsonLikeNormalizationLimits(3, 4, 3, 7),
                    JsonLikeNormalizationException::REASON_TOTAL_STRING_BYTES_EXCEEDED,
                    PhpArraySerializationException::REASON_TOTAL_STRING_BYTES_EXCEEDED,
                ],
            ] as [$limits, $normalizerReason, $parserReason]
        ) {
            try {
                JsonLikeNormalizer::normalize($value, limits: $limits);
                self::fail('Expected normalization budget rejection.');
            } catch (JsonLikeNormalizationException $exception) {
                self::assertSame($normalizerReason, $exception->reason());
            }
            self::assertParserRejects(static fn (): array => $parser->parseBounded($bytes, $limits), $parserReason);
        }

        self::assertSame([[]], $parser->parseBounded($dumper->dump([[]]), new JsonLikeNormalizationLimits(2, 1, 1)));
        self::assertParserRejects(
            static fn (): array => $parser->parseBounded($dumper->dump([[]]), new JsonLikeNormalizationLimits(1, 1, 1)),
            PhpArraySerializationException::REASON_MAX_DEPTH_EXCEEDED,
        );

        // A map key is also subject to the individual and aggregate byte budgets.
        $keyOnly = ['éé' => null];
        $keyBytes = $dumper->dump($keyOnly);
        self::assertSame($keyOnly, $parser->parseBounded($keyBytes, new JsonLikeNormalizationLimits(1, 1, 4, 4)));
        self::assertParserRejects(
            static fn (): array => $parser->parseBounded($keyBytes, new JsonLikeNormalizationLimits(1, 1, 3, 8)),
            PhpArraySerializationException::REASON_STRING_BYTES_EXCEEDED,
        );
        self::assertParserRejects(
            static fn (): array => $parser->parseBounded($keyBytes, new JsonLikeNormalizationLimits(1, 1, 4, 3)),
            PhpArraySerializationException::REASON_TOTAL_STRING_BYTES_EXCEEDED,
        );
    }

    public function testBoundedDecoderRejectsBeforeParsingOverBudgetNestedValues(): void
    {
        $parser = new StablePhpArrayParser();
        $dumper = new StablePhpArrayDumper();
        $value = [0, \array_fill(0, 4096, ['x' => 'value'])];
        $bytes = $dumper->dump($value);
        self::assertSame($value, $parser->parse($bytes));
        self::assertParserRejects(
            static fn (): array => $parser->parseBounded($bytes, new JsonLikeNormalizationLimits(1, 10000, 16)),
            PhpArraySerializationException::REASON_MAX_DEPTH_EXCEEDED,
        );
        self::assertParserRejects(
            static fn (): array => $parser->parseBounded($bytes, new JsonLikeNormalizationLimits(8, 1, 16)),
            PhpArraySerializationException::REASON_MAX_NODES_EXCEEDED,
        );

        // A malformed suffix must not take precedence over an already exceeded budget.
        // Decoding the entire document and normalizing afterwards would fail these assertions.
        $truncated = "<?php\n\nreturn [\n    0,\n    [\n        invalid";
        self::assertParserRejects(
            static fn (): array => $parser->parse($truncated),
            PhpArraySerializationException::REASON_SERIALIZATION_INVALID,
        );
        self::assertParserRejects(
            static fn (): array => $parser->parseBounded($truncated, new JsonLikeNormalizationLimits(1, 100, 16)),
            PhpArraySerializationException::REASON_MAX_DEPTH_EXCEEDED,
        );
        self::assertParserRejects(
            static fn (): array => $parser->parseBounded($truncated, new JsonLikeNormalizationLimits(8, 1, 16)),
            PhpArraySerializationException::REASON_MAX_NODES_EXCEEDED,
        );
        $stringPrefix = "<?php\n\nreturn [\n    \"abcd";
        self::assertParserRejects(
            static fn (): array => $parser->parseBounded($stringPrefix, new JsonLikeNormalizationLimits(8, 8, 3)),
            PhpArraySerializationException::REASON_STRING_BYTES_EXCEEDED,
        );
    }

    /**
     * @param \Closure(): array<int|string, mixed> $parse
     */
    private static function assertParserRejects(\Closure $parse, string $reason): void
    {
        try {
            $parse();
            self::fail('Expected canonical PHP-array decoding failure.');
        } catch (PhpArraySerializationException $exception) {
            self::assertSame(PhpArraySerializationException::ERROR_CODE, $exception->errorCode());
            self::assertSame($reason, $exception->reason());
            self::assertSame(PhpArraySerializationException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
        }
    }
}
