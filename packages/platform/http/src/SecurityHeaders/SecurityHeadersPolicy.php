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

namespace Coretsia\Platform\Http\SecurityHeaders;

/**
 * Immutable HTTP-owned response security-header configuration policy.
 *
 * All configured values are validated independently of enablement. The
 * ordered map contains only enabled, non-empty header applications.
 * Response mutation belongs exclusively to HttpResponseFinalizer.
 *
 * @internal
 */
final readonly class SecurityHeadersPolicy
{
    private const int MAX_OPTIONAL_HEADER_BYTES = 4096;

    /** @var list<string> */
    private const array REFERRER_POLICIES = [
        'no-referrer',
        'no-referrer-when-downgrade',
        'origin',
        'origin-when-cross-origin',
        'same-origin',
        'strict-origin',
        'strict-origin-when-cross-origin',
        'unsafe-url',
    ];

    /** @var array<string, string> */
    private array $headers;

    public function __construct(
        bool $enabled,
        string $xContentTypeOptions,
        string $xFrameOptions,
        string $referrerPolicy,
        string $permissionsPolicy,
        string $csp,
    ) {
        if ($xContentTypeOptions !== 'nosniff') {
            throw new \InvalidArgumentException('http-security-headers-x-content-type-options-invalid');
        }

        if ($xFrameOptions !== 'deny' && $xFrameOptions !== 'sameorigin') {
            throw new \InvalidArgumentException('http-security-headers-x-frame-options-invalid');
        }

        if (!\in_array($referrerPolicy, self::REFERRER_POLICIES, true)) {
            throw new \InvalidArgumentException('http-security-headers-referrer-policy-invalid');
        }

        if (!self::isValidOptionalHeader($permissionsPolicy)) {
            throw new \InvalidArgumentException('http-security-headers-permissions-policy-invalid');
        }

        if (!self::isValidOptionalHeader($csp)) {
            throw new \InvalidArgumentException('http-security-headers-csp-invalid');
        }

        $headers = [];
        if ($enabled) {
            $headers = [
                'X-Content-Type-Options' => $xContentTypeOptions,
                'X-Frame-Options' => $xFrameOptions === 'deny' ? 'DENY' : 'SAMEORIGIN',
                'Referrer-Policy' => $referrerPolicy,
            ];

            if ($permissionsPolicy !== '') {
                $headers['Permissions-Policy'] = $permissionsPolicy;
            }

            if ($csp !== '') {
                $headers['Content-Security-Policy'] = $csp;
            }
        }

        $this->headers = $headers;
    }

    /**
     * Returns header applications in their canonical mutation order.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    private static function isValidOptionalHeader(string $value): bool
    {
        return $value === '' || (
            \strlen($value) <= self::MAX_OPTIONAL_HEADER_BYTES
                && \trim($value) === $value
                && \preg_match('/[^\x20-\x7E]/', $value) === 0
        );
    }
}
