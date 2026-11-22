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

use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Immutable access policy for explicitly selected development diagnostics.
 *
 * Eligibility is distinct from request authorization. The policy consumes
 * the frozen runtime environment and validated request-local proxy metadata,
 * never process environment or raw forwarding headers.
 *
 * @internal
 */
final readonly class DebugGuard
{
    public const int MAX_CONFIG_PATH_BYTES = 256;

    private const int MAX_IP_ADDRESSES = 64;
    private const int MAX_CONFIG_PREFIXES = 64;
    private const string CONFIG_PATH_PATTERN = '/\A[a-z][a-z0-9_]{0,63}(?:\.[A-Za-z_][A-Za-z0-9_]{0,63})*\z/D';
    private const string TOKEN_PATTERN = '/\A[a-f0-9]{64}\z/D';

    /** @var list<string> */
    private const array OPERATIONS = ['headers', 'modules', 'config', 'cache'];

    /** @var array<string, true> */
    private array $allowedIps;

    /** @var array<string, true> */
    private array $allowedOperations;

    /** @var list<string> */
    private array $configPrefixes;

    /**
     * @param list<mixed> $allowlist
     * @param list<mixed> $enabledOperations
     * @param list<mixed> $configAllowlist
     */
    public function __construct(
        private RuntimeDiagnosticsInput $runtime,
        private ModulePlan $modulePlan,
        private TrustedProxyPolicy $trustedProxy,
        private bool $proxyEnabled,
        private bool $enabled,
        array $allowlist,
        private string $tokenSha256,
        array $enabledOperations,
        array $configAllowlist,
    ) {
        $this->allowedIps = self::validateIps($allowlist);

        if ($this->tokenSha256 !== '' && \preg_match(self::TOKEN_PATTERN, $this->tokenSha256) !== 1) {
            throw new \InvalidArgumentException('http-debug-token-sha256-invalid');
        }

        $this->allowedOperations = self::validateOperations($enabledOperations);
        $this->configPrefixes = self::validateConfigPrefixes($configAllowlist);
    }

    public function isEligible(): bool
    {
        return $this->enabled
            && \in_array($this->runtime->appEnv(), ['local', 'dev'], true)
            && $this->modulePlan->hasEnabledModule('platform.problem-details');
    }

    public function allows(ServerRequestInterface $request, string $operation): bool
    {
        if (
            !$this->isEligible()
            || !isset($this->allowedOperations[$operation])
            || $this->tokenSha256 === ''
        ) {
            return false;
        }

        $clientIp = $this->clientIp($request);
        if ($clientIp === null || !isset($this->allowedIps[$clientIp])) {
            return false;
        }

        $values = $request->getHeader('X-Debug-Token');
        if (!\array_is_list($values) || \count($values) !== 1) {
            return false;
        }

        $token = $values[0];
        if (\preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return false;
        }

        return \hash_equals($this->tokenSha256, \hash('sha256', $token));
    }

    public function allowsConfigKey(string $keyPath): bool
    {
        if (
            !self::isLogicalConfigPath($keyPath)
            || $keyPath === 'http.debug'
            || \str_starts_with($keyPath, 'http.debug.')
        ) {
            return false;
        }

        foreach ($this->configPrefixes as $prefix) {
            if ($keyPath === $prefix || \str_starts_with($keyPath, $prefix . '.')) {
                return true;
            }
        }

        return false;
    }

    private function clientIp(ServerRequestInterface $request): ?string
    {
        $peer = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        if (!\is_string($peer) || TrustedProxyPolicy::canonicalIp($peer) !== $peer) {
            return null;
        }

        if (!$this->trustedProxy->isTrustedPeer($peer)) {
            return $peer;
        }

        if (!$this->proxyEnabled) {
            return null;
        }

        $metadata = $request->getAttribute(TrustedProxyMiddleware::ATTRIBUTE);
        if (!\is_array($metadata)) {
            return null;
        }

        $client = $metadata['client_ip'] ?? null;
        if (!\is_string($client) || TrustedProxyPolicy::canonicalIp($client) !== $client) {
            return null;
        }

        return $client;
    }

    /**
     * @param list<mixed> $allowlist
     * @return array<string, true>
     */
    private static function validateIps(array $allowlist): array
    {
        if (!\array_is_list($allowlist) || \count($allowlist) > self::MAX_IP_ADDRESSES) {
            throw new \InvalidArgumentException('http-debug-allowlist-invalid');
        }

        $addresses = [];
        foreach ($allowlist as $entry) {
            if (!\is_string($entry)) {
                throw new \InvalidArgumentException('http-debug-allowlist-invalid');
            }

            $canonical = TrustedProxyPolicy::canonicalIp($entry);
            if ($canonical === null || $canonical !== $entry || isset($addresses[$canonical])) {
                throw new \InvalidArgumentException('http-debug-allowlist-invalid');
            }

            $addresses[$canonical] = true;
        }

        return $addresses;
    }

    /**
     * @param list<mixed> $operations
     * @return array<string, true>
     */
    private static function validateOperations(array $operations): array
    {
        if (!\array_is_list($operations) || \count($operations) > \count(self::OPERATIONS)) {
            throw new \InvalidArgumentException('http-debug-paths-enabled-invalid');
        }

        $allowed = [];
        foreach ($operations as $operation) {
            if (!\is_string($operation) || !\in_array(
                $operation,
                self::OPERATIONS,
                true,
            ) || isset($allowed[$operation])) {
                throw new \InvalidArgumentException('http-debug-paths-enabled-invalid');
            }

            $allowed[$operation] = true;
        }

        return $allowed;
    }

    /**
     * @param list<mixed> $prefixes
     * @return list<string>
     */
    private static function validateConfigPrefixes(array $prefixes): array
    {
        if (!\array_is_list($prefixes) || \count($prefixes) > self::MAX_CONFIG_PREFIXES) {
            throw new \InvalidArgumentException('http-debug-config-allowlist-invalid');
        }

        $seen = [];
        foreach ($prefixes as $prefix) {
            if (!\is_string($prefix) || !self::isLogicalConfigPath($prefix) || isset($seen[$prefix])) {
                throw new \InvalidArgumentException('http-debug-config-allowlist-invalid');
            }

            $seen[$prefix] = true;
        }

        return \array_keys($seen);
    }

    private static function isLogicalConfigPath(string $path): bool
    {
        return \strlen($path) <= self::MAX_CONFIG_PATH_BYTES
            && \preg_match(self::CONFIG_PATH_PATTERN, $path) === 1;
    }
}
