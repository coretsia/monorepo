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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Psr\Container\ContainerInterface;

final readonly class BoundaryScenarioContainer implements ContainerInterface
{
    public function __construct(private BoundaryScenarioRuntime $runtime)
    {
    }

    public function get(string $id): mixed
    {
        $this->runtime->events[] = 'resolve';
        if ($id !== BoundaryScenarioCommand::class || !$this->runtime->active) {
            throw new \LogicException('Command resolution must occur inside the UoW callback.');
        }
        return new BoundaryScenarioCommand();
    }

    public function has(string $id): bool
    {
        return $id === BoundaryScenarioCommand::class;
    }
}
