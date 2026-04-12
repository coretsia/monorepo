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
 * Contract invariants:
 * - Output implementation MUST enforce determinism (no timestamps/randomness) and redaction (no secrets/PII).
 * - This interface intentionally does not define formatting/verbosity/styling semantics.
 */
interface OutputInterface
{
    /**
     * Write a plain text message (implementation decides newline policy; must be deterministic).
     */
    public function text(string $text): void;

    /**
     * Write a JSON payload (implementation MUST emit deterministic bytes).
     *
     * @param array<string, mixed>|list<mixed> $payload
     */
    public function json(array $payload): void;

    /**
     * Emit a normalized error record.
     *
     * IMPORTANT:
     * - $message MUST NOT contain secrets; output implementation enforces redaction as a final safety net.
     */
    public function error(string $code, string $message): void;
}
