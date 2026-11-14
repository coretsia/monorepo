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

namespace Coretsia\Platform\Http;

use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Single-owner HTTP request lifecycle and final-response orchestration.
 *
 * KernelRuntime owns UnitOfWork begin, after hooks, and reset. This adapter
 * retains all request response, timing, span, outcome, and Throwable state only
 * inside handle(). Observability always consumes the finalized HTTP result.
 *
 * @internal
 */
final readonly class HttpKernel implements HttpKernelInterface
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
    ];

    public function __construct(
        private KernelRuntimeInterface $runtime,
        private Stopwatch $stopwatch,
        private Pipeline $pipeline,
        private HttpResponseFinalizer $finalizer,
        private HttpRequestMetricsRecorder $metrics,
        private HttpAccessLogWriter $accessLog,
        private HttpRequestSpanRecorder $spans,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $handle = $this->runtime->beginUnitOfWork('http');

        $method = 'OTHER';
        $status = 'unknown';
        $outcome = 'fatal_error';
        $durationMs = 0;
        $startedAt = null;
        $span = null;
        $response = null;
        $primary = null;

        try {
            try {
                $candidate = $this->stopwatch->start();
                if ($candidate > 0) {
                    $startedAt = $candidate;
                }
            } catch (\Throwable) {
                // Timing is optional. The pipeline must still execute.
            }

            $method = self::canonicalMethod($request->getMethod());

            try {
                $span = $this->spans->start($method);
            } catch (\Throwable) {
                // Span startup cannot suppress the HTTP pipeline.
            }

            $response = $this->pipeline->handle($request);
            $response = $this->finalizer->finalize($response);
            $status = $response->getStatusCode();
            if ($status < 100 || $status > 599) {
                throw new \RuntimeException('http-final-response-status-invalid');
            }

            $outcome = $status < 400 ? 'success' : 'handled_error';
        } catch (\Throwable $throwable) {
            $primary = $throwable;
            $response = null;
            $status = 'unknown';
            $outcome = 'fatal_error';
        }

        try {
            if ($startedAt !== null) {
                try {
                    $measured = $this->stopwatch->stop($startedAt);
                    $durationMs = \max(0, \min(self::MAX_DURATION_MS, $measured));
                } catch (\Throwable) {
                    // A stopped or unavailable timer produces a safe zero.
                }
            }

            try {
                $this->metrics->record($method, $status, $outcome, $durationMs);
            } catch (\Throwable) {
                // Recording cannot replace a completed response or primary error.
            }

            try {
                $context = $handle->context();
                $correlationId = $context['correlationId'] ?? null;
                $this->accessLog->write(
                    $method,
                    $status,
                    $outcome,
                    $durationMs,
                    \is_string($correlationId) ? $correlationId : null,
                );
            } catch (\Throwable) {
                // Access logging is an independent optional completion path.
            }

            try {
                $this->spans->finish($span, $method, $status, $outcome);
            } catch (\Throwable) {
                // Even an unexpected span-recorder failure is non-fatal.
            }
        } finally {
            try {
                $this->runtime->afterUnitOfWork($handle, $outcome, $primary);
            } catch (\Throwable $completionFailure) {
                if ($primary === null) {
                    $primary = $completionFailure;
                }
            }
        }

        if ($primary !== null) {
            throw $primary;
        }

        // A successful pipeline and response finalizer always return a response.
        return $response;
    }

    private static function canonicalMethod(string $method): string
    {
        if ($method === '' || \strlen($method) > 16) {
            return 'OTHER';
        }

        $normalized = \strtoupper($method);

        return isset(self::METHODS[$normalized]) ? $normalized : 'OTHER';
    }
}
