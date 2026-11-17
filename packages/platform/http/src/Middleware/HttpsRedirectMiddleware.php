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

namespace Coretsia\Platform\Http\Middleware;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Platform\Http\HttpsRedirect\HttpsRedirectPolicy;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Stateless HTTPS redirect using an explicitly configured target authority.
 *
 * Effective scheme resolution consumes only the canonical trusted-proxy
 * handoff when enabled, otherwise the direct PSR-7 URI scheme. Encoded path
 * and query components are validated without decoding or reconstruction.
 * Redirect decisions are observed independently of final HTTP completion.
 *
 * @internal
 */
final readonly class HttpsRedirectMiddleware implements MiddlewareInterface
{
    public function __construct(
        private HttpsRedirectPolicy $policy,
        private ResponseFactoryInterface $responses,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private bool $proxyEnabled,
        private bool $metricsEnabled,
        private bool $accessLogEnabled,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->policy->enabled()) {
            return $handler->handle($request);
        }

        $uri = $request->getUri();
        $location = null;
        if ($this->effectiveScheme($request, $uri) === 'http' && $this->policy->allowsMethod($request->getMethod())) {
            $location = $this->location($uri);
        }

        if ($location === null) {
            $this->recordDecision('skipped');

            return $handler->handle($request);
        }

        $response = $this->responses->createResponse($this->policy->status())->withHeader('Location', $location);
        if (!$response instanceof ResponseInterface) {
            throw new \RuntimeException('http-https-redirect-response-invalid');
        }

        $this->recordDecision('redirected');

        return $response;
    }

    private function effectiveScheme(ServerRequestInterface $request, UriInterface $uri): string
    {
        if ($this->proxyEnabled) {
            $proxy = $request->getAttribute(TrustedProxyMiddleware::ATTRIBUTE);
            if (\is_array($proxy) && \is_string($proxy['client_ip'] ?? null) && $proxy['client_ip'] !== '') {
                $scheme = $proxy['scheme'] ?? null;
                if ($scheme === 'http' || $scheme === 'https') {
                    return $scheme;
                }
            }
        }

        return $uri->getScheme();
    }

    private function location(UriInterface $uri): ?string
    {
        $path = $uri->getPath();
        $path = $path === '' ? '/' : $path;
        $query = $uri->getQuery();

        // RFC 3986 pchar plus slash; query additionally permits question mark.
        // Reject every raw byte outside the grammar and every malformed escape.
        if (!\str_starts_with($path, '/') || \str_starts_with($path, '//')
            || \preg_match('#[^A-Za-z0-9._~!$&\'()*+,;=:@/%-]|%(?![0-9A-Fa-f]{2})#', $path) !== 0
            || \preg_match('#[^A-Za-z0-9._~!$&\'()*+,;=:@/?%-]|%(?![0-9A-Fa-f]{2})#', $query) !== 0
        ) {
            return null;
        }

        return 'https://' . $this->policy->targetAuthority() . $path . ($query !== '' ? '?' . $query : '');
    }

    /** @param 'redirected'|'skipped' $outcome */
    private function recordDecision(string $outcome): void
    {
        if ($this->metricsEnabled) {
            try {
                $this->meter->increment('http.https_redirect_total', 1, ['outcome' => $outcome]);
            } catch (\Throwable) {
                // An optional counter failure cannot affect the redirect decision.
            }
        }

        if ($this->accessLogEnabled) {
            $context = ['outcome' => $outcome];
            if ($outcome === 'redirected') {
                $context['status'] = $this->policy->status();
            }

            try {
                $this->logger->info('http.https_redirect.decided', $context);
            } catch (\Throwable) {
                // Logging is isolated independently of the redirect counter.
            }
        }
    }
}
