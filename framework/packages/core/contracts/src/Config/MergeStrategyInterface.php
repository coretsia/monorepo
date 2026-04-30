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
 * validation-before-merge, trace generation, and artifact writing are owned by
 * the Kernel config engine implementation.
 */
interface MergeStrategyInterface
{
    /**
     * Merges config layers deterministically.
     *
     * Layers must be supplied in explicit precedence order by the caller or
     * implementation owner. This contract does not infer merge precedence from
     * ConfigSourceType.
     *
     * @param list<array<string,mixed>> $layers
     *
     * @return array<string,mixed>
     */
    public function merge(array $layers): array;
}
