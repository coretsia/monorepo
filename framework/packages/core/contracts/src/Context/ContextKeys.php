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

namespace Coretsia\Contracts\Context;

/**
 * Canonical public context key identifiers.
 *
 * This contract-level registry defines stable context key names that may be
 * shared across packages for context reads, validation, diagnostics, and
 * deterministic cross-package coordination.
 *
 * It defines vocabulary only. Importing this class does not grant write
 * ownership over context values. Context storage, safe-write validation,
 * lifecycle writes, reset behavior, propagation, logging, tracing, and export
 * policy remain owned by their respective runtime packages.
 */
final class ContextKeys
{
    public const string CORRELATION_ID = 'correlation_id';
    public const string UOW_ID = 'uow_id';
    public const string UOW_TYPE = 'uow_type';

    public const string CLIENT_IP = 'client_ip';
    public const string SCHEME = 'scheme';
    public const string HOST = 'host';
    public const string PATH = 'path';
    public const string USER_AGENT = 'user_agent';

    public const string REQUEST_ID = 'request_id';
    public const string PATH_TEMPLATE = 'path_template';
    public const string HTTP_RESPONSE_FORMAT = 'http_response_format';
    public const string ACTOR_ID = 'actor_id';
    public const string TENANT_ID = 'tenant_id';

    /**
     * @var list<non-empty-string>
     */
    private const array ALL = [
        self::CORRELATION_ID,
        self::UOW_ID,
        self::UOW_TYPE,
        self::CLIENT_IP,
        self::SCHEME,
        self::HOST,
        self::PATH,
        self::USER_AGENT,
        self::REQUEST_ID,
        self::PATH_TEMPLATE,
        self::HTTP_RESPONSE_FORMAT,
        self::ACTOR_ID,
        self::TENANT_ID,
    ];

    private function __construct()
    {
    }

    public static function isKnown(string $key): bool
    {
        return \in_array($key, self::ALL, true);
    }

    /**
     * Returns all canonical public context key identifiers.
     *
     * Ordering is stable and intentionally deterministic.
     *
     * @return list<non-empty-string>
     */
    public static function all(): array
    {
        return self::ALL;
    }
}
