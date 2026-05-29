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

namespace Coretsia\Contracts\Runtime\Hook;

/**
 * Format-neutral after-unit-of-work hook boundary.
 *
 * This contract represents lifecycle behavior that may run after a runtime
 * finishes processing a unit of work according to owner-defined lifecycle
 * policy.
 *
 * It is usable across HTTP, CLI, worker, scheduler, queue consumer, and custom
 * runtime boundaries.
 *
 * This interface MUST NOT require PSR-7 request objects, PSR-7 response
 * objects, framework HTTP request objects, framework HTTP response objects,
 * CLI concrete input/output objects, queue vendor messages, worker vendor
 * contexts, scheduler vendor contexts, concrete service container objects,
 * platform classes, or integration classes.
 *
 * Hook discovery and execution are runtime-owned. The contracts package does
 * not introduce or own DI tags, tag metadata, hook priority semantics, service
 * ordering, failure handling, config defaults, config rules, providers, or
 * generated artifacts.
 */
interface AfterUowHookInterface
{
    /**
     * Runs after a unit of work.
     *
     * The context array is the normalized exported unit-of-work context
     * produced by the runtime owner. It is a format-neutral lifecycle hook
     * payload and MUST NOT contain transport-specific objects or vendor runtime
     * objects.
     *
     * The result array is the normalized exported unit-of-work result produced
     * by the runtime owner. It is a format-neutral lifecycle hook payload and
     * MUST NOT contain transport-specific objects or vendor runtime objects.
     *
     * Implementations MAY finalize runtime-owned scoped state, stop profiling
     * integration, flush safe summaries, emit safe owner-defined signals, or
     * perform other implementation-owned teardown.
     *
     * Implementations MUST NOT expose raw transport data, raw payloads,
     * credentials, tokens, private customer data, or absolute local paths
     * through diagnostics.
     *
     * @param array<string, mixed> $context Normalized exported unit-of-work
     *                                      context array.
     * @param array<string, mixed> $result Normalized exported unit-of-work
     *                                      result array.
     */
    public function afterUow(
        array $context,
        array $result
    ): void;
}
