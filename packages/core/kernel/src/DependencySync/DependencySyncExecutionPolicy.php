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

final readonly class DependencySyncExecutionPolicy
{
    public function __construct(
        private bool $apply,
        private bool $allowComposerScripts = false,
        private bool $allowComposerPlugins = false,
        private bool $allowBroadUpdate = false,
        private bool $allowRepair = false,
    ) {
    }

    public function apply(): bool
    {
        return $this->apply;
    }

    public function allowComposerScripts(): bool
    {
        return $this->allowComposerScripts;
    }

    public function allowComposerPlugins(): bool
    {
        return $this->allowComposerPlugins;
    }

    public function allowBroadUpdate(): bool
    {
        return $this->allowBroadUpdate;
    }

    public function allowRepair(): bool
    {
        return $this->allowRepair;
    }
}
