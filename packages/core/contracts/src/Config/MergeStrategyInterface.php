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

namespace Coretsia\Contracts\Config;

/**
 * Deterministic config merge strategy port.
 *
 * This interface defines the merge boundary only. Concrete directive handling,
 * validation-before-merge, trace generation, layer folding, and artifact
 * writing are owned by the Kernel config engine implementation.
 */
interface MergeStrategyInterface
{
    /**
     * Merges two config nodes deterministically.
     *
     * Inputs are implementation-owned config data nodes. Implementations MUST NOT
     * require executable validators, closures, service instances, resources, or
     * runtime wiring objects to perform a merge.
     *
     * Implementations MUST follow directive policy and MUST be side-effect free.
     * Multi-layer merge is performed by owner code by folding this operation in
     * explicit precedence order.
     */
    public function merge(mixed $base, mixed $patch): mixed;
}
