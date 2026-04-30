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

namespace Coretsia\Contracts\Config;

/**
 * Read-only merged config access port.
 *
 * This interface does not prescribe storage format and does not require
 * filesystem paths. Source tracking, when exposed, must use safe contracts
 * shapes and must not contain raw source values.
 */
interface ConfigRepositoryInterface
{
    /**
     * Returns whether a merged config key exists.
     *
     * Key format is implementation-owned and must remain logical, not a
     * filesystem path contract.
     */
    public function has(string $key): bool;

    /**
     * Returns a merged config value for the given logical key.
     *
     * Implementations define missing-key behavior.
     */
    public function get(string $key): mixed;

    /**
     * Returns safe source metadata for the given logical key, when available.
     *
     * The returned source must not contain raw config/env values.
     */
    public function source(string $key): ?ConfigValueSource;
}
