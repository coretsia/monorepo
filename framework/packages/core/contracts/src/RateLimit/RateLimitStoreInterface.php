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
 * Contracts-level rate limit store boundary.
 *
 * This port accepts an already-safe internal storage key produced by
 * RateLimitKeyHasherInterface. It does not accept raw logical key material.
 *
 * Implementations own concrete storage behavior, atomicity, timing precision,
 * race handling, backend failure policy, and backend-specific optimization.
 *
 * This interface MUST NOT expose Redis clients, Redis commands, Lua scripts,
 * TTL backend metadata, database connections, cache pools, locks, clock
 * objects, request/response objects, PSR-7 objects, middleware objects, config
 * repositories, service containers, streams, resources, closures, iterators,
 * generators, or vendor runtime objects.
 *
 * The key hash is still internal implementation data. Implementations MUST NOT
 * expose it through logs, metrics, spans, health output, CLI output, error
 * descriptor extensions, public diagnostics, returned state, or returned
 * decisions.
 */
interface RateLimitStoreInterface
{
    /**
     * Returns the stable safe store implementation name.
     *
     * The name MAY be used by runtime owners as a bounded diagnostics value or
     * as an allowed `driver` metric label value when observability policy allows
     * it.
     *
     * The name MUST be stable, safe, non-empty, and bounded-cardinality.
     *
     * The name MUST NOT contain raw key material, hashed key material, actor
     * ids, client IPs, user ids, tenant ids, request ids, correlation ids, raw
     * paths, raw queries, hostnames, DSNs, connection strings, credentials,
     * tokens, environment-specific identifiers, or backend object metadata.
     *
     * Examples of safe names:
     *
     * - memory
     * - redis
     * - database
     * - cache
     * - external
     *
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * Atomically consumes cost units from the rate limit represented by the
     * internal key hash, limit, and window.
     *
     * A returned allowed decision means the operation may proceed according to
     * runtime owner policy.
     *
     * A returned denied decision means the operation should be rejected or
     * delayed according to runtime owner policy.
     *
     * Implementations MUST return safe contracts models only and MUST NOT
     * expose backend-specific objects or key material in the returned decision.
     *
     * @param non-empty-string $keyHash Internal deterministic storage key produced by RateLimitKeyHasherInterface.
     * @param int<1,max> $limit Maximum allowed cost or hit count in the effective window.
     * @param int<1,max> $windowSeconds Effective rate limit window size in seconds.
     * @param int<1,max> $cost Positive integer cost consumed by this operation.
     */
    public function consume(
        string $keyHash,
        int $limit,
        int $windowSeconds,
        int $cost = 1,
    ): RateLimitDecision;

    /**
     * Returns the current safe state for the rate limit represented by the
     * internal key hash, limit, and window without consuming additional cost.
     *
     * Missing-state initialization behavior is implementation-owned.
     *
     * Implementations MUST return safe contracts state only and MUST NOT expose
     * backend-specific objects, raw key material, or hashed key material in the
     * returned state.
     *
     * @param non-empty-string $keyHash Internal deterministic storage key produced by RateLimitKeyHasherInterface.
     * @param int<1,max> $limit Maximum allowed cost or hit count in the effective window.
     * @param int<1,max> $windowSeconds Effective rate limit window size in seconds.
     */
    public function state(
        string $keyHash,
        int $limit,
        int $windowSeconds,
    ): RateLimitState;

    /**
     * Clears or resets the store state for the internal key hash according to
     * implementation-owned backend semantics.
     *
     * Runtime owners may use this for tests, administrative behavior, or
     * policy-owned reset flows.
     *
     * Implementations MUST NOT expose the raw key or hashed key through
     * diagnostics.
     *
     * @param non-empty-string $keyHash Internal deterministic storage key produced by RateLimitKeyHasherInterface.
     */
    public function reset(string $keyHash): void;
}
