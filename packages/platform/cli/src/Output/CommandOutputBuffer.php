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

use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Cli\Exception\CliOutputFormatException;

/**
 * Invocation-local normalized command output, never a console output sink.
 *
 * Foundation owns JSON-like validation, bounds, map ordering, and encoding.
 * CLI owns only presentation-specific string and map-key control policy.
 *
 * @internal
 */
final class CommandOutputBuffer implements OutputInterface
{
    private const int MAX_RECORDS = 1024;
    private const int MAX_RECORD_BYTES = 1048576;

    /** @var list<array<string, mixed>> */
    private array $records = [];

    private int $recordBytes = 0;

    private bool $finalized = false;

    private bool $failed = false;

    public function text(string $text): void
    {
        $this->assertWritable();

        $this->append([
            'type' => 'text',
            'text' => self::normalizeString($text),
        ]);
    }

    /** @param array<string, mixed>|list<mixed> $payload */
    public function json(array $payload): void
    {
        $this->assertWritable();

        try {
            $normalized = JsonLikeNormalizer::normalize(
                $payload,
                'payload',
                self::limits(),
            );
            $transformed = self::normalizeValueStrings($normalized);
            $normalized = JsonLikeNormalizer::normalize(
                $transformed,
                'payload',
                self::limits(),
            );
        } catch (\Throwable) {
            $this->failed = true;
            throw CliOutputFormatException::failed();
        }

        $this->append([
            'type' => 'json',
            'payload' => $normalized,
        ]);
    }

    public function error(string $code, string $message): void
    {
        $this->assertWritable();

        if (\preg_match('/\A[A-Za-z][A-Za-z0-9_.:-]{0,127}\z/D', $code) !== 1) {
            throw CliOutputFormatException::failed();
        }

        $message = self::normalizeString($message);

        if (
            \trim($message) === ''
            || \str_contains($message, "\n")
            || \preg_match('/[\x{2028}\x{2029}]/u', $message) === 1
        ) {
            throw CliOutputFormatException::failed();
        }

        $this->append([
            'type' => 'error',
            'code' => $code,
            'message' => $message,
        ]);
    }

    public function finalize(): CommandOutputBatch
    {
        $this->assertWritable();
        $batch = new CommandOutputBatch($this->records);
        $this->finalized = true;

        return $batch;
    }

    public function discard(): void
    {
        if ($this->finalized) {
            throw CliOutputFormatException::failed();
        }
        $this->records = [];
        $this->recordBytes = 0;
        $this->failed = false;
    }

    /** @param array<string, mixed> $record */
    private function append(array $record): void
    {
        $this->assertWritable();

        if (\count($this->records) >= self::MAX_RECORDS) {
            $this->failed = true;
            throw CliOutputFormatException::failed();
        }

        try {
            $canonical = JsonLikeNormalizer::normalize($record, 'record', self::limits());
            $bytes = \strlen(StableJsonEncoder::encodeStable($canonical));
        } catch (\Throwable) {
            $this->failed = true;
            throw CliOutputFormatException::failed();
        }

        if ($bytes > self::MAX_RECORD_BYTES - $this->recordBytes) {
            $this->failed = true;
            throw CliOutputFormatException::failed();
        }

        /** @var array<string, mixed> $canonical */
        $this->records[] = $canonical;
        $this->recordBytes += $bytes;
    }

    private function assertWritable(): void
    {
        if ($this->finalized || $this->failed) {
            throw CliOutputFormatException::failed();
        }
    }

    private static function limits(): JsonLikeNormalizationLimits
    {
        return new JsonLikeNormalizationLimits(
            maxDepth: 32,
            maxNodes: 4096,
            maxStringBytes: self::MAX_RECORD_BYTES,
            maxTotalStringBytes: self::MAX_RECORD_BYTES,
        );
    }

    /**
     * Transforms string values only. All containers have already passed the
     * canonical Foundation validator; keys retain their exact original bytes.
     */
    private static function normalizeValueStrings(mixed $value): mixed
    {
        if (\is_string($value)) {
            return self::normalizeString($value);
        }

        if (!\is_array($value)) {
            return $value;
        }

        $transformed = [];

        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                self::assertSafeMapKey($key);
            }

            $transformed[$key] = self::normalizeValueStrings($item);
        }

        return $transformed;
    }

    private static function normalizeString(string $value): string
    {
        $normalized = \str_replace(["\r\n", "\r"], "\n", $value);

        // A malformed UTF-8 string is left for StableJsonEncoder to reject.
        if (\preg_match('/[\x{0000}-\x{0008}\x{000B}-\x{001F}\x{007F}-\x{009F}]/u', $normalized) === 1) {
            throw CliOutputFormatException::failed();
        }

        return $normalized;
    }

    private static function assertSafeMapKey(string $key): void
    {
        // Never rewrite map keys. UTF-8 validity is checked by the encoder.
        if (\preg_match('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', $key) === 1) {
            throw CliOutputFormatException::failed();
        }
    }
}
