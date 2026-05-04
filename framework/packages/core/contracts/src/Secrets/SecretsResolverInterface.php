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

namespace Coretsia\Contracts\Secrets;

/**
 * Contracts-level port for resolving secret values by stable logical reference.
 *
 * Implementations are runtime-owned and MUST NOT expose resolved secret values
 * through logs, metrics, traces, debug output, health output, generated
 * artifacts, exception messages, or unsafe diagnostics.
 */
interface SecretsResolverInterface
{
    /**
     * Resolve a secret value by its stable logical reference.
     *
     * The returned value is sensitive by default and MUST NOT be logged,
     * traced, exported, rendered, copied into diagnostics, or exposed through
     * debug output by implementations or downstream consumers.
     *
     * A returned empty string represents an explicitly resolved empty secret
     * value when the runtime owner and backend allow empty secret values.
     * Missing, inaccessible, denied, invalid, or failed secret resolution is
     * implementation-owned failure behavior.
     *
     * @param non-empty-string $ref
     *
     * @return string
     */
    public function resolve(string $ref): string;
}
