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

use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;

/**
 * Name-based namespace selection; never probes the filesystem.
 *
 * @internal
 */
final readonly class PresetNamespaceResolver
{
    public function resolve(string $name): PresetNamespace
    {
        if (\preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $name) !== 1) {
            throw ModePresetNotFoundException::invalidPresetName();
        }

        return CanonicalPresetNames::contains($name) ? PresetNamespace::Canonical : PresetNamespace::Custom;
    }
}
