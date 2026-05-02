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
 * Format-neutral before-unit-of-work hook boundary.
 *
 * This contract represents lifecycle behavior that may run before a runtime
 * begins processing a unit of work.
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
interface BeforeUowHookInterface
{
    /**
     * Runs before a unit of work.
     *
     * This method intentionally has no parameters so hook behavior remains
     * format-neutral and does not encode transport-specific context.
     *
     * Implementations MAY initialize runtime-owned scoped state, prepare safe
     * observability state, start profiling integration, or perform other
     * implementation-owned preparation.
     *
     * Implementations MUST NOT expose raw transport data, raw payloads,
     * credentials, tokens, private customer data, or absolute local paths
     * through diagnostics.
     */
    public function beforeUow(): void;
}
