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

use Coretsia\Contracts\Config\ConfigValueSource;

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
     * Returns whether the env name is present.
     *
     * Present empty string MUST return true.
     *
     * @param non-empty-string $name
     */
    public function has(string $name): bool;

    /**
     * Returns the env lookup result for the given name.
     *
     * Missing and present-empty-string must remain distinct through EnvValue.
     *
     * @param non-empty-string $name
     */
    public function get(string $name): EnvValue;

    /**
     * Returns present env values.
     *
     * This method exposes raw env values to the runtime owner. Diagnostics,
     * traces, logs, validation errors, and explain output MUST NOT print this
     * map directly.
     *
     * @return array<string,string>
     */
    public function all(): array;

    /**
     * Returns safe source metadata for the given env name, when available.
     *
     * The returned source must not contain raw env values.
     *
     * @param non-empty-string $name
     */
    public function sourceOf(string $name): ?ConfigValueSource;
}
