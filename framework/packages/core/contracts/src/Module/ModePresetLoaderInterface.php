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

namespace Coretsia\Contracts\Module;

/**
 * Port for loading mode presets by canonical or owner-defined preset name.
 *
 * Framework canonical names are "micro", "express", "hybrid", and
 * "enterprise". The implementation source is intentionally hidden.
 */
interface ModePresetLoaderInterface
{
    /**
     * Returns available preset names in deterministic order.
     *
     * @return list<non-empty-string>
     */
    public function listNames(): array;

    /**
     * Returns whether a preset exists.
     *
     * Invalid or missing names SHOULD return false.
     *
     * @param non-empty-string $name
     */
    public function has(string $name): bool;

    /**
     * Loads a preset by name.
     *
     * Missing preset behavior is implementation-owned.
     * Implementations SHOULD throw deterministic owner-defined exceptions.
     *
     * @param non-empty-string $name
     */
    public function load(string $name): ModePresetInterface;

    /**
     * Loads a preset by name or returns null when missing.
     *
     * Invalid or missing names SHOULD return null.
     *
     * @param non-empty-string $name
     */
    public function tryLoad(string $name): ?ModePresetInterface;
}
