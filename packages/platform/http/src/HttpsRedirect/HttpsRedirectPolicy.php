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

namespace Coretsia\Platform\Http\HttpsRedirect;

use Coretsia\Platform\Http\Host\TrustedHostPolicy;

/**
 * Immutable HTTP-owned HTTPS redirect configuration policy.
 *
 * The destination authority comes exclusively from explicit configuration.
 * Every non-empty authority is validated using the canonical trusted-host
 * grammar, independently of enablement. Scheme resolution, request handling,
 * and response construction belong to the HTTPS redirect middleware.
 *
 * @internal
 */
final readonly class HttpsRedirectPolicy
{
    /** @var list<string> */
    private const array SUPPORTED_METHODS = [
        'GET',
        'HEAD',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ];

    /** @var array<string, true> */
    private array $allowedMethods;

    /**
     * @param list<string> $allowMethods Canonical HTTP methods from http.https_redirect.allow_methods.
     */
    public function __construct(
        private bool $enabled,
        private int $status,
        array $allowMethods,
        private string $targetAuthority,
    ) {
        if ($this->status !== 307 && $this->status !== 308) {
            throw new \InvalidArgumentException('http-https-redirect-status-invalid');
        }

        if (!\array_is_list($allowMethods)) {
            throw new \InvalidArgumentException('http-https-redirect-allow-methods-invalid');
        }

        $methods = [];
        foreach ($allowMethods as $method) {
            if (!\is_string($method) || !\in_array(
                $method,
                self::SUPPORTED_METHODS,
                true,
            ) || isset($methods[$method])) {
                throw new \InvalidArgumentException('http-https-redirect-allow-methods-invalid');
            }

            $methods[$method] = true;
        }

        if ($this->targetAuthority === '') {
            if ($this->enabled) {
                throw new \InvalidArgumentException('http-https-redirect-target-authority-invalid');
            }
        } else {
            try {
                new TrustedHostPolicy([$this->targetAuthority]);
            } catch (\InvalidArgumentException) {
                throw new \InvalidArgumentException('http-https-redirect-target-authority-invalid');
            }
        }

        $this->allowedMethods = $methods;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * Checks exact method membership. An empty allowlist denies every method.
     */
    public function allowsMethod(string $method): bool
    {
        return isset($this->allowedMethods[$method]);
    }

    public function targetAuthority(): string
    {
        return $this->targetAuthority;
    }
}
