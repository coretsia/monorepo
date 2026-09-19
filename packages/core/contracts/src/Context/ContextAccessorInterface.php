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

namespace Coretsia\Contracts\Context;

/**
 * Minimal format-neutral context read port.
 *
 * This contract provides read-only access to implementation-owned runtime
 * context without depending on Foundation, platform packages, integrations, or
 * storage details.
 *
 * The port intentionally does not expose default values, setters, mutation
 * APIs, context storage, lifecycle hooks, full context snapshots, or
 * transport/runtime objects.
 */
interface ContextAccessorInterface
{
    /**
     * Returns whether the context contains the given key.
     *
     * Key naming, namespacing, and empty-key handling are implementation-owned.
     * This method allows callers to distinguish an absent key from a present
     * key whose value is null, without adding a default parameter to get().
     */
    public function has(string $key): bool;

    /**
     * Returns the context value associated with the given key.
     *
     * Missing-key semantics are implementation-owned. This contract deliberately
     * has no default parameter.
     *
     * Returned values are implementation-owned safe context values. Callers and
     * implementations MUST NOT use this port to expose secrets, credentials,
     * tokens, raw payloads, raw SQL, private customer data, or transport/runtime
     * objects.
     */
    public function get(string $key): mixed;
}
