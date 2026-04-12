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

namespace Coretsia\Contracts\Cli\Input;

/**
 * CLI input port.
 *
 * Contract invariant:
 * - This port exposes raw tokens only.
 * - It MUST NOT freeze parsing semantics (flags/options/argv rules are CLI-impl concern).
 */
interface InputInterface
{
    /**
     * Raw CLI tokens (implementation-defined, but MUST be stable for the same invocation).
     *
     * @return list<string>
     */
    public function tokens(): array;
}
