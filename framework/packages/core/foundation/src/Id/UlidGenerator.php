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

namespace Coretsia\Foundation\Id;

/**
 * Canonical Foundation ULID generator.
 *
 * This class is the single ULID implementation source for Foundation runtime
 * code. Other Foundation ids that need ULID format must delegate here instead
 * of duplicating timestamp, entropy, or Crockford Base32 encoding logic.
 */
final class UlidGenerator
{
    private const string ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int TIMESTAMP_MAX = 281_474_976_710_655; // 2^48 - 1

    public function generate(): string
    {
        return self::encode(
            self::timestampBytes(self::unixTimeMilliseconds()) . \random_bytes(10),
        );
    }

    private static function unixTimeMilliseconds(): int
    {
        $parts = \explode(' ', \microtime());

        if (\count($parts) !== 2) {
            throw new \RuntimeException('ulid-time-source-invalid');
        }

        [$microseconds, $seconds] = $parts;

        $milliseconds = (int)\substr($microseconds . '000', 2, 3);

        return ((int)$seconds * 1_000) + $milliseconds;
    }

    private static function timestampBytes(int $milliseconds): string
    {
        if ($milliseconds < 0 || $milliseconds > self::TIMESTAMP_MAX) {
            throw new \RuntimeException('ulid-timestamp-out-of-range');
        }

        return \chr(($milliseconds >> 40) & 0xFF)
            . \chr(($milliseconds >> 32) & 0xFF)
            . \chr(($milliseconds >> 24) & 0xFF)
            . \chr(($milliseconds >> 16) & 0xFF)
            . \chr(($milliseconds >> 8) & 0xFF)
            . \chr($milliseconds & 0xFF);
    }

    private static function encode(string $bytes): string
    {
        if (\strlen($bytes) !== 16) {
            throw new \RuntimeException('ulid-bytes-invalid');
        }

        $bits = '00';

        for ($i = 0; $i < 16; $i++) {
            $bits .= \str_pad(
                \decbin(\ord($bytes[$i])),
                8,
                '0',
                \STR_PAD_LEFT,
            );
        }

        $encoded = '';

        for ($i = 0; $i < 26; $i++) {
            $index = \bindec(\substr($bits, $i * 5, 5));
            $encoded .= self::ENCODING[$index];
        }

        return $encoded;
    }
}
