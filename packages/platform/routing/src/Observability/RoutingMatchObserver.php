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

namespace Coretsia\Platform\Routing\Observability;

use Closure;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Foundation\Time\Stopwatch;
use Psr\Log\LoggerInterface;

/**
 * Failure-isolated, invocation-local routing match observability.
 *
 * Matching decisions and context mutation remain owned by the caller. This
 * helper emits only bounded method/outcome values and a validated template
 * from an already successful canonical RouteMatch.
 *
 * @internal
 */
final readonly class RoutingMatchObserver
{
    private const string SPAN_NAME = 'routing.match';
    private const string METRIC_TOTAL = 'routing.match_total';
    private const string METRIC_DURATION = 'routing.match_duration_ms';
    private const string LOG_EVENT = 'routing.match.completed';

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
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private Stopwatch $stopwatch,
        private bool $tracingEnabled,
        private bool $metricsEnabled,
        private bool $loggingEnabled,
    ) {
    }

    /**
     * Executes one caller-owned match and records only completed decisions.
     * An unexpected match failure propagates unchanged, after span finalization.
     *
     * @param Closure(): ?RouteMatch $operation
     */
    public function observe(string $method, Closure $operation): ?RouteMatch
    {
        $boundedMethod = isset(self::METHODS[$method]) ? $method : 'OTHER';
        $span = $this->startSpan($boundedMethod);
        $startedAt = $this->startDuration();

        try {
            // The caller finishes PATH_TEMPLATE context mutation before return.
            $match = $operation();
            $outcome = $match === null ? 'not_matched' : 'matched';
            $this->complete($span, $boundedMethod, $outcome, $match, $startedAt);
            return $match;
        } finally {
            if ($span !== null) {
                try {
                    $span->end();
                } catch (\Throwable) {
                    // Span finalization never replaces a routing outcome.
                }
            }
        }
    }

    private function startSpan(string $method): ?SpanInterface
    {
        if (!$this->tracingEnabled) {
            return null;
        }

        try {
            return $this->tracer->startSpan(self::SPAN_NAME, ['method' => $method]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function startDuration(): ?int
    {
        if (!$this->metricsEnabled) {
            return null;
        }

        try {
            return $this->stopwatch->start();
        } catch (\Throwable) {
            return null;
        }
    }

    private function complete(
        ?SpanInterface $span,
        string $method,
        string $outcome,
        ?RouteMatch $match,
        ?int $startedAt,
    ): void {
        if ($span !== null) {
            try {
                $span->setAttribute('outcome', $outcome);
            } catch (\Throwable) {
                // Complete other signals regardless of span attribute failures.
            }

            if ($match !== null) {
                try {
                    $span->setAttribute('path_template', $match->pathTemplate());
                } catch (\Throwable) {
                    // The span is still finalized by the caller's finally.
                }
            }
        }

        if ($this->metricsEnabled) {
            $labels = ['method' => $method, 'outcome' => $outcome];
            try {
                $this->meter->increment(self::METRIC_TOTAL, 1, $labels);
            } catch (\Throwable) {
                // Attempt the duration observation independently.
            }

            if ($startedAt !== null) {
                try {
                    $durationMs = $this->stopwatch->stop($startedAt);
                    if ($durationMs >= 0 && $durationMs <= self::MAX_DURATION_MS) {
                        try {
                            $this->meter->observe(self::METRIC_DURATION, $durationMs, $labels);
                        } catch (\Throwable) {
                            // Duration recording cannot change the match.
                        }
                    }
                } catch (\Throwable) {
                    // Stopwatch failure does not suppress diagnostic logging.
                }
            }
        }

        if ($this->loggingEnabled) {
            $context = ['method' => $method, 'outcome' => $outcome];
            if ($match !== null) {
                $context['path_template'] = $match->pathTemplate();
            }

            try {
                $this->logger->debug(self::LOG_EVENT, $context);
            } catch (\Throwable) {
                // Logging failure never affects the completed routing decision.
            }
        }
    }
}
