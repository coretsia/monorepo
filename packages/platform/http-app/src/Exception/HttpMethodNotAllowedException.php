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

namespace Coretsia\Platform\HttpApp\Exception;

/**
 * Deterministic application route method mismatch.
 *
 * The message contains only the fixed reason token. Custom exception state
 * contains only the validated canonical allowed-method list, never the
 * rejected request method, raw path, request object, or previous Throwable.
 */
final class HttpMethodNotAllowedException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_HTTP_405';

    private const string REASON = 'http-app-method-not-allowed';

    /** @var list<non-empty-string> */
    private const array ALLOWED_METHODS = [
        'GET',
        'HEAD',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
        'TRACE',
        'CONNECT',
    ];

    /** @param list<non-empty-string> $allowedMethods */
    private function __construct(
        private readonly array $allowedMethods,
    ) {
        parent::__construct(self::REASON);
    }

    /**
     * @param array<mixed> $allowedMethods
     *
     * @throws \InvalidArgumentException
     */
    public static function methodNotAllowed(array $allowedMethods): self
    {
        if (!\array_is_list($allowedMethods) || $allowedMethods === [] || \count($allowedMethods) > 9) {
            throw new \InvalidArgumentException('http-app-allowed-methods-invalid');
        }

        $validated = [];
        $previous = null;
        foreach ($allowedMethods as $method) {
            if (!\is_string($method) || !\in_array($method, self::ALLOWED_METHODS, true)) {
                throw new \InvalidArgumentException('http-app-allowed-methods-invalid');
            }

            if ($previous !== null && \strcmp($previous, $method) >= 0) {
                throw new \InvalidArgumentException('http-app-allowed-methods-invalid');
            }

            $validated[] = $method;
            $previous = $method;
        }

        return new self($validated);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON;
    }

    /**
     * @return list<non-empty-string>
     */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
