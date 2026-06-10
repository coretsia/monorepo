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
        $returned = self::includePhpReturn(self::dumper()->dumpEnvelope([
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

        self::assertSame(['_meta', 'payload'], \array_keys($returned));
        self::assertArrayHasKey('name', $returned['_meta']);
        self::assertArrayHasKey('status', $returned['payload']);
        self::assertArrayNotHasKey('artifact', $returned);
        self::assertArrayNotHasKey('envelope', $returned);
    }

    public function testPreservesListOrder(): void
    {
        $returned = self::includePhpReturn(self::dumper()->dumpEnvelope([
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
            $returned['payload']['items'],
        );
    }

    public function testPreservesNormalizedMapOrder(): void
    {
        $returned = self::includePhpReturn(self::dumper()->dumpEnvelope([
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

        self::assertSame(['alpha', 'middle', 'zeta'], \array_keys($returned['payload']['map']));
        self::assertSame(['a', 'z'], \array_keys($returned['payload']['map']['middle']));
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
    private static function includePhpReturn(string $bytes): array
    {
        $path = \tempnam(\sys_get_temp_dir(), 'coretsia-artifact-');

        if ($path === false) {
            self::fail('Failed to create temporary artifact file.');
        }

        try {
            \file_put_contents($path, $bytes);

            $returned = (static function (string $__path): mixed {
                return include $__path;
            })(
                $path
            );

            self::assertIsArray($returned);

            /** @var array<string, mixed> $returned */
            return $returned;
        } finally {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
    }
}
