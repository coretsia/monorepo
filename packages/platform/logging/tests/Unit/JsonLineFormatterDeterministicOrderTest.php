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
use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use PHPUnit\Framework\TestCase;

final class JsonLineFormatterDeterministicOrderTest extends TestCase
{
    public function testCanonicalRootAndNestedMapsUseByteOrderAndPreserveListOrder(): void
    {
        $record = self::record([
            'context' => [
                'source_paths' => ['src/A.php', 'src/B.php'],
                'moduleIds' => ['core.foundation', 'platform.logging', 'platform.redaction'],
                'groups_count' => 2,
                'artifact_path' => 'var/cache/config.php',
            ],
        ]);
        $formatter = new JsonLineFormatter();
        $encoded = $formatter->format($record);

        self::assertSame(StableJsonEncoder::encodeStableMap($record), $encoded);
        self::assertSame(
            '{"app_target":"web","context":{"artifact_path":"var/cache/config.php","groups_count":2,"moduleIds":["core.foundation","platform.logging","platform.redaction"],"source_paths":["src/A.php","src/B.php"]},"correlation_id":null,"level":"info","message":"kernel.boot","service_name":"web","ts_utc":null,"uow_id":null,"uow_type":null}' . "\n",
            $encoded,
        );
        self::assertSame(1, \substr_count($encoded, "\n"));
        self::assertTrue(\str_ends_with($encoded, "\n"));
        self::assertSame('{', $encoded[0]);
        self::assertSame('}', $encoded[\strlen($encoded) - 2]);

        $decoded = \json_decode($encoded, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['src/A.php', 'src/B.php'], $decoded['context']['source_paths']);
        self::assertSame(
            ['core.foundation', 'platform.logging', 'platform.redaction'],
            $decoded['context']['moduleIds'],
        );
        self::assertSame(
            [
                'app_target',
                'context',
                'correlation_id',
                'level',
                'message',
                'service_name',
                'ts_utc',
                'uow_id',
                'uow_type',
            ],
            \array_keys($decoded),
        );
        self::assertSame(
            ['artifact_path', 'groups_count', 'moduleIds', 'source_paths'],
            \array_keys($decoded['context'])
        );
    }

    public function testMapInsertionOrderDoesNotChangeEncodedBytes(): void
    {
        $record = self::record([
            'context' => [
                'services_count' => 3,
                'moduleIds' => ['core.kernel', 'platform.cli'],
                'groups_count' => 1,
            ],
        ]);
        $reversed = \array_reverse($record, true);
        $reversed['context'] = \array_reverse($record['context'], true);

        self::assertTrue(LogRecordPolicy::isValidCanonicalRecord($record));
        self::assertTrue(LogRecordPolicy::isValidCanonicalRecord($reversed));
        self::assertSame(new JsonLineFormatter()->format($record), new JsonLineFormatter()->format($reversed));
        self::assertSame(StableJsonEncoder::encodeStableMap($reversed), new JsonLineFormatter()->format($record));
    }

    public function testFoundationRecursivelySortsMapsWithoutReorderingNestedLists(): void
    {
        // Canonical logging lists are already sorted by LogRecordPolicy.
        // Exercise the delegated Foundation serializer with an intentionally
        // unsorted list to distinguish list order from recursive map sorting.
        $value = [
            'zeta' => [
                'nested' => ['z' => 3, 'a' => 2, 'A' => 1],
                'items' => ['z', 'a', 'm'],
            ],
            'alpha' => 0,
        ];

        self::assertSame(
            '{"alpha":0,"zeta":{"items":["z","a","m"],"nested":{"A":1,"a":2,"z":3}}}' . "\n",
            StableJsonEncoder::encodeStableMap($value),
        );
    }

    public function testNullContextIsNotAnEmptyJsonArrayAndNonEmptyContextIsObject(): void
    {
        $formatter = new JsonLineFormatter();
        $empty = $formatter->format(self::record());
        self::assertStringContainsString('"context":null', $empty);
        self::assertStringNotContainsString('"context":[]', $empty);
        self::assertSame(null, \json_decode($empty, true, 512, \JSON_THROW_ON_ERROR)['context']);

        $populated = $formatter->format(self::record(['context' => ['operation' => 'config:compile']]));
        self::assertStringContainsString('"context":{"operation":"config:compile"}', $populated);
        self::assertSame(
            'config:compile',
            \json_decode($populated, true, 512, \JSON_THROW_ON_ERROR)['context']['operation'],
        );
        self::assertSame(1, \substr_count($populated, "\n"));
    }

    public function testInvalidRootAndUnsafePhpTypesAreNeverSerialized(): void
    {
        $formatter = new JsonLineFormatter();
        $invalid = [
            ['service_name' => 'invalid.name'],
            ['app_target' => 'unselected'],
            ['message' => 'unsafe message'],
            ['context' => []],
            ['context' => ['unknown' => 'value']],
            ['context' => ['duration_ms' => 2.5]],
            ['context' => ['operation' => new \stdClass()]],
            [
                'context' => [
                    'operation' => new class() implements \Stringable {
                        public function __toString(): string
                        {
                            throw new \RuntimeException('must-not-be-evaluated');
                        }
                    },
                ],
            ],
        ];
        $handle = \fopen('php://memory', 'rb');
        self::assertIsResource($handle);
        $invalid[] = ['context' => ['operation' => $handle]];

        try {
            foreach ($invalid as $change) {
                try {
                    $formatter->format(self::record($change));
                    self::fail('Malformed canonical record must not be encoded.');
                } catch (\InvalidArgumentException $exception) {
                    self::assertSame('logging-record-invalid', $exception->getMessage());
                }
            }
        } finally {
            \fclose($handle);
        }
    }

    public function testEmbeddedLineFeedsCannotCreateAdditionalPhysicalRecords(): void
    {
        $formatter = new JsonLineFormatter();
        $record = self::record(['context' => ['operation' => "unsafe\noperation"]]);

        self::assertFalse(LogRecordPolicy::isValidCanonicalRecord($record));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('logging-record-invalid');
        $formatter->format($record);
    }

    public function testCanonicalServiceIdentityIsRequiredAndParticipatesInValidation(): void
    {
        $formatter = new JsonLineFormatter();
        $result = \json_decode($formatter->format(self::record()), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('web', $result['service_name']);
        self::assertSame('web', $result['app_target']);
        self::assertNull($result['correlation_id']);
        self::assertNull($result['uow_id']);
        self::assertNull($result['uow_type']);

        foreach (['service_name', 'app_target'] as $key) {
            $missing = self::record();
            unset($missing[$key]);
            self::assertFalse(LogRecordPolicy::isValidCanonicalRecord($missing));
            try {
                $formatter->format($missing);
                self::fail('Canonical service identity must be present.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('logging-record-invalid', $exception->getMessage());
            }
        }

        // A nested, non-authoritative caller field cannot replace the root identity.
        $record = self::record(['context' => ['app_target' => 'api']]);
        $decoded = \json_decode($formatter->format($record), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('web', $decoded['app_target']);
        self::assertSame('web', $decoded['service_name']);
        self::assertSame('api', $decoded['context']['app_target']);
    }

    public function testFormatterDelegatesSerializationToFoundationWithoutAnotherEncoder(): void
    {
        $source = \file_get_contents(new \ReflectionClass(JsonLineFormatter::class)->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString('StableJsonEncoder::encodeStableMap($record)', $source);
        self::assertStringNotContainsString('json_encode(', $source);
        self::assertStringNotContainsString('JsonLikeNormalizer', $source);
        self::assertStringNotContainsString('(object)', $source);
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
