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
 * Vendor-neutral span abstraction.
 *
 * Implementations MUST NOT expose vendor-specific span handles through this
 * contract. Span names, attributes, and events MUST follow the global
 * observability naming and redaction policy.
 */
interface SpanInterface
{
    /**
     * Returns the stable span name.
     *
     * Span names MUST NOT contain raw paths, raw queries, raw SQL, user ids,
     * tokens, credentials, headers, cookies, or request/response bodies.
     *
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * Sets a safe json-like span attribute.
     *
     * Attribute names and values MUST obey the global redaction law.
     * Raw payloads, raw request data, raw SQL, headers, cookies, tokens,
     * credentials, profile payloads, and private customer data are forbidden.
     *
     * @param non-empty-string $key
     */
    public function setAttribute(string $key, mixed $value): void;

    /**
     * Sets safe json-like span attributes.
     *
     * The root value MUST be a map. Values MUST be json-like:
     * null, bool, int, string, list, or string-keyed map. Floats are forbidden.
     *
     * @param array<string,mixed> $attributes
     */
    public function setAttributes(array $attributes): void;

    /**
     * Adds a safe span event.
     *
     * Event attributes MUST be json-like and redacted.
     *
     * @param non-empty-string $name
     * @param array<string,mixed> $attributes
     */
    public function addEvent(string $name, array $attributes = []): void;

    /**
     * Records an exception safely.
     *
     * Implementations MUST NOT export stack traces, raw Throwable messages,
     * raw payloads, raw SQL, credentials, tokens, cookies, request/response
     * bodies, or profile payloads by default.
     *
     * @param array<string,mixed> $attributes
     */
    public function recordException(\Throwable $throwable, array $attributes = []): void;

    /**
     * Ends the span.
     *
     * Calling this method more than once SHOULD be noop-safe.
     */
    public function end(): void;
}
