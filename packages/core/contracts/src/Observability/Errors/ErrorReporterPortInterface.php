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

namespace Coretsia\Contracts\Observability\Errors;

/**
 * Format-neutral normalized error reporter port.
 *
 * Reporter implementations own concrete emission to logs, traces, metrics, or
 * external systems. They MUST preserve the redaction policy and MUST NOT emit
 * raw Throwable payloads, raw request/response payloads, raw SQL, credentials,
 * tokens, cookies, profile payloads, private customer data, or absolute local
 * paths.
 */
interface ErrorReporterPortInterface
{
    /**
     * Reports a normalized descriptor with optional safe context.
     *
     * The descriptor and context are already contracts-level safe models.
     * Implementations MUST NOT enrich emitted diagnostics with unsafe raw
     * runtime payloads.
     */
    public function report(
        ErrorDescriptor $descriptor,
        ?ErrorHandlingContext $context = null,
    ): void;
}
