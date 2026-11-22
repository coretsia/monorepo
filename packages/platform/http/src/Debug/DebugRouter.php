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

namespace Coretsia\Platform\Http\Debug;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Platform\Http\Exception\DebugForbiddenException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Stateless terminal dispatcher for explicitly supported development endpoints.
 *
 * FallbackRouter establishes canonical development-only declaration ownership.
 * Exact original URI paths remain mandatory for endpoint claims. DebugGuard
 * owns eligibility and access policy; diagnostic handlers own safe projections.
 *
 * Endpoint decisions are observed independently of final HTTP request
 * completion, response finalization and error rendering.
 *
 * @internal
 */
final readonly class DebugRouter
{
    public function __construct(
        private DebugGuard $guard,
        private DebugHeadersHandler $headers,
        private DebugModuleListHandler $modules,
        private DebugConfigHandler $config,
        private DebugCacheHandler $cache,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private bool $metricsEnabled,
        private bool $accessLogEnabled,
    ) {
    }

    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        if ($request->getMethod() !== 'GET') {
            return null;
        }

        $operation = match ($request->getUri()->getPath()) {
            '/_debug/headers' => 'headers',
            '/_debug/modules' => 'modules',
            '/_debug/config' => 'config',
            '/_debug/cache' => 'cache',
            default => null,
        };

        if ($operation === null || !$this->guard->isEligible()) {
            return null;
        }

        if (!$this->guard->allows($request, $operation)) {
            $this->recordDecision($operation, 'blocked');

            throw DebugForbiddenException::forbidden();
        }

        $response = match ($operation) {
            'headers' => $this->headers->handle($request),
            'modules' => $this->modules->handle(),
            'config' => $this->dispatchConfig($request),
            'cache' => $this->cache->handle(),
        };

        if ($response === null) {
            return null;
        }

        $this->recordDecision($operation, 'allowed');

        return $response;
    }

    private function dispatchConfig(ServerRequestInterface $request): ?ResponseInterface
    {
        try {
            return $this->config->handle($request);
        } catch (DebugForbiddenException $exception) {
            $this->recordDecision('config', 'blocked');

            throw $exception;
        }
    }

    /**
     * @param 'headers'|'modules'|'config'|'cache' $operation
     * @param 'allowed'|'blocked' $outcome
     */
    private function recordDecision(string $operation, string $outcome): void
    {
        if ($this->metricsEnabled) {
            try {
                $this->meter->increment('http.debug_total', 1, [
                    'operation' => $operation,
                    'outcome' => $outcome,
                ]);
            } catch (\Throwable) {
                // An optional counter failure cannot affect the endpoint decision.
            }
        }

        if ($outcome === 'blocked' && $this->accessLogEnabled) {
            try {
                $this->logger->warning('http.debug.blocked', [
                    'operation' => $operation,
                ]);
            } catch (\Throwable) {
                // Logging is isolated independently of the debug counter.
            }
        }
    }
}
