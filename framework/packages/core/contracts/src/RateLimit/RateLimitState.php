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

namespace Coretsia\Contracts\RateLimit;

/**
 * Safe immutable contracts model for effective rate limit state.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 *
 * The state exposes only scalar bucket/window information. It MUST NOT expose
 * raw rate limit keys, hashed keys, actor ids, client IPs, user ids, tenant ids,
 * request ids, correlation ids, raw paths, raw queries, headers, cookies,
 * tokens, credentials, backend handles, Redis keys, lock tokens, vendor
 * response objects, service instances, or runtime wiring objects.
 */
final readonly class RateLimitState
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @var int<1,max>
     */
    private int $limit;

    /**
     * @var int<0,max>
     */
    private int $remaining;

    /**
     * @var int<0,max>
     */
    private int $resetAfterSeconds;

    /**
     * @var int<1,max>
     */
    private int $windowSeconds;

    /**
     * @param int<1,max> $limit
     * @param int<0,max> $remaining
     * @param int<0,max> $resetAfterSeconds
     * @param int<1,max> $windowSeconds
     */
    public function __construct(
        int $limit,
        int $remaining,
        int $resetAfterSeconds,
        int $windowSeconds,
    ) {
        $this->limit = self::normalizeLimit($limit);
        $this->remaining = self::normalizeRemaining($remaining, $this->limit);
        $this->resetAfterSeconds = self::normalizeResetAfterSeconds($resetAfterSeconds);
        $this->windowSeconds = self::normalizeWindowSeconds($windowSeconds);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * Maximum allowed cost or hit count in the current window.
     *
     * @return int<1,max>
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * Remaining cost or hit count after the relevant store operation.
     *
     * @return int<0,max>
     */
    public function remaining(): int
    {
        return $this->remaining;
    }

    /**
     * Number of seconds until the current window or bucket is expected to reset.
     *
     * @return int<0,max>
     */
    public function resetAfterSeconds(): int
    {
        return $this->resetAfterSeconds;
    }

    /**
     * Effective rate limit window size in seconds.
     *
     * @return int<1,max>
     */
    public function windowSeconds(): int
    {
        return $this->windowSeconds;
    }

    /**
     * Returns a deterministic safe scalar/json-like state shape.
     *
     * The exported shape intentionally exposes no key material or backend data.
     *
     * @return array{
     *     limit: int<1,max>,
     *     remaining: int<0,max>,
     *     resetAfterSeconds: int<0,max>,
     *     schemaVersion: int,
     *     windowSeconds: int<1,max>
     * }
     */
    public function toArray(): array
    {
        return [
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'resetAfterSeconds' => $this->resetAfterSeconds,
            'schemaVersion' => self::SCHEMA_VERSION,
            'windowSeconds' => $this->windowSeconds,
        ];
    }

    /**
     * @return int<1,max>
     */
    private static function normalizeLimit(int $limit): int
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Invalid rate limit limit.');
        }

        return $limit;
    }

    /**
     * @param int<1,max> $limit
     *
     * @return int<0,max>
     */
    private static function normalizeRemaining(int $remaining, int $limit): int
    {
        if ($remaining < 0) {
            throw new \InvalidArgumentException('Invalid rate limit remaining.');
        }

        if ($remaining > $limit) {
            throw new \InvalidArgumentException('Invalid rate limit remaining.');
        }

        return $remaining;
    }

    /**
     * @return int<0,max>
     */
    private static function normalizeResetAfterSeconds(int $resetAfterSeconds): int
    {
        if ($resetAfterSeconds < 0) {
            throw new \InvalidArgumentException('Invalid rate limit reset after seconds.');
        }

        return $resetAfterSeconds;
    }

    /**
     * @return int<1,max>
     */
    private static function normalizeWindowSeconds(int $windowSeconds): int
    {
        if ($windowSeconds < 1) {
            throw new \InvalidArgumentException('Invalid rate limit window seconds.');
        }

        return $windowSeconds;
    }
}
