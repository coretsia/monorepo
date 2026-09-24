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

use Coretsia\Contracts\Module\ModePresetInterface;
use Coretsia\Kernel\Module\Exception\InvalidModuleSelectionException;

/**
 * Applies preset policy and resolved overrides without inspecting dependencies.
 *
 * @internal
 */
final readonly class ModuleSelectionFactory
{
    public function __construct(private ModuleIdSetNormalizer $normalizer)
    {
    }

    public function create(ModePresetInterface $preset, ResolvedModuleOverrides $overrides): ModuleSelection
    {
        $excluded = \array_fill_keys(\array_map(static fn ($id): string => $id->value(), $overrides->exclude()), true);
        foreach ($preset->required() as $id) {
            if (isset($excluded[$id->value()])) {
                throw InvalidModuleSelectionException::withReason(
                    InvalidModuleSelectionException::REASON_REQUIRED_EXCLUDED,
                    ['moduleId' => $id->value()],
                );
            }
        }
        $roots = [];
        foreach ([$preset->required(), $preset->modules(), $overrides->include()] as $source) {
            foreach ($source as $id) {
                if (!isset($excluded[$id->value()])) {
                    $roots[] = $id;
                }
            }
        }
        return new ModuleSelection($this->normalizer->normalize($roots), $overrides->exclude());
    }
}
