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

namespace Coretsia\Platform\Http\Maintenance;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Platform\Http\Exception\MaintenanceStateUnavailableException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Stateless maintenance short-circuit using shared application-local state.
 *
 * Static participation and runtime activation are independent. Unavailable
 * state fails closed except for the exact downstream liveness bypass.
 * Maintenance decisions are observed independently of final HTTP completion.
 * Response finalization and the selected error boundary retain ownership of
 * their existing responsibilities.
 *
 * @internal
 */
final readonly class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MaintenanceStateStore $state,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private bool $enabled,
        private int $retryAfterDefaultSeconds,
        private bool $metricsEnabled,
        private bool $accessLogEnabled,
    ) {
        if ($this->retryAfterDefaultSeconds < 1 || $this->retryAfterDefaultSeconds > 86400) {
            throw new \InvalidArgumentException('http-maintenance-retry-after-invalid');
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        try {
            $snapshot = $this->state->read();
        } catch (MaintenanceStateUnavailableException) {
            $this->warn('http.maintenance.state_unavailable', 'state-unavailable');
            $snapshot = ['enabled' => true, 'messageState' => 'ignored', 'message' => null];
        }

        if (!$snapshot['enabled']) {
            return $handler->handle($request);
        }

        if ($snapshot['messageState'] === 'invalid') {
            $this->warn('http.maintenance.message_invalid', 'invalid-message');
        }

        if ($request->getUri()->getPath() === SystemRouteTable::ALIVE_PATH) {
            $this->recordDecision('bypassed');

            return $handler->handle($request);
        }

        $response = $this->maintenanceResponse($request, $snapshot['message']);
        $this->recordDecision('served');

        return $response;
    }

    private function maintenanceResponse(
        ServerRequestInterface $request,
        ?MaintenanceMessage $message,
    ): ResponseInterface {
        $retryAfter = $message?->retryAfterSeconds() ?? $this->retryAfterDefaultSeconds;
        $body = $request->getMethod() === 'HEAD'
            ? ''
            : ($message?->message() ?? 'Service Unavailable') . "\n";

        $response = $this->responses->createResponse(503);
        foreach (
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
                'Retry-After' => (string) $retryAfter,
            ] as $name => $value
        ) {
            $updated = $response->withHeader($name, $value);
            if (!$updated instanceof ResponseInterface) {
                throw new \RuntimeException('http-maintenance-response-invalid');
            }

            $response = $updated;
        }

        $updated = $response->withBody($this->streams->createStream($body));
        if (!$updated instanceof ResponseInterface) {
            throw new \RuntimeException('http-maintenance-response-invalid');
        }

        return $updated;
    }

    /** @param 'served'|'bypassed' $outcome */
    private function recordDecision(string $outcome): void
    {
        if ($this->metricsEnabled) {
            try {
                $this->meter->increment('http.maintenance_total', 1, ['outcome' => $outcome]);
            } catch (\Throwable) {
                // An optional counter failure cannot affect maintenance behavior.
            }
        }

        if ($this->accessLogEnabled && $outcome === 'served') {
            try {
                $this->logger->info('http.maintenance.served', ['outcome' => 'served', 'status' => 503]);
            } catch (\Throwable) {
                // Logging remains isolated independently of the counter.
            }
        }
    }

    /**
     * @param 'http.maintenance.message_invalid'|'http.maintenance.state_unavailable' $event
     * @param 'invalid-message'|'state-unavailable' $reason
     */
    private function warn(string $event, string $reason): void
    {
        if (!$this->accessLogEnabled) {
            return;
        }

        try {
            $this->logger->warning($event, ['reason' => $reason]);
        } catch (\Throwable) {
            // A warning failure must not affect the selected maintenance path.
        }
    }
}
