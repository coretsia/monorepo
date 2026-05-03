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
 * Safe immutable contracts model for a rate limit allow/deny decision.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 *
 * The decision exposes only safe scalar decision metadata and the safe
 * RateLimitState snapshot. It MUST NOT expose raw rate limit keys, hashed keys,
 * actor ids, client IPs, user ids, tenant ids, request ids, correlation ids,
 * raw paths, raw queries, headers, cookies, tokens, credentials, backend
 * handles, Redis keys, lock tokens, vendor response objects, service instances,
 * or runtime wiring objects.
 */
final readonly class RateLimitDecision
{
    public const int SCHEMA_VERSION = 1;

    private bool $allowed;

    /**
     * @var int<0,max>|null
     */
    private ?int $retryAfterSeconds;

    /**
     * @var non-empty-string|null
     */
    private ?string $reason;
    private RateLimitState $state;

    /**
     * @param int<0,max>|null $retryAfterSeconds
     * @param non-empty-string|null $reason Safe bounded owner-defined reason/outcome.
     */
    public function __construct(
        bool $allowed,
        RateLimitState $state,
        ?int $retryAfterSeconds = null,
        ?string $reason = null,
    ) {
        $this->allowed = $allowed;
        $this->state = $state;
        $this->retryAfterSeconds = self::normalizeRetryAfterSeconds($retryAfterSeconds);
        $this->reason = self::normalizeReason($reason);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Suggested retry delay when denied, or null when no delay is required.
     *
     * A retry delay of 0 means retry may be attempted immediately according to
     * owner policy.
     *
     * @return int<0,max>|null
     */
    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    /**
     * Safe bounded owner-defined reason/outcome.
     *
     * This value MUST NOT contain raw key material, hashed key material,
     * request ids, correlation ids, actor ids, client IPs, raw paths, raw
     * queries, headers, cookies, tokens, credentials, backend diagnostics, or
     * other unsafe payload.
     *
     * @return non-empty-string|null
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    public function state(): RateLimitState
    {
        return $this->state;
    }

    /**
     * Returns a deterministic safe scalar/json-like decision shape.
     *
     * The exported shape intentionally exposes no key material, transport data,
     * or backend data.
     *
     * @return array{
     *     allowed: bool,
     *     reason: non-empty-string|null,
     *     retryAfterSeconds: int<0,max>|null,
     *     schemaVersion: int,
     *     state: array<string,mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'retryAfterSeconds' => $this->retryAfterSeconds,
            'schemaVersion' => self::SCHEMA_VERSION,
            'state' => $this->state->toArray(),
        ];
    }

    /**
     * @return int<0,max>|null
     */
    private static function normalizeRetryAfterSeconds(?int $retryAfterSeconds): ?int
    {
        if ($retryAfterSeconds === null) {
            return null;
        }

        if ($retryAfterSeconds < 0) {
            throw new \InvalidArgumentException('Invalid rate limit retry after seconds.');
        }

        return $retryAfterSeconds;
    }

    /**
     * @return non-empty-string|null
     */
    private static function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Invalid rate limit decision reason.');
        }

        if (!self::isSafeSingleLineString($reason)) {
            throw new \InvalidArgumentException('Invalid rate limit decision reason.');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $reason) !== 1) {
            throw new \InvalidArgumentException('Invalid rate limit decision reason.');
        }

        return $reason;
    }

    private static function isSafeSingleLineString(string $value): bool
    {
        return self::isSafeString($value)
            && !str_contains($value, "\r")
            && !str_contains($value, "\n");
    }

    private static function isSafeString(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
