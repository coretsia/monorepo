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

use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayParser;
use PHPUnit\Framework\TestCase;

final class StablePhpArrayDumperDeterministicEmissionContractTest extends TestCase
{
    public function testEmitsLfOnlyPhpWithSingleFinalNewline(): void
    {
        $bytes = self::dumper()->dumpEnvelope(self::canonicalEnvelope());

        self::assertStringStartsWith("<?php\n\nreturn [", $bytes);
        self::assertStringNotContainsString("\r", $bytes);
        self::assertStringEndsWith("\n", $bytes);
        self::assertFalse(
            \str_ends_with($bytes, "\n\n"),
            'Stable PHP artifact bytes must end with exactly one final LF.',
        );
    }

    public function testPreservesCanonicalEnvelopeTopLevelShapeWithoutWrapper(): void
    {
        $envelope = self::parseArtifactBytes(self::dumper()->dumpEnvelope([
            'payload' => [
                'status' => 'ok',
            ],
            '_meta' => [
                'schemaVersion' => 1,
                'name' => 'config',
                'fingerprint' => 'abc123',
                'generator' => 'coretsia/core-kernel',
            ],
        ]));

        self::assertSame(['_meta', 'payload'], \array_keys($envelope));
        self::assertArrayHasKey('name', $envelope['_meta']);
        self::assertArrayHasKey('status', $envelope['payload']);
        self::assertArrayNotHasKey('artifact', $envelope);
        self::assertArrayNotHasKey('envelope', $envelope);
    }

    public function testPreservesListOrder(): void
    {
        $envelope = self::parseArtifactBytes(self::dumper()->dumpEnvelope([
            '_meta' => [
                'name' => 'config',
                'schemaVersion' => 1,
                'fingerprint' => 'abc123',
                'generator' => 'coretsia/core-kernel',
            ],
            'payload' => [
                'items' => [
                    3,
                    1,
                    2,
                    'kept-in-list-order',
                ],
            ],
        ]));

        self::assertSame(
            [
                3,
                1,
                2,
                'kept-in-list-order',
            ],
            $envelope['payload']['items'],
        );
    }

    public function testPreservesNormalizedMapOrder(): void
    {
        $envelope = self::parseArtifactBytes(self::dumper()->dumpEnvelope([
            '_meta' => [
                'schemaVersion' => 1,
                'name' => 'config',
                'fingerprint' => 'abc123',
                'generator' => 'coretsia/core-kernel',
            ],
            'payload' => [
                'map' => [
                    'zeta' => 'last',
                    'alpha' => 'first',
                    'middle' => [
                        'z' => 'nested-last',
                        'a' => 'nested-first',
                    ],
                ],
            ],
        ]));

        self::assertSame(['alpha', 'middle', 'zeta'], \array_keys($envelope['payload']['map']));
        self::assertSame(['a', 'z'], \array_keys($envelope['payload']['map']['middle']));
    }

    public function testEmitsStableBytesOnRepeatedRuns(): void
    {
        $envelope = self::canonicalEnvelope();

        $first = self::dumper()->dumpEnvelope($envelope);
        $second = self::dumper()->dumpEnvelope($envelope);
        $third = StablePhpArrayDumper::dumpStableEnvelope($envelope);

        self::assertSame($first, $second);
        self::assertSame($first, $third);
    }

    private static function dumper(): StablePhpArrayDumper
    {
        return new StablePhpArrayDumper(new PayloadNormalizer());
    }

    /**
     * @return array<string, mixed>
     */
    private static function canonicalEnvelope(): array
    {
        return [
            '_meta' => [
                'name' => 'config',
                'schemaVersion' => 1,
                'fingerprint' => 'abc123',
                'generator' => 'coretsia/core-kernel',
                'requires' => [
                    'module-manifest@1',
                ],
            ],
            'payload' => [
                'zeta' => 'last',
                'alpha' => [
                    'z' => 'nested-last',
                    'a' => 'nested-first',
                ],
                'items' => [
                    [
                        'b' => 2,
                        'a' => 1,
                    ],
                    'kept-in-list-order',
                    null,
                    true,
                    42,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseArtifactBytes(string $bytes): array
    {
        $decoded = new StablePhpArrayParser()->parse($bytes);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
