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

namespace Coretsia\Platform\Http\Response;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Psr\Http\Message\ResponseInterface;

/**
 * Stateless identity-header finalization for completed PSR-7 responses.
 *
 * HttpKernel invokes this service after the entire PSR-15 pipeline, including
 * any explicitly selected Problem Details error boundary, has returned.
 * Kernel-owned correlation identity is applied before HTTP-owned request
 * identity. The selected request identifier must match the current context
 * value exactly before its response header can be applied.
 *
 * This service neither reads request headers nor creates identities,
 * fallback responses, or completion hooks.
 */
final readonly class HttpResponseFinalizer
{
    private const int MAX_HEADER_NAME_BYTES = 128;

    /** @var array<string, true> */
    private const array PROTECTED_HEADERS = [
        'accept-ranges' => true,
        'access-control-allow-credentials' => true,
        'access-control-allow-headers' => true,
        'access-control-allow-methods' => true,
        'access-control-allow-origin' => true,
        'access-control-expose-headers' => true,
        'access-control-max-age' => true,
        'age' => true,
        'allow' => true,
        'alt-svc' => true,
        'authorization' => true,
        'cache-control' => true,
        'clear-site-data' => true,
        'connection' => true,
        'content-disposition' => true,
        'content-encoding' => true,
        'content-language' => true,
        'content-length' => true,
        'content-range' => true,
        'content-security-policy' => true,
        'content-security-policy-report-only' => true,
        'content-type' => true,
        'cookie' => true,
        'cross-origin-embedder-policy' => true,
        'cross-origin-embedder-policy-report-only' => true,
        'cross-origin-opener-policy' => true,
        'cross-origin-opener-policy-report-only' => true,
        'cross-origin-resource-policy' => true,
        'date' => true,
        'etag' => true,
        'expires' => true,
        'host' => true,
        'last-modified' => true,
        'link' => true,
        'location' => true,
        'nel' => true,
        'origin-agent-cluster' => true,
        'permissions-policy' => true,
        'proxy-authenticate' => true,
        'proxy-authorization' => true,
        'proxy-status' => true,
        'referrer-policy' => true,
        'refresh' => true,
        'report-to' => true,
        'reporting-endpoints' => true,
        'retry-after' => true,
        'server' => true,
        'set-cookie' => true,
        'strict-transport-security' => true,
        'transfer-encoding' => true,
        'vary' => true,
        'www-authenticate' => true,
        'x-content-type-options' => true,
        'x-frame-options' => true,
    ];

    public function __construct(
        private CorrelationIdProviderInterface $correlationIds,
        private bool $enabled,
        private string $headerName,
        private RequestIdPolicy $requestIds,
    ) {
        if (
            \strlen($this->headerName) > self::MAX_HEADER_NAME_BYTES
            || \preg_match('/\A[!#$%&\'\x2a+.^_`|~0-9A-Za-z-]+\z/D', $this->headerName) !== 1
            || isset(self::PROTECTED_HEADERS[\strtolower($this->headerName)])
            || \preg_match('/\A(?:[A-Za-z0-9]+-)*correlation-id\z/iD', $this->headerName) !== 1
        ) {
            throw new \InvalidArgumentException('http-correlation-header-invalid');
        }
    }

    /**
     * Applies correlation and request identity headers in deterministic order.
     * Identity verification and PSR-7 mutation failures propagate unchanged.
     */
    public function finalize(ResponseInterface $response, ?string $selectedRequestId = null): ResponseInterface
    {
        $response = $this->finalizeCorrelation($response);
        $requestId = $this->requestIds->current();

        if ($requestId !== $selectedRequestId) {
            throw new \RuntimeException('http-request-id-context-invalid');
        }

        if ($requestId === null) {
            return $response;
        }

        $finalized = $response->withHeader($this->requestIds->headerName(), $requestId);
        if (!$finalized instanceof ResponseInterface) {
            throw new \RuntimeException('http-request-id-response-invalid');
        }

        return $finalized;
    }

    /**
     * Returns the unchanged response when correlation is disabled or absent.
     * PSR-7 header mutation failures propagate to HttpKernel unchanged.
     */
    private function finalizeCorrelation(ResponseInterface $response): ResponseInterface
    {
        if (!$this->enabled) {
            return $response;
        }

        $correlationId = $this->correlationIds->correlationId();
        if ($correlationId === null) {
            return $response;
        }

        // The Foundation correlation provider admits only canonical Kernel
        // identities. Do not promote arbitrary values from another provider.
        if (\preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $correlationId) !== 1) {
            return $response;
        }

        $finalized = $response->withHeader($this->headerName, $correlationId);
        if (!$finalized instanceof ResponseInterface) {
            throw new \RuntimeException('http-correlation-response-invalid');
        }

        return $finalized;
    }
}
