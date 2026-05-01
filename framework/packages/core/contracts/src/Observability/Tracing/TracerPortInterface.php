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

namespace Coretsia\Contracts\Observability\Tracing;

/**
 * Vendor-neutral tracing port.
 *
 * Implementations may be no-op. This contract MUST NOT expose vendor tracing
 * SDK objects, PSR-7 objects, raw headers, raw request bodies, raw response
 * bodies, raw SQL, cookies, credentials, tokens, or private customer data.
 */
interface TracerPortInterface
{
    /**
     * Starts a span with safe json-like attributes.
     *
     * Span names MUST follow the canonical observability naming rules.
     * Attributes MUST be a safe json-like map:
     * null, bool, int, string, list, or string-keyed map. Floats are forbidden.
     *
     * @param array<string,mixed> $attributes
     */
    public function startSpan(string $name, array $attributes = []): SpanInterface;

    /**
     * Returns the current span for the runtime boundary, or null when no span
     * is active or the tracer implementation is no-op.
     */
    public function currentSpan(): ?SpanInterface;
}
