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

namespace Coretsia\Platform\Http\Proxy;

/**
 * Immutable trusted-proxy and forwarded-metadata interpretation policy.
 *
 * Only a canonical, configured immediate peer may supply forwarded metadata.
 * Forwarded client chains are traversed from the trusted peer outward, stopping
 * at the first untrusted address; earlier user-controlled entries have no
 * authority. RFC 7239 Forwarded and legacy X-Forwarded-* are mutually exclusive.
 * Malformed, ambiguous, or conflicting forwarded input yields no metadata.
 *
 * The caller owns PSR-7 server-parameter extraction, request attributes, and
 * middleware activation. This policy performs no DNS lookup, container access,
 * logging, shared context writes, or request-local state retention.
 *
 * @internal
 */
final readonly class TrustedProxyPolicy
{
    private const int MAX_TRUSTED_PROXIES = 64;
    private const int MAX_PROXY_ENTRY_BYTES = 96;
    private const int MAX_FORWARDED_BYTES = 8192;
    private const int MAX_FORWARDED_HOPS = 16;
    private const int MAX_HEADER_LINES = 16;
    private const int MAX_HOST_BYTES = 253;

    /** @var list<array{address: string, prefix: int}> */
    private array $trustedNetworks;

    /**
     * @param list<string> $trustedProxies Canonical IP addresses or network CIDRs.
     */
    public function __construct(array $trustedProxies)
    {
        if (!\array_is_list($trustedProxies) || \count($trustedProxies) > self::MAX_TRUSTED_PROXIES) {
            throw new \InvalidArgumentException('http-trusted-proxies-invalid');
        }

        $networks = [];
        foreach ($trustedProxies as $entry) {
            if (!\is_string($entry) || $entry === '' || \strlen($entry) > self::MAX_PROXY_ENTRY_BYTES) {
                throw new \InvalidArgumentException('http-trusted-proxies-invalid');
            }

            $parts = \explode('/', $entry);
            $canonical = self::canonicalIp($parts[0]);
            if (\count($parts) > 2 || $canonical === null || $canonical !== $parts[0]) {
                throw new \InvalidArgumentException('http-trusted-proxies-invalid');
            }

            $packed = \inet_pton($canonical);
            if ($packed === false) {
                throw new \InvalidArgumentException('http-trusted-proxies-invalid');
            }

            $prefix = \strlen($packed) * 8;
            if (isset($parts[1])) {
                if (!\preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/D', $parts[1])) {
                    throw new \InvalidArgumentException('http-trusted-proxies-invalid');
                }

                $prefix = (int) $parts[1];
                if ($prefix > \strlen($packed) * 8 || !self::isNetworkAddress($packed, $prefix)) {
                    throw new \InvalidArgumentException('http-trusted-proxies-invalid');
                }
            }

            $key = \bin2hex($packed) . '/' . $prefix;
            if (isset($networks[$key])) {
                throw new \InvalidArgumentException('http-trusted-proxies-invalid');
            }

            $networks[$key] = ['address' => $packed, 'prefix' => $prefix];
        }

        \ksort($networks, SORT_STRING);
        $this->trustedNetworks = \array_values($networks);
    }

    /**
     * The immediate peer is supplied only from transport server parameters.
     * Forwarding headers must never be used as proof of peer trust.
     */
    public function isTrustedPeer(string $immediatePeer): bool
    {
        $canonical = self::canonicalIp($immediatePeer);

        return $canonical !== null
            && $canonical === $immediatePeer
            && $this->isTrustedAddress($canonical);
    }

    /**
     * Resolves canonical request-local proxy metadata from PSR-7 header arrays.
     *
     * Invalid, ambiguous, or conflicting forwarding metadata is ignored in
     * full rather than published partially. This method does not mutate state.
     *
     * @param array<string, list<string>> $headers
     * @return array{client_ip?: string, scheme?: string, host?: string}
     */
    public function resolve(string $immediatePeer, array $headers): array
    {
        if (!$this->isTrustedPeer($immediatePeer)) {
            return [];
        }

        $values = self::forwardingHeaders($headers);
        if ($values === null) {
            return [];
        }

        $hasForwarded = $values['forwarded'] !== [];
        $hasLegacy = $values['x-forwarded-for'] !== []
            || $values['x-forwarded-proto'] !== []
            || $values['x-forwarded-host'] !== [];

        if ($hasForwarded && $hasLegacy) {
            return [];
        }

        if ($hasForwarded) {
            $elements = self::parseForwarded($values['forwarded']);
            if ($elements === null) {
                return [];
            }

            $nearest = $elements[\count($elements) - 1];
            $result = ['client_ip' => $this->clientFromChain(\array_column($elements, 'for'))];

            if (isset($nearest['proto'])) {
                $result['scheme'] = $nearest['proto'];
            }

            if (isset($nearest['host'])) {
                $result['host'] = $nearest['host'];
            }

            return $result;
        }

        if (!$hasLegacy || $values['x-forwarded-for'] === []) {
            return [];
        }

        $chain = self::parseLegacyFor($values['x-forwarded-for']);
        if ($chain === null) {
            return [];
        }

        $result = ['client_ip' => $this->clientFromChain($chain)];
        foreach (['x-forwarded-proto' => 'scheme', 'x-forwarded-host' => 'host'] as $name => $target) {
            $header = $values[$name];
            if ($header === []) {
                continue;
            }

            // Multiple values cannot safely be attributed to the nearest peer.
            if (\count($header) !== 1 || \str_contains($header[0], ',')) {
                return [];
            }

            $raw = \trim($header[0], " \t");
            $normalized = $target === 'scheme' ? self::scheme($raw) : self::host($raw);
            if ($normalized === null) {
                return [];
            }

            $result[$target] = $normalized;
        }

        return $result;
    }

    /**
     * @param list<string> $chain Canonical addresses, nearest proxy last.
     */
    private function clientFromChain(array $chain): string
    {
        $client = $chain[\count($chain) - 1];

        for ($index = \count($chain) - 2; $index >= 0; --$index) {
            if (!$this->isTrustedAddress($client)) {
                break;
            }

            $client = $chain[$index];
        }

        return $client;
    }

    private function isTrustedAddress(string $address): bool
    {
        $packed = \inet_pton($address);
        if ($packed === false) {
            return false;
        }

        foreach ($this->trustedNetworks as $network) {
            if (\strlen($packed) !== \strlen($network['address'])) {
                continue;
            }

            $fullBytes = \intdiv($network['prefix'], 8);
            $remainingBits = $network['prefix'] % 8;
            if (\substr($packed, 0, $fullBytes) !== \substr($network['address'], 0, $fullBytes)) {
                continue;
            }

            if ($remainingBits === 0) {
                return true;
            }

            $mask = (0xff << (8 - $remainingBits)) & 0xff;
            if ((\ord($packed[$fullBytes]) & $mask) === (\ord($network['address'][$fullBytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    private static function isNetworkAddress(string $packed, int $prefix): bool
    {
        $index = \intdiv($prefix, 8);
        $remainingBits = $prefix % 8;
        if ($remainingBits !== 0) {
            $hostMask = (1 << (8 - $remainingBits)) - 1;
            if ((\ord($packed[$index]) & $hostMask) !== 0) {
                return false;
            }

            ++$index;
        }

        for ($length = \strlen($packed); $index < $length; ++$index) {
            if (\ord($packed[$index]) !== 0) {
                return false;
            }
        }

        return true;
    }

    private static function canonicalIp(string $value): ?string
    {
        if ($value === '' || \strlen($value) > 45) {
            return null;
        }

        $packed = @\inet_pton($value);
        if ($packed === false) {
            return null;
        }

        $canonical = \inet_ntop($packed);

        return $canonical === false ? null : $canonical;
    }

    /**
     * @param array<string, list<string>> $headers
     * @return array<string, list<string>>|null
     */
    private static function forwardingHeaders(array $headers): ?array
    {
        $forwarding = [
            'forwarded' => [],
            'x-forwarded-for' => [],
            'x-forwarded-proto' => [],
            'x-forwarded-host' => [],
        ];
        $bytes = 0;

        foreach ($headers as $name => $values) {
            if (!\is_string($name)) {
                continue;
            }

            $key = \strtolower($name);
            if (!\array_key_exists($key, $forwarding)) {
                continue;
            }

            if (!\is_array($values) || !\array_is_list($values) || $values === []) {
                return null;
            }

            foreach ($values as $value) {
                if (!\is_string($value) || $value === '' || \preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value)) {
                    return null;
                }

                $bytes += \strlen($value);
                if ($bytes > self::MAX_FORWARDED_BYTES || \count($forwarding[$key]) >= self::MAX_HEADER_LINES) {
                    return null;
                }

                $forwarding[$key][] = $value;
            }
        }

        return $forwarding;
    }

    /**
     * @param list<string> $values
     * @return list<array{for: string, proto?: string, host?: string}>|null
     */
    private static function parseForwarded(array $values): ?array
    {
        $parts = self::splitOutsideQuotes(\implode(',', $values), ',');
        if ($parts === null || \count($parts) > self::MAX_FORWARDED_HOPS) {
            return null;
        }

        $elements = [];
        foreach ($parts as $part) {
            $pairs = self::splitOutsideQuotes($part, ';');
            if ($pairs === null || \count($pairs) > 4) {
                return null;
            }

            $fields = [];
            foreach ($pairs as $pair) {
                $equal = \strpos($pair, '=');
                if ($equal === false) {
                    return null;
                }

                $rawName = \substr($pair, 0, $equal);
                $rawValue = \substr($pair, $equal + 1);
                if (
                    $rawName === ''
                    || $rawValue === ''
                    || \trim($rawName, " \t") !== $rawName
                    || \trim($rawValue, " \t") !== $rawValue
                ) {
                    return null;
                }

                $name = \strtolower($rawName);
                if (!\in_array($name, ['for', 'by', 'proto', 'host'], true) || isset($fields[$name])) {
                    return null;
                }

                $value = self::forwardedValue($rawValue);
                if ($value === null || \strlen($value) > self::MAX_HOST_BYTES) {
                    return null;
                }

                $fields[$name] = $value;
            }

            if (!isset($fields['for'])) {
                return null;
            }

            if (isset($fields['by']) && !self::validForwardedBy($fields['by'])) {
                return null;
            }

            $client = self::forwardedNode($fields['for']);
            if ($client === null) {
                return null;
            }

            $element = ['for' => $client];
            if (isset($fields['proto'])) {
                $proto = self::scheme($fields['proto']);
                if ($proto === null) {
                    return null;
                }

                $element['proto'] = $proto;
            }

            if (isset($fields['host'])) {
                $host = self::host($fields['host']);
                if ($host === null) {
                    return null;
                }

                $element['host'] = $host;
            }

            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @param list<string> $values
     * @return list<string>|null
     */
    private static function parseLegacyFor(array $values): ?array
    {
        $parts = \explode(',', \implode(',', $values));
        if (\count($parts) > self::MAX_FORWARDED_HOPS) {
            return null;
        }

        $chain = [];
        foreach ($parts as $part) {
            $canonical = self::canonicalIp(\trim($part, " \t"));
            if ($canonical === null) {
                return null;
            }

            $chain[] = $canonical;
        }

        return $chain;
    }

    private static function forwardedNode(string $value): ?string
    {
        if (\str_starts_with($value, '[')) {
            if (!\preg_match('/\A\[([^]]+)](?::([1-9][0-9]{0,4}))?\z/D', $value, $matches)) {
                return null;
            }

            if (isset($matches[2]) && (int) $matches[2] > 65535) {
                return null;
            }

            $address = self::canonicalIp($matches[1]);

            return $address !== null && \str_contains($address, ':') ? $address : null;
        }

        if (\preg_match('/\A([^:]+):([1-9][0-9]{0,4})\z/D', $value, $matches)) {
            if ((int) $matches[2] > 65535) {
                return null;
            }

            $value = $matches[1];
        }

        $address = self::canonicalIp($value);

        return $address !== null && !\str_contains($address, ':') ? $address : null;
    }

    private static function validForwardedBy(string $value): bool
    {
        if (self::forwardedNode($value) !== null) {
            return true;
        }

        if (
            \preg_match(
                '/\A(?:unknown|_[A-Za-z0-9._-]+)(?::([0-9]{1,5}|_[A-Za-z0-9._-]+))?\z/D',
                $value,
                $matches,
            ) !== 1
        ) {
            return false;
        }

        return !isset($matches[1])
            || \str_starts_with($matches[1], '_')
            || (int) $matches[1] <= 65535;
    }

    private static function scheme(string $value): ?string
    {
        $normalized = \strtolower($value);

        return \in_array($normalized, ['http', 'https'], true) ? $normalized : null;
    }

    private static function host(string $value): ?string
    {
        if ($value === '' || \strlen($value) > self::MAX_HOST_BYTES) {
            return null;
        }

        $value = \strtolower($value);
        if (\str_starts_with($value, '[')) {
            if (!\preg_match('/\A\[([^]]+)](?::([1-9][0-9]{0,4}))?\z/D', $value, $matches)) {
                return null;
            }

            $address = self::canonicalIp($matches[1]);
            if (
                $address === null
                || !\str_contains($address, ':')
                || (isset($matches[2]) && (int) $matches[2] > 65535)
            ) {
                return null;
            }

            return '[' . $address . ']' . (isset($matches[2]) ? ':' . $matches[2] : '');
        }

        $host = $value;
        $port = '';
        if (\str_contains($value, ':')) {
            if (!\preg_match('/\A([^:]+):([1-9][0-9]{0,4})\z/D', $value, $matches) || (int) $matches[2] > 65535) {
                return null;
            }

            $host = $matches[1];
            $port = ':' . $matches[2];
        }

        // Numeric dotted authorities must be canonical IP addresses rather than
        // alternate numeric host spellings interpreted differently by adapters.
        if (\preg_match('/\A[0-9.]+\z/D', $host) && self::canonicalIp($host) !== $host) {
            return null;
        }

        foreach (\explode('.', $host) as $label) {
            if (!\preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $label)) {
                return null;
            }
        }

        return $host . $port;
    }

    /** @return list<string>|null */
    private static function splitOutsideQuotes(string $input, string $delimiter): ?array
    {
        $parts = [];
        $segment = '';
        $quoted = false;
        $escaped = false;

        for ($index = 0, $length = \strlen($input); $index < $length; ++$index) {
            $char = $input[$index];
            if ($escaped) {
                if ($char !== '"' && $char !== '\\') {
                    return null;
                }

                $escaped = false;
            } elseif ($char === '\\') {
                if (!$quoted) {
                    return null;
                }

                $escaped = true;
            } elseif ($char === '"') {
                $quoted = !$quoted;
            } elseif (!$quoted && $char === $delimiter) {
                $part = \trim($segment, " \t");
                if ($part === '') {
                    return null;
                }

                $parts[] = $part;
                $segment = '';
                continue;
            }

            $segment .= $char;
        }

        $part = \trim($segment, " \t");
        if ($quoted || $escaped || $part === '') {
            return null;
        }

        $parts[] = $part;

        return $parts;
    }

    private static function forwardedValue(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if ($value[0] !== '"') {
            return \preg_match('/\A[!#$%&\'\x2a+.^_`|~0-9A-Za-z-]+\z/D', $value) ? $value : null;
        }

        if (!\str_ends_with($value, '"') || \strlen($value) < 3) {
            return null;
        }

        $content = \substr($value, 1, -1);
        $decoded = '';
        for ($index = 0, $length = \strlen($content); $index < $length; ++$index) {
            $char = $content[$index];
            if ($char === '\\') {
                ++$index;
                if ($index >= $length || ($content[$index] !== '"' && $content[$index] !== '\\')) {
                    return null;
                }

                $char = $content[$index];
            } elseif ($char === '"') {
                return null;
            }

            if (\ord($char) < 0x21 || \ord($char) > 0x7e) {
                return null;
            }

            $decoded .= $char;
        }

        return $decoded === '' ? null : $decoded;
    }
}
