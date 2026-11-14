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

namespace Coretsia\Platform\Http\Host;

/**
 * Immutable allowlist policy for effective HTTP request authorities.
 *
 * Configuration accepts only canonical ASCII domain names, IPv4 addresses,
 * bracketed IPv6 literals, and optional explicit decimal ports. Comparison
 * normalizes ASCII case but does not infer a port, resolve DNS, or support
 * wildcard or suffix matching. An empty allowlist denies every host.
 *
 * The caller MUST supply the direct PSR-7 authority or the already-validated
 * host from the trusted-proxy request attribute. Forwarded headers themselves
 * are never read or trusted by this policy.
 *
 * @internal
 */
final readonly class TrustedHostPolicy
{
    private const int MAX_ALLOWED_HOSTS = 64;
    private const int MAX_HOST_BYTES = 253;

    /** @var array<string, true> */
    private array $allowedHosts;

    /**
     * @param list<string> $allowed Canonical host authorities from http.hosts.allowed.
     */
    public function __construct(array $allowed)
    {
        if (!\array_is_list($allowed) || \count($allowed) > self::MAX_ALLOWED_HOSTS) {
            throw new \InvalidArgumentException('http-hosts-allowed-invalid');
        }

        $hosts = [];
        foreach ($allowed as $entry) {
            if (!\is_string($entry)) {
                throw new \InvalidArgumentException('http-hosts-allowed-invalid');
            }

            $canonical = self::normalizeHost($entry);
            if ($canonical === null || $canonical !== $entry || isset($hosts[$canonical])) {
                throw new \InvalidArgumentException('http-hosts-allowed-invalid');
            }

            $hosts[$canonical] = true;
        }

        \ksort($hosts, \SORT_STRING);
        $this->allowedHosts = $hosts;
    }

    /**
     * Checks the already-selected effective authority, never raw forwarding data.
     */
    public function allows(string $effectiveHost): bool
    {
        $canonical = self::normalizeHost($effectiveHost);

        return $canonical !== null && isset($this->allowedHosts[$canonical]);
    }

    /**
     * Normalizes only host syntax; it does not authorize the host's provenance.
     */
    private static function normalizeHost(string $value): ?string
    {
        if ($value === '' || \strlen($value) > self::MAX_HOST_BYTES || \preg_match('/[^\x21-\x7E]/', $value)) {
            return null;
        }

        $value = \strtolower($value);

        if (\str_starts_with($value, '[')) {
            if (\preg_match('/\A\[([^]]+)](?::([1-9][0-9]{0,4}))?\z/D', $value, $matches) !== 1) {
                return null;
            }

            if (isset($matches[2]) && ((int) $matches[2] > 65535 || $matches[2][0] === '0')) {
                return null;
            }

            $packed = @\inet_pton($matches[1]);
            if ($packed === false || \strlen($packed) !== 16) {
                return null;
            }

            $address = \inet_ntop($packed);
            if ($address === false || $address !== $matches[1]) {
                return null;
            }

            return '[' . $address . ']' . (isset($matches[2]) ? ':' . $matches[2] : '');
        }

        $host = $value;
        $port = '';
        if (\str_contains($value, ':')) {
            if (\preg_match('/\A([^:]+):([1-9][0-9]{0,4})\z/D', $value, $matches) !== 1
                || (int) $matches[2] > 65535
            ) {
                return null;
            }

            $host = $matches[1];
            $port = ':' . $matches[2];
        }

        // Reject noncanonical dotted-decimal spellings that adapters can
        // interpret differently from the allowlist (e.g. leading-zero octets).
        if (\preg_match('/\A[0-9.]+\z/D', $host) === 1) {
            $packed = @\inet_pton($host);
            if ($packed === false || \strlen($packed) !== 4 || \inet_ntop($packed) !== $host) {
                return null;
            }

            return $host . $port;
        }

        foreach (\explode('.', $host) as $label) {
            if (\preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $label) !== 1) {
                return null;
            }
        }

        return $host . $port;
    }
}
