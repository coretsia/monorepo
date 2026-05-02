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

namespace Coretsia\Contracts\Observability\Profiling;

/**
 * Unit-of-work-neutral profiling boundary port.
 *
 * This port is usable around HTTP, CLI, worker, scheduler, queue consumer, and
 * custom runtime boundaries. It MUST NOT require PSR-7 objects, framework HTTP
 * objects, CLI concrete input/output objects, queue vendor messages, worker
 * vendor contexts, or profiler SDK types.
 *
 * Implementations may be no-op. Stopping is owned by the returned
 * ProfilingSessionInterface handle.
 */
interface ProfilerPortInterface
{
    /**
     * Starts profiling a unit of work or nested operation.
     *
     * The UoW type MUST be safe and MUST NOT contain secrets, raw paths,
     * raw queries, raw SQL, tokens, credentials, request/response bodies,
     * private customer data, or absolute local paths.
     *
     * Metadata MUST be a safe json-like map:
     * null, bool, int, string, list, or string-keyed map. Floats are forbidden.
     *
     * @param non-empty-string $uowType
     * @param array<string,mixed> $metadata
     */
    public function start(string $uowType, array $metadata = []): ProfilingSessionInterface;
}
