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

namespace Coretsia\Contracts\HttpApp;

/**
 * Format-neutral application action invocation boundary.
 *
 * This port invokes an implementation-owned action id with already resolved
 * named arguments.
 *
 * The action id is the stable implementation-owned action reference. In the
 * routing boundary, this is normally the value exposed by RouteMatch::handler()
 * or RouteDefinition::handler().
 *
 * This port MUST NOT require PSR-7 request objects, PSR-7 response objects,
 * framework HTTP request objects, framework HTTP response objects, concrete
 * middleware objects, concrete service container objects, concrete controller
 * base classes, concrete response factories, platform classes, or integration
 * classes.
 *
 * Implementations MAY resolve the stable action id into a callable using
 * runtime-owned mechanisms.
 *
 * The contracts package MUST NOT implement action lookup, controller
 * construction, DI lookup, response creation, exception handling, or transport
 * rendering.
 */
interface ActionInvokerInterface
{
    /**
     * Invokes an application action.
     *
     * The $actionId value MUST be a stable action identifier. It SHOULD match
     * the stable handler/action reference from the matched route.
     *
     * The $arguments map is expected to be the named argument map returned by
     * ArgumentResolverInterface.
     *
     * Raw argument values MUST NOT be emitted through diagnostics, logs, spans,
     * metrics, or errors.
     *
     * The return value is implementation-owned. A runtime adapter MAY convert
     * it into a transport-specific response outside core/contracts.
     *
     * @param non-empty-string $actionId
     * @param array<string,mixed> $arguments
     */
    public function invoke(string $actionId, array $arguments = []): mixed;
}
