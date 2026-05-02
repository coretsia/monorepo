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
 * Format-neutral config loader port.
 *
 * Implementations own concrete source discovery, parsing, ordering, merging,
 * and repository construction. Callers must not be required to pass filesystem
 * paths, Composer metadata paths, or concrete file-format details through this
 * contract.
 */
interface ConfigLoaderInterface
{
    /**
     * Loads config into a read-only merged config repository.
     *
     * The repository may expose safe source metadata and deterministic explain
     * traces through contracts-level shapes only.
     */
    public function load(): ConfigRepositoryInterface;
}
