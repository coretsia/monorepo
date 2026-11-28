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
 * Deterministic HttpApp action and middleware dispatch failure.
 *
 * The message and custom exception state contain only an admitted reason
 * token. No action id, service id, argument value, return value, reflection
 * diagnostic, or previous Throwable is accepted as exception state.
 *
 * Dispatch failures remain internal server failures handled by the canonical
 * error boundary, not application route misses or method mismatches.
 */
final class HttpAppDispatchException extends \RuntimeException
{
    public const string REASON_HANDLER_UNMAPPED = 'http-app-handler-unmapped';
    public const string REASON_HANDLER_UNAVAILABLE = 'http-app-handler-unavailable';
    public const string REASON_HANDLER_INVALID = 'http-app-handler-invalid';
    public const string REASON_ARGUMENT_UNRESOLVABLE = 'http-app-argument-unresolvable';
    public const string REASON_ARGUMENT_INVALID = 'http-app-argument-invalid';
    public const string REASON_RESPONSE_UNSUPPORTED = 'http-app-response-unsupported';
    public const string REASON_RESPONSE_DISABLED = 'http-app-response-disabled';
    public const string REASON_RESPONSE_MAPPING_FAILED = 'http-app-response-mapping-failed';
    public const string REASON_ROUTE_STATE_INVALID = 'http-app-route-state-invalid';
    public const string REASON_ROUTE_INPUT_CHANGED = 'http-app-route-input-changed';
    public const string REASON_ROUTE_MIDDLEWARE_INVALID = 'http-app-route-middleware-invalid';
    public const string REASON_ROUTE_MIDDLEWARE_UNAVAILABLE = 'http-app-route-middleware-unavailable';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_HANDLER_UNMAPPED => true,
        self::REASON_HANDLER_UNAVAILABLE => true,
        self::REASON_HANDLER_INVALID => true,
        self::REASON_ARGUMENT_UNRESOLVABLE => true,
        self::REASON_ARGUMENT_INVALID => true,
        self::REASON_RESPONSE_UNSUPPORTED => true,
        self::REASON_RESPONSE_DISABLED => true,
        self::REASON_RESPONSE_MAPPING_FAILED => true,
        self::REASON_ROUTE_STATE_INVALID => true,
        self::REASON_ROUTE_INPUT_CHANGED => true,
        self::REASON_ROUTE_MIDDLEWARE_INVALID => true,
        self::REASON_ROUTE_MIDDLEWARE_UNAVAILABLE => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        parent::__construct($reason);
    }

    public static function withReason(string $reason): self
    {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('http-app-dispatch-reason-invalid');
        }

        return new self($reason);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
