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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StablePhpArrayParserContractTest extends TestCase
{
    public function testRoundTripsCanonicalSerializationDomain(): void
    {
        $input = [
            'zeta' => [
                'nested-z' => 'last',
                'nested-a' => 'first',
            ],
            'alpha' => [
                [],
                null,
                true,
                false,
                0,
                1,
                -1,
                PHP_INT_MAX,
                PHP_INT_MIN,
                '',
                'ascii',
                'Привіт',
                "\\\"$\n\r\t\0\x01\x1F\x7F",
            ],
        ];

        $bytes = new StablePhpArrayDumper()->dump($input);
        $decoded = new StablePhpArrayParser()->parse($bytes);

        self::assertSame(['alpha', 'zeta'], \array_keys($decoded));
        self::assertSame(
            [
                [],
                null,
                true,
                false,
                0,
                1,
                -1,
                PHP_INT_MAX,
                PHP_INT_MIN,
                '',
                'ascii',
                'Привіт',
                "\\\"$\n\r\t\0\x01\x1F\x7F",
            ],
            $decoded['alpha'],
        );
        self::assertSame(
            ['nested-a' => 'first', 'nested-z' => 'last'],
            $decoded['zeta'],
        );
    }

    public function testAcceptsEveryCanonicalControlByteEncodingEmittedByDumper(): void
    {
        $value = '';

        for ($byte = 0; $byte < 0x20; ++$byte) {
            $value .= \chr($byte);
        }

        $value .= \chr(0x7F);

        $bytes = new StablePhpArrayDumper()->dump(['value' => $value]);
        $decoded = new StablePhpArrayParser()->parse($bytes);

        self::assertSame($value, $decoded['value']);
    }

    #[DataProvider('invalidSerializationProvider')]
    public function testRejectsNonCanonicalSerialization(string $bytes): void
    {
        try {
            new StablePhpArrayParser()->parse($bytes);

            self::fail('Expected non-canonical artifact serialization rejection.');
        } catch (ArtifactInvalidException $exception) {
            self::assertSame(
                ArtifactInvalidException::REASON_SERIALIZATION_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                ArtifactInvalidException::ERROR_CODE
                . ': '
                . ArtifactInvalidException::REASON_SERIALIZATION_INVALID,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString($bytes, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0:string}>
     */
    public static function invalidSerializationProvider(): iterable
    {
        $canonical = self::document('[]');
        $overflow = (string)PHP_INT_MAX . '0';

        yield 'missing open tag' => ["return [];\n"];
        yield 'utf8 bom' => ["\xEF\xBB\xBF" . $canonical];
        yield 'declare before return' => ["<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n"];
        yield 'comment before return' => ["<?php\n\n/* comment */\nreturn [];\n"];
        yield 'missing return' => ["<?php\n\n[];\n"];
        yield 'missing semicolon' => ["<?php\n\nreturn []\n"];
        yield 'missing final lf' => [\substr($canonical, 0, -1)];
        yield 'trailing bytes' => [$canonical . "\n"];
        yield 'crlf exact source' => [\str_replace("\n", "\r\n", $canonical)];
        yield 'single quoted string' => [self::document("[\n    'value',\n]")];
        yield 'array keyword' => [self::document('array()')];
        yield 'float' => [self::document("[\n    1.5,\n]")];
        yield 'scientific notation' => [self::document("[\n    1e3,\n]")];
        yield 'integer plus sign' => [self::document("[\n    +1,\n]")];
        yield 'integer leading zero' => [self::document("[\n    01,\n]")];
        yield 'negative zero' => [self::document("[\n    -0,\n]")];
        yield 'integer overflow' => [self::document("[\n    {$overflow},\n]")];
        yield 'invalid string escape' => [self::document("[\n    \"\\q\",\n]")];
        yield 'lowercase hex escape' => [self::document("[\n    \"\\x0f\",\n]")];
        yield 'non-control hex escape' => [self::document("[\n    \"\\x41\",\n]")];
        yield 'non-canonical tab hex escape' => [self::document("[\n    \"\\x09\",\n]")];
        yield 'raw control byte' => [self::document("[\n    \"a\x01b\",\n]")];
        yield 'raw dollar byte' => [self::document("[\n    \"a\$b\",\n]")];
        yield 'variable' => [self::document("[\n    \$value,\n]")];
        yield 'function call' => [self::document("[\n    strlen(\"x\"),\n]")];
        yield 'include expression' => [self::document("[\n    include \"x.php\",\n]")];
        yield 'require expression' => [self::document("[\n    require \"x.php\",\n]")];
        yield 'new expression' => [self::document("[\n    new stdClass(),\n]")];
        yield 'closure' => [self::document("[\n    function () {},\n]")];
        yield 'arrow function' => [self::document("[\n    fn () => 1,\n]")];
        yield 'constant' => [self::document("[\n    PHP_VERSION,\n]")];
        yield 'magic constant' => [self::document("[\n    __DIR__,\n]")];
        yield 'concatenation' => [self::document("[\n    \"a\" . \"b\",\n]")];
        yield 'arithmetic expression' => [self::document("[\n    1 + 1,\n]")];
        yield 'numeric map key' => [self::document("[\n    \"1\" => \"value\",\n]")];
        yield 'mixed list and map' => [self::document("[\n    \"value\",\n    \"key\" => \"value\",\n]")];
        yield 'unsorted map' => [self::document("[\n    \"z\" => 1,\n    \"a\" => 2,\n]")];
        yield 'duplicate map key' => [self::document("[\n    \"a\" => 1,\n    \"a\" => 2,\n]")];
        yield 'non-canonical indentation' => [self::document("[\n  \"value\",\n]")];
        yield 'missing trailing comma' => [self::document("[\n    \"value\"\n]")];
        yield 'trailing php statement' => ["<?php\n\nreturn [];\nfile_put_contents('/tmp/x', 'x');\n"];
    }

    private static function document(string $value): string
    {
        return "<?php\n\nreturn {$value};\n";
    }
}
