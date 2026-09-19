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
 * Contracts-level rate limit key hashing boundary.
 *
 * This port transforms sensitive logical rate limit key material into a stable
 * deterministic storage key that may be passed to rate limit store
 * implementations.
 *
 * The input key is sensitive internal key material. Implementations MUST NOT
 * expose it through exceptions, logs, metrics, spans, health output, CLI output,
 * or unsafe debug output.
 *
 * The returned key is still internal implementation data. It MUST NOT become a
 * metric label, public diagnostic value, error descriptor extension, health
 * output field, or CLI output field.
 *
 * Concrete hashing algorithm, keyed hashing, salts, namespace prefixes, secret
 * rotation, and backend key conventions are runtime-owned.
 */
interface RateLimitKeyHasherInterface
{
    /**
     * Transforms sensitive non-empty rate limit key material into a non-empty
     * deterministic storage key.
     *
     * Implementations MUST be deterministic for the same logical input and
     * policy.
     *
     * Implementations MUST NOT return an empty string.
     *
     * Implementations MUST NOT return the raw key material unchanged.
     *
     * Implementations MUST NOT expose the raw key material in exception
     * messages or diagnostics.
     *
     * @param non-empty-string $key Sensitive logical rate limit key material.
     *
     * @return non-empty-string Internal deterministic storage key.
     */
    public function hash(string $key): string;
}
