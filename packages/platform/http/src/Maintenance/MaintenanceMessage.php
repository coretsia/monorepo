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

namespace Coretsia\Platform\Http\Maintenance;

use Coretsia\Foundation\Serialization\StableJsonDecoder;

/**
 * Immutable version-1 public maintenance message.
 *
 * Direct construction and JSON reconstruction share the same semantic
 * validation. Message values are accepted without transformation.
 *
 * JSON reconstruction uses the canonical Foundation decoder and its
 * duplicate-object-key guard. Invalid values are rejected through a fixed,
 * payload-free diagnostic token without retaining decoder exceptions.
 *
 * Filesystem access, runtime state, and response construction belong to
 * their respective maintenance boundaries.
 *
 * @internal
 */
final readonly class MaintenanceMessage
{
    private const int SCHEMA_VERSION = 1;

    private const int MAX_MESSAGE_BYTES = 1024;

    private const int MAX_JSON_BYTES = 8192;

    private const int MAX_RETRY_AFTER_SECONDS = 86400;

    private const string REASON_INVALID_MESSAGE = 'http-maintenance-message-invalid';

    public function __construct(
        int $schemaVersion,
        private string $message,
        private ?int $retryAfterSeconds,
    ) {
        if (
            $schemaVersion !== self::SCHEMA_VERSION
            || $message === ''
            || \strlen($message) > self::MAX_MESSAGE_BYTES
            || \preg_match('/[\p{Cc}\p{Zl}\p{Zp}]|\A\p{Z}|\p{Z}\z/u', $message) !== 0
            || (
                $retryAfterSeconds !== null
                && ($retryAfterSeconds < 1 || $retryAfterSeconds > self::MAX_RETRY_AFTER_SECONDS)
            )
        ) {
            throw new \InvalidArgumentException(self::REASON_INVALID_MESSAGE);
        }
    }

    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw new \InvalidArgumentException(self::REASON_INVALID_MESSAGE);
        }

        try {
            $map = StableJsonDecoder::decodeStableMap($json);
            StableJsonDecoder::assertNoDuplicateObjectKeys($json);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException(self::REASON_INVALID_MESSAGE);
        }

        if (\array_diff_key($map, [
                'schemaVersion' => true,
                'message' => true,
                'retryAfterSeconds' => true,
            ]) !== []) {
            throw new \InvalidArgumentException(self::REASON_INVALID_MESSAGE);
        }

        $schemaVersion = $map['schemaVersion'] ?? null;
        $message = $map['message'] ?? null;

        if (!\is_int($schemaVersion) || !\is_string($message)) {
            throw new \InvalidArgumentException(self::REASON_INVALID_MESSAGE);
        }

        $retryAfterSeconds = null;

        if (\array_key_exists('retryAfterSeconds', $map)) {
            $retryAfterSeconds = $map['retryAfterSeconds'];

            if (!\is_int($retryAfterSeconds)) {
                throw new \InvalidArgumentException(self::REASON_INVALID_MESSAGE);
            }
        }

        return new self(
            schemaVersion: $schemaVersion,
            message: $message,
            retryAfterSeconds: $retryAfterSeconds,
        );
    }

    /**
     * Returns the canonical version-1 maintenance message map.
     *
     * @return array{schemaVersion: 1, message: string, retryAfterSeconds?: int}
     */
    public function toArray(): array
    {
        $map = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'message' => $this->message,
        ];

        if ($this->retryAfterSeconds !== null) {
            $map['retryAfterSeconds'] = $this->retryAfterSeconds;
        }

        return $map;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
