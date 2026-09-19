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
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

/**
 * No-op tracer implementation for the Foundation observability baseline.
 *
 * This implementation stores no span stack, attributes, payloads, headers,
 * tokens, raw SQL, or private data. It emits no diagnostics.
 */
final class NoopTracer implements TracerPortInterface
{
    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        unset($attributes);

        return new NoopSpan($name);
    }

    public function inSpan(
        string $name,
        callable $callback,
        array $attributes = [],
    ): mixed {
        $span = $this->startSpan($name, $attributes);

        try {
            return $callback($span);
        } finally {
            $span->end();
        }
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}
