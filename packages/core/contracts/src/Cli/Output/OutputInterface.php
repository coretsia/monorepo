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

namespace Coretsia\Contracts\Cli\Output;

/**
 * CLI output port.
 *
 * This is the only output abstraction that package-contributed commands should
 * use. Commands must write user-visible output through this contracts-level
 * port and must not write to stdout/stderr directly.
 *
 * Contract invariants:
 *
 * - this contract MUST remain independent from platform/cli;
 * - package-contributed commands MUST NOT depend on platform/cli output
 *   implementation classes;
 * - command implementations MUST NOT use echo, print, printf, fwrite(STDOUT),
 *   fwrite(STDERR), var_dump(), print_r(), or error_log() for command output;
 * - JSON payloads passed to json() MUST be intended as safe JSON-like payloads;
 * - JSON payloads SHOULD contain only null, bool, int, string, lists, and
 *   string-keyed maps;
 * - JSON payloads MUST NOT intentionally contain secrets, raw payloads, raw
 *   endpoints, headers, tokens, environment values, resources, closures,
 *   objects, or implementation internals;
 * - error messages passed to error() MUST be safe, normalized, and secret-free;
 * - final deterministic rendering, newline policy, formatting, verbosity,
 *   styling, stream selection, and redaction policy remain owned by the
 *   platform/cli implementation.
 */
interface OutputInterface
{
    /**
     * Writes a plain text message.
     *
     * The message MUST be safe for user-visible command output. Final rendering
     * details are owned by the concrete CLI output implementation.
     */
    public function text(string $text): void;

    /**
     * Writes a safe JSON-like payload.
     *
     * Commands MUST pass only payloads intended to be safe JSON-like data.
     * The concrete CLI output implementation owns final deterministic JSON
     * rendering and redaction behavior.
     *
     * @param array<string, mixed>|list<mixed> $payload
     */
    public function json(array $payload): void;

    /**
     * Emits a normalized error record.
     *
     * The code MUST be a stable diagnostic identifier. The message MUST be
     * safe, normalized, and secret-free. The concrete CLI output implementation
     * owns final rendering and redaction behavior.
     */
    public function error(string $code, string $message): void;
}
