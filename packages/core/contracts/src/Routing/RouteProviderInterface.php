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
 * This port is the contracts boundary for providing declared routes to
 * routing compilation and runtime owners.
 *
 * The contracts boundary MUST NOT expose route groups, PSR-7 objects,
 * request or response objects, middleware objects, controller objects,
 * service container objects, concrete runtime implementation objects,
 * or generated route artifacts.
 *
 * Concrete providers MAY use owner-specific route declaration helpers
 * internally. All such helpers MUST produce ordinary RouteDefinition
 * instances before crossing this contracts boundary.
 *
 * Providing route declarations MUST NOT require PSR-7 objects, request or
 * response objects, middleware objects, controller objects, service container
 * objects, generated route artifacts, filesystem scanning, PHP attribute
 * scanning, or CLI command behavior.
 *
 * Provider output containing multiple routes MUST be deterministic.
 * Provider-emitted route order defines the declared matching precedence
 * within that provider.
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
     * The returned list MUST contain only RouteDefinition instances,
     * including routes produced by owner-specific declaration helpers.
     *
     * The returned list MUST preserve deterministic provider-emitted
     * route order. This order defines the declared matching precedence
     * within the provider and MUST NOT be reordered by route name.
     *
     * Provider outputs MUST NOT contain duplicate route names.
     *
     * @return list<RouteDefinition>
     */
    public function routes(): array;
}
