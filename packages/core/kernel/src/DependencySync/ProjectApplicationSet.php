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

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;

final readonly class ProjectApplicationSet
{
    /** @var non-empty-list<AppTarget> */
    private array $targets;

    /** @param list<AppTarget> $targets */
    public function __construct(array $targets)
    {
        if ($targets === [] || !\array_is_list($targets)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::APPLICATION_SET_INVALID);
        }

        $indexed = [];

        foreach ($targets as $target) {
            if (!$target instanceof AppTarget) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::APPLICATION_SET_INVALID);
            }

            $targetName = $target->value;

            if (isset($indexed[$targetName])) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::APPLICATION_SET_INVALID);
            }

            $indexed[$targetName] = $target;
        }

        \uksort(
            $indexed,
            static fn (string $left, string $right): int => \strcmp(
                $left,
                $right,
            ),
        );

        /** @var non-empty-list<AppTarget> $canonical */
        $canonical = \array_values($indexed);

        $this->targets = $canonical;
    }

    /** @return non-empty-list<AppTarget> */
    public function targets(): array
    {
        return $this->targets;
    }
}
