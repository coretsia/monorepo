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
 * Implementations own concrete source discovery and parsing. Callers must not
 * be required to pass filesystem paths, Composer metadata paths, or concrete
 * file-format details through this contract.
 */
interface ConfigLoaderInterface
{
    /**
     * Loads config input into a contract-level config shape.
     *
     * The returned map is config data only. Concrete loading, parsing, source
     * discovery, and source ordering are implementation-owned.
     *
     * @return array<string,mixed>
     */
    public function load(): array;
}
