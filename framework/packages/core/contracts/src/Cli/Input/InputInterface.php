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
 * This contract exposes deterministic parsed input for package commands while
 * remaining independent from platform/cli implementation details.
 *
 * Contract invariants:
 *
 * - command implementations MUST NOT be required to parse raw argv tokens directly;
 * - raw tokens remain available only for diagnostics, forwarding, or advanced
 *   command use cases;
 * - parser implementation classes MUST NOT be exposed through this contract;
 * - parsed command name, positional arguments, and normalized options MUST be
 *   stable for the same invocation;
 * - option values MUST be limited to string, bool, list<string>, or null.
 *
 * @phpstan-type CliOptionValue string|bool|list<string>|null
 * @phpstan-type CliOptions array<string, CliOptionValue>
 */
interface InputInterface
{
    /**
     * Returns raw CLI tokens for the current invocation.
     *
     * These tokens are implementation-provided and MUST be stable for the same
     * invocation. Package commands SHOULD use parsed accessors for normal
     * command input instead of parsing these tokens directly.
     *
     * @return list<string>
     */
    public function tokens(): array;

    /**
     * Returns the resolved command name.
     *
     * The value MUST be the normalized command name selected by the CLI input
     * parser, not a parser object, command catalog entry, or platform-specific
     * implementation detail.
     */
    public function commandName(): string;

    /**
     * Returns positional arguments in parsed order.
     *
     * @return list<string>
     */
    public function arguments(): array;

    /**
     * Returns normalized options keyed by normalized option name.
     *
     * Option values MUST be limited to:
     *
     * - string
     * - bool
     * - list<string>
     * - null
     *
     * @return CliOptions
     */
    public function options(): array;

    /**
     * Returns whether a normalized option exists.
     */
    public function hasOption(string $name): bool;

    /**
     * Returns a normalized option value by normalized option name.
     *
     * Null means either the option is absent or the normalized option value is
     * explicitly null. Call hasOption() when absence must be distinguished from
     * a null value.
     *
     * @return CliOptionValue
     */
    public function option(string $name): string|bool|array|null;
}
