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
 * Format-neutral route definition provider.
 *
 * This port is the contracts boundary for providing declared routes to future
 * routing runtime owners and route compilation owners.
 *
 * Implementations MUST NOT require PSR-7 objects, framework HTTP request or
 * response objects, middleware objects, controller objects, service container
 * objects, platform classes, integration classes, generated route artifacts,
 * filesystem scanning, PHP attribute scanning, or CLI command behavior.
 *
 * Provider output containing multiple routes MUST be deterministic.
 * The expected ordering is by route name ascending using byte-order strcmp.
 */
interface RouteProviderInterface
{
    /**
     * Returns the stable provider id.
     *
     * The provider id MAY be an application id, module id, package id, or
     * another owner-defined stable source identifier.
     *
     * The provider id MUST be stable, non-empty, safe, and single-line.
     * It MUST NOT contain raw paths, raw request data, credentials, tokens,
     * private customer data, or environment-specific bytes.
     *
     * @return non-empty-string
     */
    public function id(): string;

    /**
     * Returns declared route definitions.
     *
     * The returned list MUST contain only RouteDefinition instances.
     *
     * The returned list SHOULD be ordered by:
     *
     * ```text
     * name ascending using byte-order strcmp
     * ```
     *
     * Provider outputs MUST NOT contain duplicate route names.
     *
     * @return list<RouteDefinition>
     */
    public function routes(): array;
}
