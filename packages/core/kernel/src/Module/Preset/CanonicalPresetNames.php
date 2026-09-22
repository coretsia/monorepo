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

namespace Coretsia\Kernel\Module\Preset;

use Coretsia\Contracts\Module\ModePresetInterface;

/**
 * Kernel-internal helper for reserved canonical preset names.
 *
 * @internal
 */
final class CanonicalPresetNames
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [
            ModePresetInterface::MICRO,
            ModePresetInterface::EXPRESS,
            ModePresetInterface::HYBRID,
            ModePresetInterface::ENTERPRISE,
        ];
        \usort($names, static fn (string $a, string $b): int => \strcmp($a, $b));
        return $names;
    }

    public static function contains(string $name): bool
    {
        return \in_array($name, self::all(), true);
    }
}
