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

use Psr\Log\LoggerInterface;

/**
 * Stateless safe-by-construction HTTP request completion log producer.
 *
 * HttpKernel supplies only the bounded final method, status, outcome,
 * duration, and optional Kernel-owned correlation identity. In the routing-free
 * baseline route_template is always unknown; no request data, routes, or
 * exception messages are consulted. Existing PSR-3 logging owns redaction.
 */
final readonly class HttpAccessLogWriter
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
        private LoggerInterface $logger,
        private bool $enabled,
    ) {
    }

    /**
     * @param int|string $status Final HTTP status (100..599) or fixed unknown.
     */
    public function write(
        string $method,
        int|string $status,
        string $outcome,
        int $durationMs,
        ?string $correlationId = null,
    ): void {
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

        $context = [
            'method' => $method,
            'status' => $status,
            'outcome' => $outcome,
            'duration_ms' => $durationMs,
            'route_template' => 'unknown',
        ];

        // Identity is optional and never reconstructed from request headers.
        if ($correlationId !== null && \preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $correlationId) === 1) {
            $context['correlation_id'] = $correlationId;
        }

        try {
            $this->logger->info('http.request.completed', $context);
        } catch (\Throwable) {
            // Logging must never replace the completed response or Throwable.
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
