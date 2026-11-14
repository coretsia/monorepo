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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Optional, bounded request-local enrichment of Foundation ContextStore.
 *
 * Proxy data is consumed only from the reserved middleware attribute in the
 * canonical trusted-proxy-first pipeline. Direct request/server values are
 * validated independently. Stored raw path and agent values are in-process
 * context, never safe-by-default diagnostic or observability fields.
 *
 * @internal
 */
final readonly class RequestContextMiddleware implements MiddlewareInterface
{
    private const int MAX_HOST_BYTES = 253;
    private const int MAX_PATH_BYTES = 2048;
    private const int MAX_USER_AGENT_BYTES = 512;

    public function __construct(
        private ContextStore $context,
        private bool $enabled,
        private bool $proxyEnabled,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $serverParams = $request->getServerParams();
        $clientIp = self::canonicalIp($serverParams['REMOTE_ADDR'] ?? null);
        $scheme = self::canonicalScheme($request->getUri()->getScheme());
        $host = self::directHost($request);

        if ($this->proxyEnabled) {
            $proxy = $request->getAttribute(TrustedProxyMiddleware::ATTRIBUTE);
            if (\is_array($proxy) && \is_string($proxy['client_ip'] ?? null)) {
                $validatedIp = self::canonicalIp($proxy['client_ip']);
                if ($validatedIp !== null && $validatedIp === $proxy['client_ip']) {
                    $clientIp = $validatedIp;
                    if (isset($proxy['scheme'])) {
                        $validatedScheme = self::canonicalScheme($proxy['scheme']);
                        if ($validatedScheme === $proxy['scheme']) {
                            $scheme = $validatedScheme;
                        }
                    }

                    if (isset($proxy['host']) && \is_string($proxy['host'])) {
                        $validatedHost = self::canonicalHost($proxy['host']);
                        if ($validatedHost === $proxy['host']) {
                            $host = $validatedHost;
                        }
                    }
                }
            }
        }

        $path = $request->getUri()->getPath();
        $userAgentHeaders = $request->getHeader('User-Agent');
        $userAgent = \count($userAgentHeaders) === 1
            ? self::boundedText($userAgentHeaders[0], self::MAX_USER_AGENT_BYTES)
            : null;

        foreach (
            [
                ContextKeys::CLIENT_IP => $clientIp,
                ContextKeys::SCHEME => $scheme,
                ContextKeys::HOST => $host,
                ContextKeys::PATH => self::boundedText($path, self::MAX_PATH_BYTES),
                ContextKeys::USER_AGENT => $userAgent,
            ] as $key => $value
        ) {
            if ($value === null) {
                continue;
            }

            try {
                $this->context->set($key, $value);
            } catch (\Throwable) {
                // Optional request metadata must not weaken the context policy.
            }
        }

        return $handler->handle($request);
    }

    private static function canonicalIp(mixed $value): ?string
    {
        if (!\is_string($value) || $value === '' || \strlen($value) > 45) {
            return null;
        }

        $packed = @\inet_pton($value);
        if ($packed === false) {
            return null;
        }

        $canonical = \inet_ntop($packed);

        return $canonical === $value ? $canonical : null;
    }

    private static function canonicalScheme(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $scheme = \strtolower($value);

        return $scheme === 'http' || $scheme === 'https' ? $scheme : null;
    }

    private static function directHost(ServerRequestInterface $request): ?string
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $port = $uri->getPort();
        $header = $request->getHeader('Host');

        if (\count($header) > 1) {
            return null;
        }

        $authority = $host !== '' ? $host . ($port !== null ? ':' . $port : '') : '';
        if ($header !== [] && $authority !== '' && \strcasecmp($header[0], $authority) !== 0) {
            return null;
        }

        return self::canonicalHost($authority !== '' ? $authority : ($header[0] ?? ''));
    }

    private static function canonicalHost(mixed $value): ?string
    {
        if (!\is_string($value) || $value === '' || \strlen($value) > self::MAX_HOST_BYTES
            || \preg_match('/[^\x21-\x7E]/', $value) === 1
        ) {
            return null;
        }

        $host = \strtolower($value);
        if (\str_starts_with($host, '[')) {
            if (\preg_match('/\A\[([^]]+)](?::([1-9][0-9]{0,4}))?\z/D', $host, $matches) !== 1
                || (isset($matches[2]) && (int) $matches[2] > 65535)
            ) {
                return null;
            }

            $packed = @\inet_pton($matches[1]);
            $canonical = $packed !== false ? \inet_ntop($packed) : false;
            if ($packed === false || \strlen($packed) !== 16 || $canonical !== $matches[1]) {
                return null;
            }

            return '[' . $canonical . ']' . (isset($matches[2]) ? ':' . $matches[2] : '');
        }

        $port = '';
        if (\str_contains($host, ':')) {
            if (\preg_match('/\A([^:]+):([1-9][0-9]{0,4})\z/D', $host, $matches) !== 1
                || (int) $matches[2] > 65535
            ) {
                return null;
            }

            $host = $matches[1];
            $port = ':' . $matches[2];
        }

        if (\preg_match('/\A[0-9.]+\z/D', $host) === 1) {
            $ip = self::canonicalIp($host);

            return $ip === null ? null : $ip . $port;
        }

        foreach (\explode('.', $host) as $label) {
            if (\preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $label) !== 1) {
                return null;
            }
        }

        return $host . $port;
    }

    private static function boundedText(string $value, int $maxBytes): ?string
    {
        return $value !== '' && \strlen($value) <= $maxBytes
        && \preg_match('/[\x00-\x1F\x7F]/', $value) !== 1
            ? $value : null;
    }
}
