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

use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

/**
 * Stateless lifecycle helper for the canonical HTTP request span.
 *
 * HttpKernel owns the invocation-local SpanInterface handle and invokes
 * finish() on each completion path. This service never retains an active span,
 * records raw Throwables, or claims inbound parent-context propagation.
 */
final readonly class HttpRequestSpanRecorder
{
    private const string SPAN_NAME = 'http.request';

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
        private TracerPortInterface $tracer,
        private bool $enabled,
    ) {
    }

    /**
     * Starts the optional request-local span before the PSR-15 pipeline.
     */
    public function start(string $method): ?SpanInterface
    {
        if (!$this->enabled || !isset(self::METHODS[$method])) {
            return null;
        }

        try {
            return $this->tracer->startSpan(self::SPAN_NAME, ['method' => $method]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ends the exact supplied request-local span, even if an attribute fails.
     * No Throwable from pipeline execution is accepted or recorded.
     *
     * @param int|string $status Final HTTP status (100..599) or fixed unknown.
     */
    public function finish(?SpanInterface $span, string $method, int|string $status, string $outcome): void
    {
        if ($span === null) {
            return;
        }

        try {
            if (isset(self::METHODS[$method])) {
                try {
                    $span->setAttribute('method', $method);
                } catch (\Throwable) {
                    // Continue finalizing independently of this attribute.
                }
            }

            if (self::validCompletion($status, $outcome)) {
                try {
                    $span->setAttribute('status', $status);
                } catch (\Throwable) {
                    // A failed status update does not suppress the outcome.
                }

                try {
                    $span->setAttribute('outcome', $outcome);
                } catch (\Throwable) {
                    // The exact span is still ended below.
                }
            }
        } finally {
            try {
                $span->end();
            } catch (\Throwable) {
                // Span ending must never replace the primary HTTP result.
            }
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
