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

namespace Coretsia\Kernel\DependencySync;

final readonly class ProjectDependencySyncResult
{
    /**
     * @param array<string, non-empty-string> $managedRequireWrites
     * @param list<string> $managedRequireRemovals
     * @param array<string, non-empty-string> $preservedSatisfiers
     */
    public function __construct(
        private ProjectPackagePlan $plan,
        private bool $changed,
        private array $managedRequireWrites,
        private array $managedRequireRemovals,
        private array $preservedSatisfiers,
    ) {
    }

    public function plan(): ProjectPackagePlan
    {
        return $this->plan;
    }

    public function changed(): bool
    {
        return $this->changed;
    }

    /** @return array<string, non-empty-string> */
    public function managedRequireWrites(): array
    {
        return $this->managedRequireWrites;
    }

    /** @return list<string> */
    public function managedRequireRemovals(): array
    {
        return $this->managedRequireRemovals;
    }

    /** @return array<string, non-empty-string> */
    public function preservedSatisfiers(): array
    {
        return $this->preservedSatisfiers;
    }
}
