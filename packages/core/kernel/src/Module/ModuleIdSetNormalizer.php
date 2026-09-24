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

/**
 * Canonical set union for already validated ModuleId lists.
 *
 * @internal
 */
final readonly class ModuleIdSetNormalizer
{
    /**
     * @param list<ModuleId> $modules
     * @return list<ModuleId>
     */
    public function normalize(array $modules): array
    {
        if (!\array_is_list($modules)) {
            throw new \InvalidArgumentException('module-id-set-must-be-list');
        }
        $set = [];
        foreach ($modules as $module) {
            if (!$module instanceof ModuleId) {
                throw new \InvalidArgumentException('module-id-set-item-invalid');
            }
            $set[$module->value()] = $module;
        }
        \ksort($set, \SORT_STRING);
        return \array_values($set);
    }
}
