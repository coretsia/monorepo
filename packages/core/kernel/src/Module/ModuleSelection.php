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
final readonly class ModuleSelection
{
    /**
     * @param list<ModuleId> $roots
     * @param list<ModuleId> $excluded
     */
    public function __construct(private array $roots, private array $excluded)
    {
        self::validate($roots, InvalidModuleSelectionException::REASON_ROOTS_INVALID);
        self::validate($excluded, InvalidModuleSelectionException::REASON_EXCLUDED_INVALID);
        $left = \array_fill_keys(\array_map(static fn (ModuleId $id): string => $id->value(), $roots), true);
        foreach ($excluded as $id) {
            if (isset($left[$id->value()])) {
                throw InvalidModuleSelectionException::withReason(InvalidModuleSelectionException::REASON_OVERLAP);
            }
        }
    }

    /**
     * @return list<ModuleId>
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * @return list<ModuleId>
     */
    public function excluded(): array
    {
        return $this->excluded;
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
