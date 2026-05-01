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

namespace Coretsia\Contracts\Observability\Health;

/**
 * Format-neutral health check port.
 *
 * Health endpoint routing, response rendering, aggregation, and runtime
 * discovery are platform-owned. This contracts package does not introduce or
 * own DI tags.
 *
 * Implementations MUST NOT expose raw credentials, tokens, cookies, raw SQL,
 * raw request/response bodies, profile payloads, private customer data, or
 * absolute local paths through the health check name or status.
 */
interface HealthCheckInterface
{
    /**
     * Returns the stable safe health check name.
     *
     * The name SHOULD identify the checked component without exposing raw
     * connection strings, host-specific paths, secrets, tenant ids, user ids,
     * or unbounded runtime values.
     */
    public function name(): string;

    /**
     * Executes the check and returns the normalized health status.
     *
     * Endpoint-specific representation belongs to platform health packages.
     */
    public function check(): HealthStatus;
}
