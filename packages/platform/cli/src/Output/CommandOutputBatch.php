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

namespace Coretsia\Platform\Cli\Output;

use Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Cli\Exception\CliOutputFormatException;

/**
 * Immutable finalized sequence of canonical CLI output records.
 *
 * The normal production creator is CommandOutputBuffer::finalize(). Directly
 * constructed batches are checked against the same Foundation value model.
 *
 * @internal
 */
final readonly class CommandOutputBatch
{
    /** @var list<array<string, mixed>> */
    private array $records;

    /** @param list<array<string, mixed>> $records */
    public function __construct(array $records)
    {
        if (!\array_is_list($records)) {
            throw CliOutputFormatException::failed();
        }

        foreach ($records as $record) {
            if (!\is_array($record)) {
                throw CliOutputFormatException::failed();
            }

            $keys = match ($record['type'] ?? null) {
                'text' => ['text', 'type'],
                'json' => ['payload', 'type'],
                'error' => ['code', 'message', 'type'],
                default => throw CliOutputFormatException::failed(),
            };

            if (\array_keys($record) !== $keys) {
                throw CliOutputFormatException::failed();
            }

            if (
                ($record['type'] === 'text' && !self::isSafeString($record['text']))
                || ($record['type'] === 'json' && !\is_array($record['payload']))
                || ($record['type'] === 'error' && (
                    !\is_string($record['code'])
                        || \preg_match('/\A[A-Za-z][A-Za-z0-9_.:-]{0,127}\z/D', $record['code']) !== 1
                        || !self::isSafeString($record['message'], singleLine: true)
                ))
            ) {
                throw CliOutputFormatException::failed();
            }

            try {
                $normalized = JsonLikeNormalizer::normalize(
                    $record,
                    'record',
                    new JsonLikeNormalizationLimits(32, 4096, 1048576, 1048576),
                );
                StableJsonEncoder::encodeStable($normalized);
            } catch (\Throwable) {
                throw CliOutputFormatException::failed();
            }

            if (
                $normalized !== $record
                || ($record['type'] === 'json' && !self::hasSafePresentationStrings($record['payload']))
            ) {
                throw CliOutputFormatException::failed();
            }
        }

        $this->records = $records;
    }

    /** @return list<array<string, mixed>> */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * Checks only CLI presentation controls after Foundation validation.
     * This does not duplicate Foundation's JSON-like type or budget checks.
     */
    private static function hasSafePresentationStrings(mixed $value): bool
    {
        if (\is_string($value)) {
            return self::isSafeString($value);
        }

        if (!\is_array($value)) {
            return true;
        }

        foreach ($value as $key => $item) {
            if (
                (\is_string($key)
                    && \preg_match('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', $key) === 1)
                || !self::hasSafePresentationStrings($item)
            ) {
                return false;
            }
        }

        return true;
    }

    private static function isSafeString(mixed $value, bool $singleLine = false): bool
    {
        if (!\is_string($value) || \str_contains($value, "\r")) {
            return false;
        }

        if (
            $singleLine
            && (
                \trim($value) === ''
                || \str_contains($value, "\n")
                || \preg_match('/[\x{2028}\x{2029}]/u', $value) === 1
            )
        ) {
            return false;
        }

        return \preg_match('/[\x{0000}-\x{0008}\x{000B}-\x{001F}\x{007F}-\x{009F}]/u', $value) !== 1;
    }
}
