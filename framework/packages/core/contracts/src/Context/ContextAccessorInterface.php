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
 * APIs, context storage, lifecycle hooks, or transport/runtime objects.
 */
interface ContextAccessorInterface
{
    /**
     * Returns the context value associated with the given key.
     *
     * Missing-key semantics are implementation-owned. This contract deliberately
     * has no default parameter.
     */
    public function get(string $key): mixed;
}
