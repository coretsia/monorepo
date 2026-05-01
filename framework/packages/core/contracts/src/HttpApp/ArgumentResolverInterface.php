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

use Coretsia\Contracts\Routing\RouteMatch;

/**
 * Format-neutral action argument resolution boundary.
 *
 * This port resolves named invocation arguments from a stable action id and a
 * normalized RouteMatch.
 *
 * The action id is the stable implementation-owned action reference. In the
 * routing boundary, this is normally the value exposed by RouteMatch::handler()
 * or RouteDefinition::handler().
 *
 * This port MUST NOT require PSR-7 request objects, PSR-7 response objects,
 * framework HTTP request objects, framework HTTP response objects, raw headers,
 * raw cookies, raw request bodies, raw response bodies, concrete service
 * container objects, concrete router objects, platform classes, or integration
 * classes.
 *
 * Implementations MAY use reflection, RouteMatch data, safe context metadata,
 * runtime-owned resolver policy, and runtime-owned container lookups internally.
 *
 * Diagnostics for argument resolution MUST NOT print or log raw argument
 * values. Safe diagnostics MAY use safe derivations such as hash(value),
 * len(value), or type(value).
 */
interface ArgumentResolverInterface
{
    /**
     * Resolves named arguments for action invocation.
     *
     * The $actionId value MUST be a stable action identifier. It SHOULD match
     * the stable handler/action reference from the matched route.
     *
     * The $context map is optional safe context metadata. It SHOULD be json-like
     * whenever possible and MUST NOT contain raw headers, cookies, request
     * bodies, response bodies, credentials, tokens, raw SQL, transport objects,
     * service container objects, or private customer data.
     *
     * Returned argument values are implementation-owned runtime invocation
     * values. They MUST NOT be emitted through diagnostics, logs, spans,
     * metrics, or errors as raw values.
     *
     * @param non-empty-string $actionId
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    public function resolve(string $actionId, RouteMatch $match, array $context = []): array;
}
