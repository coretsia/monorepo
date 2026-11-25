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

namespace Coretsia\Foundation\Routing;

/**
 * Canonical versioned system-route declaration registry.
 *
 * All declaration statuses represent reserved route ownership.
 * Declarations do not activate endpoint capabilities, authorize access,
 * or determine runtime dispatch behavior.
 *
 * Exact declarations represent complete case-sensitive paths.
 * Prefix declarations represent slash-terminated namespace boundaries.
 */
final class SystemRouteTable
{
    public const int SCHEMA_VERSION = 1;

    public const string ALIVE_PATH = '/_alive';

    /**
     * @var list<array{
     *     owner: string,
     *     pattern: string,
     *     status: 'active'|'reserved'|'dev_only',
     *     type: 'exact'|'prefix'
     * }>
     */
    private const array ENTRIES = [
        [
            'owner' => 'platform/http',
            'pattern' => self::ALIVE_PATH,
            'status' => 'active',
            'type' => 'exact',
        ],
        [
            'owner' => 'platform/http',
            'pattern' => '/_debug',
            'status' => 'dev_only',
            'type' => 'exact',
        ],
        [
            'owner' => 'platform/http',
            'pattern' => '/_debug/',
            'status' => 'dev_only',
            'type' => 'prefix',
        ],
        [
            'owner' => 'platform/http',
            'pattern' => '/error',
            'status' => 'active',
            'type' => 'exact',
        ],
        [
            'owner' => 'platform/health',
            'pattern' => '/health',
            'status' => 'reserved',
            'type' => 'exact',
        ],
        [
            'owner' => 'platform/health',
            'pattern' => '/health/',
            'status' => 'reserved',
            'type' => 'prefix',
        ],
        [
            'owner' => 'platform/metrics',
            'pattern' => '/metrics',
            'status' => 'reserved',
            'type' => 'exact',
        ],
        [
            'owner' => 'platform/http',
            'pattern' => '/ping',
            'status' => 'active',
            'type' => 'exact',
        ],
    ];

    private function __construct()
    {
    }

    /**
     * Returns canonical declarations in stable byte-order.
     *
     * @return list<array{
     *     owner: string,
     *     pattern: string,
     *     status: 'active'|'reserved'|'dev_only',
     *     type: 'exact'|'prefix'
     * }>
     */
    public static function entries(): array
    {
        return self::ENTRIES;
    }
}
