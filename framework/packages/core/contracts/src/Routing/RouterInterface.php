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

namespace Coretsia\Contracts\Routing;

/**
 * Format-neutral router matching boundary.
 *
 * This port accepts scalar matching input and returns a normalized route match.
 *
 * It MUST NOT require PSR-7 request objects, PSR-7 response objects, framework
 * HTTP request objects, framework HTTP response objects, middleware objects,
 * route collection objects, route compiler objects, platform classes, or
 * integration classes.
 *
 * The raw path and host arguments are ephemeral transport inputs.
 * Implementations MUST NOT store raw matching input in RouteMatch and MUST NOT
 * emit raw matching input through diagnostics, logs, spans, metrics, or errors
 * unless converted to a safe derivation such as hash(value) or len(value).
 */
interface RouterInterface
{
    /**
     * Matches scalar routing input to a normalized route match.
     *
     * The $path argument represents raw matching input and MUST remain
     * ephemeral. The normalized successful result is RouteMatch, which stores
     * the safe route template instead of the raw path.
     *
     * The $host argument is optional scalar matching input. When present, it
     * MUST remain ephemeral unless a future owner explicitly defines a safe
     * normalized host-template contract.
     *
     * A null return value means no route matched the supplied scalar input.
     *
     * @param non-empty-string $method
     * @param non-empty-string $path
     * @param non-empty-string|null $host
     */
    public function match(string $method, string $path, ?string $host = null): ?RouteMatch;
}
