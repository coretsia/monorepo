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

namespace Coretsia\Platform\Http\Routing;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Platform\Http\Debug\DebugRouter;
use Coretsia\Platform\Http\Exception\IntentionalErrorException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Stateless terminal dispatcher for explicitly supported system endpoints.
 *
 * Reserved ownership comes from the canonical matcher. Only explicitly
 * implemented declarations are claimed; unmatched requests remain delegated
 * to the terminal handler's safe fallback. Development-only declarations
 * delegate to DebugRouter for exact endpoint claims and authorization.
 *
 * System endpoint and debug decisions are observed independently of each
 * other and of final HTTP request completion.
 *
 * @internal
 */
final readonly class FallbackRouter
{
    public function __construct(
        private FallbackResponses $responses,
        private ClockInterface $clock,
        private MeterPortInterface $meter,
        private bool $metricsEnabled,
        private DebugRouter $debug,
    ) {
    }

    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return null;
        }

        $path = $request->getUri()->getPath();

        try {
            $entry = ReservedPathMatcher::match($path);
        } catch (\InvalidArgumentException $exception) {
            if ($exception->getMessage() !== 'http-reserved-path-invalid') {
                throw $exception;
            }

            return null;
        }

        if ($entry === null) {
            return null;
        }

        if (
            $entry['owner'] === 'platform/http'
            && $entry['pattern'] === '/_debug/'
            && $entry['type'] === 'prefix'
            && $entry['status'] === 'dev_only'
        ) {
            return $this->debug->dispatch($request);
        }

        if ($entry['owner'] === 'platform/http' && $entry['type'] === 'exact' && $entry['status'] === 'active') {
            if ($entry['pattern'] === '/error') {
                $this->intentionalError();
            }

            return match ($entry['pattern']) {
                SystemRouteTable::ALIVE_PATH => $this->served($this->responses->alive(), 'alive'),
                '/ping' => $this->served($this->responses->ping($this->clock->now()->getTimestamp()), 'ping'),
                default => null,
            };
        }

        if ($entry['status'] !== 'reserved') {
            return null;
        }

        if (
            $entry['owner'] === 'platform/health'
            && (
                ($entry['pattern'] === '/health' && $entry['type'] === 'exact')
                || ($entry['pattern'] === '/health/' && $entry['type'] === 'prefix')
            )
        ) {
            return $this->ownerUnavailable($this->responses->healthUnavailable(), 'health');
        }

        if (
            $entry['owner'] === 'platform/metrics'
            && $entry['pattern'] === '/metrics'
            && $entry['type'] === 'exact'
        ) {
            return $this->ownerUnavailable($this->responses->metricsUnavailable(), 'metrics');
        }

        return null;
    }

    /** @param 'ping'|'alive' $operation */
    private function served(ResponseInterface $response, string $operation): ResponseInterface
    {
        $this->recordDecision($operation, 200, 'served');

        return $response;
    }

    /** @param 'health'|'metrics' $operation */
    private function ownerUnavailable(ResponseInterface $response, string $operation): ResponseInterface
    {
        $this->recordDecision($operation, 501, 'owner_unavailable');

        return $response;
    }

    private function intentionalError(): never
    {
        $this->recordDecision('error', 'unknown', 'intentional_error');

        throw IntentionalErrorException::intentional();
    }

    /**
     * @param 'ping'|'alive'|'error'|'health'|'metrics' $operation
     * @param 200|501|'unknown' $status
     * @param 'served'|'owner_unavailable'|'intentional_error' $outcome
     */
    private function recordDecision(string $operation, int|string $status, string $outcome): void
    {
        if (!$this->metricsEnabled) {
            return;
        }

        try {
            $this->meter->increment('http.system_endpoint_total', 1, [
                'operation' => $operation,
                'status' => $status,
                'outcome' => $outcome,
            ]);
        } catch (\Throwable) {
            // An optional counter failure cannot affect the endpoint decision.
        }
    }
}
