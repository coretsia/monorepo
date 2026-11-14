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

namespace Coretsia\Platform\Http\Observability;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

/**
 * Stateless final HTTP request metrics through the existing meter port.
 *
 * Only the canonical observability catalog entries are emitted. Method,
 * status, outcome, and duration are supplied by HttpKernel after response
 * finalization; no request, route, context, or Throwable is inspected here.
 * Counter and duration failures are isolated independently.
 */
final readonly class HttpRequestMetricsRecorder
{
    private const int MAX_DURATION_MS = 4_294_967_295;

    /** @var array<string, true> */
    private const array METHODS = [
        'GET' => true,
        'HEAD' => true,
        'POST' => true,
        'PUT' => true,
        'PATCH' => true,
        'DELETE' => true,
        'OPTIONS' => true,
        'TRACE' => true,
        'CONNECT' => true,
        'OTHER' => true,
    ];

    public function __construct(
        private MeterPortInterface $meter,
        private bool $enabled,
    ) {
    }

    /**
     * @param int|string $status Final HTTP status (100..599) or fixed unknown.
     */
    public function record(string $method, int|string $status, string $outcome, int $durationMs): void
    {
        if (
            !$this->enabled
            || !isset(self::METHODS[$method])
            || $durationMs < 0
            || $durationMs > self::MAX_DURATION_MS
        ) {
            return;
        }

        if (!self::validCompletion($status, $outcome)) {
            return;
        }

        $labels = [
            'method' => $method,
            'status' => $status,
            'outcome' => $outcome,
        ];

        try {
            $this->meter->increment('http.request_total', 1, $labels);
        } catch (\Throwable) {
            // An optional counter failure cannot affect the HTTP lifecycle.
        }

        try {
            $this->meter->observe('http.request_duration_ms', $durationMs, $labels);
        } catch (\Throwable) {
            // Duration recording is attempted independently of the counter.
        }
    }

    private static function validCompletion(int|string $status, string $outcome): bool
    {
        if ($status === 'unknown') {
            return $outcome === 'fatal_error';
        }

        if (!\is_int($status) || $status < 100 || $status > 599) {
            return false;
        }

        return $status < 400
            ? $outcome === 'success'
            : $outcome === 'handled_error';
    }
}
