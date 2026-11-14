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

use Coretsia\Platform\Http\Exception\TrustedHostRejectedException;
use Coretsia\Platform\Http\Host\TrustedHostPolicy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Exact effective-authority enforcement without forwarding-header parsing.
 *
 * The trusted proxy attribute is authoritative only when the corresponding
 * proxy middleware is enabled and ordered before this middleware. Otherwise
 * the direct PSR-7 request authority is validated independently.
 *
 * @internal
 */
final readonly class TrustedHostMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TrustedHostPolicy $policy,
        private bool $enabled,
        private bool $proxyEnabled,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $effectiveHost = null;
        if ($this->proxyEnabled) {
            $proxy = $request->getAttribute(TrustedProxyMiddleware::ATTRIBUTE);
            if (\is_array($proxy) && \is_string($proxy['client_ip'] ?? null)
                && \is_string($proxy['host'] ?? null)
            ) {
                $effectiveHost = $proxy['host'];
            }
        }

        if ($effectiveHost === null) {
            $authority = $request->getUri()->getAuthority();
            $hostHeader = $request->getHeaderLine('Host');

            if ($authority !== '' && $hostHeader !== ''
                && \strcasecmp($authority, $hostHeader) !== 0
            ) {
                throw TrustedHostRejectedException::rejected();
            }

            $effectiveHost = $authority !== '' ? $authority : $hostHeader;
        }

        if (!$this->policy->allows($effectiveHost)) {
            throw TrustedHostRejectedException::rejected();
        }

        return $handler->handle($request);
    }
}
