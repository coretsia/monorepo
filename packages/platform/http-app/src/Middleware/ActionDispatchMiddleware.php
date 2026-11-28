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

namespace Coretsia\Platform\HttpApp\Middleware;

use Closure;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\HttpApp\Action\ActionInvoker;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Coretsia\Platform\HttpApp\Response\ActionResponseMapper;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Canonical final application dispatch contribution.
 *
 * Route-specific middleware is admitted before constructing an invocation-local
 * guarded HTTP Pipeline. Only its validated action terminal owns action
 * observations. Reserved-path handoffs remain owned by the downstream chain.
 */
final readonly class ActionDispatchMiddleware implements MiddlewareInterface
{
    private const string SPAN_NAME = 'http.action';
    private const string METRIC_TOTAL = 'http.action_total';
    private const string METRIC_DURATION = 'http.action_duration_ms';
    private const string LOG_EVENT = 'http.action.completed';

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
        private Container $container,
        private RouteMiddlewareSelector $selector,
        private ActionInvoker $invoker,
        private ActionResponseMapper $responseMapper,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private Stopwatch $stopwatch,
        private bool $tracingEnabled,
        private bool $metricsEnabled,
        private bool $loggingEnabled,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $state = RouteDispatchState::fromRequest($request);
        $state->assertRequest($request);
        $match = $state->match();
        if ($match === null) {
            return $handler->handle($request);
        }

        $serviceIds = $this->selector->select($match);

        try {
            $registered = $this->container->serviceIds();
            foreach ($serviceIds as $serviceId) {
                if (!\in_array($serviceId, $registered, true)) {
                    throw HttpAppDispatchException::withReason(
                        HttpAppDispatchException::REASON_ROUTE_MIDDLEWARE_UNAVAILABLE,
                    );
                }
            }

            $middleware = Pipeline::resolveServices($this->container, $serviceIds);
        } catch (\Throwable) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ROUTE_MIDDLEWARE_UNAVAILABLE);
        }

        foreach ($middleware as $instance) {
            if (\in_array($instance::class, RouteMiddlewareSelector::FORBIDDEN_ROLE_IDS, true)) {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ROUTE_MIDDLEWARE_INVALID);
            }
        }

        $terminal = new class(
            function (ServerRequestInterface $effectiveRequest) use ($state, $match): ResponseInterface {
                if (RouteDispatchState::fromRequest($effectiveRequest) !== $state) {
                    throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_ROUTE_STATE_INVALID);
                }

                $state->assertRequest($effectiveRequest);

                return $this->dispatchAction($match, $effectiveRequest);
            },
        ) implements RequestHandlerInterface {
            /**
             * @param Closure(ServerRequestInterface): ResponseInterface $operation
             */
            public function __construct(private readonly Closure $operation)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->operation)($request);
            }
        };

        $pipeline = new Pipeline(
            $middleware,
            $terminal,
            guardedContinuation: true,
            protectedRequestAttributes: [RouteDispatchState::ATTRIBUTE],
            protectRoutingInputs: true,
        );

        return $pipeline->handle($request);
    }

    private function dispatchAction(RouteMatch $match, ServerRequestInterface $request): ResponseInterface
    {
        $observing = $this->tracingEnabled || $this->metricsEnabled || $this->loggingEnabled;
        $method = $observing ? $this->observeMethod($request) : 'OTHER';
        $span = $this->startSpan($method);
        $startedAt = $this->startDuration();
        $response = null;
        $outcome = 'threw';

        try {
            $response = $this->responseMapper->map($this->invoker->dispatch($match, $request));
            $outcome = 'returned';

            return $response;
        } finally {
            try {
                // Stop timing before inspecting the response or emitting signals.
                $durationMs = $this->stopDuration($startedAt);
                if ($observing) {
                    $status = $this->observeStatus($response);
                    $this->complete($span, $method, $status, $outcome, $durationMs);
                }
            } finally {
                if ($span !== null) {
                    try {
                        $span->end();
                    } catch (\Throwable) {
                        // Span finalization never replaces the action outcome.
                    }
                }
            }
        }
    }

    private function observeMethod(ServerRequestInterface $request): string
    {
        try {
            $method = $request->getMethod();

            return isset(self::METHODS[$method]) ? $method : 'OTHER';
        } catch (\Throwable) {
            return 'OTHER';
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
            $startedAt = $this->stopwatch->start();

            return $startedAt > 0 ? $startedAt : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function stopDuration(?int $startedAt): ?int
    {
        if ($startedAt === null) {
            return null;
        }

        try {
            $durationMs = $this->stopwatch->stop($startedAt);

            return $durationMs >= 0 && $durationMs <= self::MAX_DURATION_MS ? $durationMs : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function observeStatus(?ResponseInterface $response): int|string
    {
        if ($response === null) {
            return 'unknown';
        }

        try {
            $status = $response->getStatusCode();

            return $status >= 100 && $status <= 599 ? $status : 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function complete(
        ?SpanInterface $span,
        string $method,
        int|string $status,
        string $outcome,
        ?int $durationMs,
    ): void {
        if ($span !== null) {
            try {
                $span->setAttribute('outcome', $outcome);
            } catch (\Throwable) {
                // Attempt the remaining signals independently.
            }

            try {
                $span->setAttribute('status', $status);
            } catch (\Throwable) {
                // The span is still finalized by the caller's finally.
            }
        }

        $labels = ['method' => $method, 'status' => $status, 'outcome' => $outcome];

        if ($this->metricsEnabled) {
            try {
                $this->meter->increment(self::METRIC_TOTAL, 1, $labels);
            } catch (\Throwable) {
                // Counter failure does not suppress duration observation.
            }

            if ($durationMs !== null) {
                try {
                    $this->meter->observe(self::METRIC_DURATION, $durationMs, $labels);
                } catch (\Throwable) {
                    // Duration recording cannot change the action outcome.
                }
            }
        }

        if ($this->loggingEnabled) {
            try {
                $this->logger->debug(self::LOG_EVENT, $labels);
            } catch (\Throwable) {
                // Logging failure never affects the completed action outcome.
            }
        }
    }
}
