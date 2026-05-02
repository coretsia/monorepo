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

namespace Coretsia\Contracts\Runtime;

/**
 * Minimal reset boundary for stateful services.
 *
 * This contract is intended for long-running runtimes that need to prevent
 * mutable unit-of-work-local state from leaking into the next unit of work.
 *
 * Implementations own their own mutable state and know how to clear it.
 *
 * This interface MUST NOT require PSR-7 request objects, PSR-7 response
 * objects, framework HTTP request objects, framework HTTP response objects,
 * CLI concrete input/output objects, queue vendor messages, worker vendor
 * contexts, scheduler vendor contexts, concrete service container objects,
 * platform classes, or integration classes.
 *
 * Reset discovery and orchestration are runtime-owned. The contracts package
 * does not introduce or own DI tags, tag metadata, service ordering, failure
 * handling, config defaults, config rules, providers, or generated artifacts.
 */
interface ResetInterface
{
    /**
     * Resets implementation-owned mutable state.
     *
     * This method intentionally has no parameters so reset behavior remains
     * format-neutral across HTTP, CLI, worker, scheduler, queue consumer, and
     * custom runtime boundaries.
     *
     * Implementations SHOULD prefer idempotent reset behavior where practical.
     *
     * Implementations MUST NOT expose raw unit-of-work payloads, raw request
     * data, raw queue messages, credentials, tokens, private customer data, or
     * absolute local paths through diagnostics.
     */
    public function reset(): void;
}
