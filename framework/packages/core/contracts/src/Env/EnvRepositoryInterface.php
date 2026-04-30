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

namespace Coretsia\Contracts\Env;

/**
 * Format-neutral env access port.
 *
 * Implementations may read from process env, parsed .env data, generated
 * artifacts, in-memory test sources, or another owner-defined source.
 *
 * The contracts package does not implement env loading or .env parsing.
 */
interface EnvRepositoryInterface
{
    /**
     * Returns the env lookup result for the given name.
     *
     * Missing and present-empty-string must remain distinct through EnvValue.
     */
    public function get(string $name): EnvValue;
}
