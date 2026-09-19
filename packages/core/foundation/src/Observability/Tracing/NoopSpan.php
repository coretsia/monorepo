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

namespace Coretsia\Foundation\Observability\Tracing;

use Coretsia\Contracts\Observability\Tracing\SpanInterface;

/**
 * No-op span implementation for the Foundation observability baseline.
 *
 * This implementation intentionally stores no span attributes, events,
 * exceptions, payloads, headers, tokens, raw SQL, or private data.
 *
 * It never emits diagnostics and every mutating operation is noop-safe.
 */
final class NoopSpan implements SpanInterface
{
    private const string NAME = 'noop';

    /**
     * The supplied name is intentionally ignored.
     *
     * A no-op span must not retain potentially unsafe runtime input. The stable
     * synthetic name keeps `name()` contract-safe without storing user/runtime
     * payloads.
     */
    public function __construct(string $name = self::NAME)
    {
        unset($name);
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        unset($key, $value);
    }

    public function setAttributes(array $attributes): void
    {
        unset($attributes);
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        unset($name, $attributes);
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        unset($throwable, $attributes);
    }

    public function end(): void
    {
    }
}
