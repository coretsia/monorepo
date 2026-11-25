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
 * Minimal format-neutral runtime context mutation port.
 *
 * Key and value validation remain implementation-owned. Callers MUST write
 * and remove only keys assigned to their semantic owner boundary and supply
 * owner-approved safe context values.
 *
 * This port does not expose reads, bulk replacement, snapshots, clearing,
 * reset, lifecycle operations, storage details, or transport objects.
 */
interface ContextWriterInterface
{
    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;
}
