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

namespace Coretsia\Kernel\Module;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\Exception\InvalidModuleSelectionException;

/**
 * Immutable, canonical compile-host module selection state.
 *
 * @internal
 */
final readonly class ResolvedModuleOverrides
{
    /**
     * @param list<ModuleId> $include
     * @param list<ModuleId> $exclude
     */
    public function __construct(private array $include, private array $exclude)
    {
        self::validate($include, InvalidModuleSelectionException::REASON_INCLUDE_INVALID);
        self::validate($exclude, InvalidModuleSelectionException::REASON_EXCLUDED_INVALID);
        $left = \array_fill_keys(\array_map(static fn (ModuleId $id): string => $id->value(), $include), true);
        foreach ($exclude as $id) {
            if (isset($left[$id->value()])) {
                throw InvalidModuleSelectionException::withReason(InvalidModuleSelectionException::REASON_OVERLAP);
            }
        }
    }

    /**
     * @return list<ModuleId>
     */
    public function include(): array
    {
        return $this->include;
    }

    /**
     * @return list<ModuleId>
     */
    public function exclude(): array
    {
        return $this->exclude;
    }

    private static function validate(array $ids, string $reason): void
    {
        if (!\array_is_list($ids)) {
            throw InvalidModuleSelectionException::withReason($reason);
        }
        $last = null;
        foreach ($ids as $id) {
            if (!$id instanceof ModuleId || ($last !== null && \strcmp($last, $id->value()) >= 0)) {
                throw InvalidModuleSelectionException::withReason($reason);
            }
            $last = $id->value();
        }
    }
}
