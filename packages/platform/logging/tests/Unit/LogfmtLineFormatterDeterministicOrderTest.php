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

namespace Coretsia\Platform\Logging\Tests\Unit;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class LogfmtLineFormatterDeterministicOrderTest extends TestCase
{
    public function testCanonicalOrderAndNullVersusQuotedStringsAreExact(): void
    {
        $formatted = new LogfmtLineFormatter()->format(self::record());

        self::assertSame(
            'ts_utc=null level="info" message="kernel.boot" service_name="web" app_target="web" correlation_id=null uow_id=null uow_type=null context=null' . "\n",
            $formatted,
        );
        self::assertSame(1, \substr_count($formatted, "\n"));
        self::assertStringNotContainsString('  ', $formatted);
        self::assertTrue(\str_ends_with($formatted, "\n"));

        $withIdentifiers = self::record([
            'ts_utc' => '2026-10-31T12:30:40.123456Z',
            'correlation_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'uow_id' => 'uow:123',
            'uow_type' => 'cli',
        ]);
        $line = new LogfmtLineFormatter()->format($withIdentifiers);
        self::assertStringContainsString('ts_utc="2026-10-31T12:30:40.123456Z"', $line);
        self::assertStringContainsString(
            'correlation_id="01ARZ3NDEKTSV4RRFFQ69G5FAV" uow_id="uow:123" uow_type="cli" context=null',
            $line,
        );
        self::assertSame(1, \substr_count($line, "\n"));
    }

    public function testContextUsesFoundationStableJsonAsOneQuotedField(): void
    {
        $context = [
            'source_paths' => ['src/A.php', 'src/B.php'],
            'moduleIds' => ['core.foundation', 'platform.logging'],
            'groups_count' => 2,
        ];
        $record = self::record(['context' => $context]);
        $reversed = \array_reverse($record, true);
        $reversed['context'] = \array_reverse($context, true);

        $formatter = new LogfmtLineFormatter();
        $line = $formatter->format($record);
        self::assertSame($line, $formatter->format($record));
        self::assertSame($line, $formatter->format($reversed));
        self::assertSame(1, \substr_count($line, "\n"));
        self::assertSame(8, \substr_count($line, ' '));

        $embedded = \substr(StableJsonEncoder::encodeStableMap($context), 0, -1);
        $quote = new ReflectionMethod(LogfmtLineFormatter::class, 'quoted');
        self::assertStringContainsString('context=' . $quote->invoke(null, $embedded), $line);
        self::assertStringContainsString('\\"moduleIds\\":[\\"core.foundation\\",\\"platform.logging\\"]', $line);
        self::assertStringNotContainsString('context=[]', $line);
    }

    public function testEscapingOfQuotesBackslashesAndEveryControlCharacterIsDeterministic(): void
    {
        // The canonical schema rejects control characters in event fields.
        // Exercise the formatter's private escaping primitive without widening that schema.
        $method = new ReflectionMethod(LogfmtLineFormatter::class, 'quoted');
        self::assertSame(
            '"a\\\\b\\"c\\n\\r\\t\\u0000\\u001F\\u007F"',
            $method->invoke(null, "a\\b\"c\n\r\t\x00\x1f\x7f"),
        );

        for ($byte = 0; $byte <= 31; $byte++) {
            $result = $method->invoke(null, 'before' . \chr($byte) . 'after');
            self::assertSame(0, \substr_count($result, "\n"));
            self::assertSame(0, \substr_count($result, "\r"));
            self::assertSame(0, \substr_count($result, "\t"));
            self::assertSame('"', $result[0]);
            self::assertSame('"', \substr($result, -1));
        }
    }

    public function testInvalidRecordsAreRejectedWithoutRawDiagnosticsOrStringableCalls(): void
    {
        $evaluations = 0;
        $stringable = new class($evaluations) implements \Stringable {
            public function __construct(private int &$evaluations)
            {
            }

            public function __toString(): string
            {
                ++$this->evaluations;
                throw new \RuntimeException('raw-private-token');
            }
        };
        $resource = \fopen('php://memory', 'rb');
        self::assertIsResource($resource);

        $cases = [
            ['message' => 'unsafe text'],
            ['app_target' => 'missing'],
            ['service_name' => "raw\nname"],
            ['context' => []],
            ['context' => ['operation' => $stringable]],
            ['context' => ['operation' => $resource]],
            ['context' => ['duration_ms' => 1.5]],
            ['context' => ['unknown_field' => new \stdClass()]],
        ];

        try {
            foreach ($cases as $invalid) {
                self::assertFalse(LogRecordPolicy::isValidCanonicalRecord(self::record($invalid)));
                try {
                    new LogfmtLineFormatter()->format(self::record($invalid));
                    self::fail('Invalid data must not reach the logfmt sink.');
                } catch (\InvalidArgumentException $exception) {
                    self::assertSame('logging-record-invalid', $exception->getMessage());
                    self::assertStringNotContainsString('raw-private-token', $exception->getMessage());
                }
            }
        } finally {
            \fclose($resource);
        }

        self::assertSame(0, $evaluations);
    }

    public function testNoIndependentRecursiveNormalizerOrJsonEncoderIsIntroduced(): void
    {
        $source = \file_get_contents(new \ReflectionClass(LogfmtLineFormatter::class)->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString('StableJsonEncoder::encodeStableMap($record[\'context\'])', $source);
        self::assertStringNotContainsString('json_encode(', $source);
        self::assertStringNotContainsString('JsonLikeNormalizer', $source);
        self::assertStringNotContainsString('__toString(', $source);
        self::assertStringNotContainsString('serialize(', $source);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function record(array $overrides = []): array
    {
        return \array_replace([
            'ts_utc' => null,
            'level' => 'info',
            'message' => 'kernel.boot',
            'service_name' => 'web',
            'app_target' => 'web',
            'correlation_id' => null,
            'uow_id' => null,
            'uow_type' => null,
            'context' => null,
        ], $overrides);
    }
}
