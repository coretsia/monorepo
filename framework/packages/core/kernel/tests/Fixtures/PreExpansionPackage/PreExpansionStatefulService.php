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

namespace Coretsia\Kernel\Tests\Fixtures\PreExpansionPackage;

use Coretsia\Contracts\Runtime\ResetInterface;

final class PreExpansionStatefulService implements ResetInterface
{
    private ?string $state = null;

    public function __construct(
        private readonly string $seed,
    ) {
    }

    public function seed(): string
    {
        return $this->seed;
    }

    public function state(): ?string
    {
        return $this->state;
    }

    public function remember(string $value): void
    {
        $this->state = $value;
    }

    public function reset(): void
    {
        $this->state = null;
    }
}
