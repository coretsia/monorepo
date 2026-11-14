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

use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Request-local trusted-proxy metadata adapter for PSR-7 server parameters.
 *
 * Only a canonical immediate peer can authorize parsing of selected forwarding
 * headers. The immutable request attribute contains only safe resolved values,
 * not raw forwarding data or the peer-trust decision itself.
 *
 * @internal
 */
final readonly class TrustedProxyMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE = 'coretsia.http.trusted_proxy';

    public function __construct(
        private TrustedProxyPolicy $policy,
        private bool $enabled,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withoutAttribute(self::ATTRIBUTE);
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $serverParams = $request->getServerParams();
        $peer = $serverParams['REMOTE_ADDR'] ?? null;
        if (!\is_string($peer) || !$this->policy->isTrustedPeer($peer)) {
            return $handler->handle($request);
        }

        $headers = [];
        foreach (
            [
                'forwarded' => 'Forwarded',
                'x-forwarded-for' => 'X-Forwarded-For',
                'x-forwarded-proto' => 'X-Forwarded-Proto',
                'x-forwarded-host' => 'X-Forwarded-Host',
            ] as $name => $header
        ) {
            $values = $request->getHeader($header);
            if ($values !== []) {
                $headers[$name] = $values;
            }
        }

        $metadata = $this->policy->resolve($peer, $headers);
        if ($metadata !== []) {
            $request = $request->withAttribute(self::ATTRIBUTE, $metadata);
        }

        return $handler->handle($request);
    }
}
