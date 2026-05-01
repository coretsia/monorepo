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

use Throwable;

/**
 * Format-neutral error handling boundary port.
 *
 * Implementations MAY compose exception mappers and reporters, but this
 * contract does not prescribe registries, DI tags, HTTP adaptation, CLI output,
 * worker behavior, or problem-details rendering.
 *
 * The port MUST NOT require PSR-7 request/response objects, framework HTTP
 * request/response objects, CLI concrete output implementations, worker
 * concrete message objects, or vendor runtime objects.
 */
interface ErrorHandlerInterface
{
    /**
     * Handles a Throwable at a runtime boundary and returns the normalized
     * descriptor selected by the implementation.
     *
     * The returned descriptor MUST be format-neutral. Its httpStatus value is
     * only an optional hint for transport adapters.
     *
     * Implementations MUST NOT expose the raw Throwable, stack trace, raw
     * payloads, raw SQL, credentials, tokens, cookies, request/response bodies,
     * profile payloads, private customer data, or absolute local paths.
     */
    public function handle(
        Throwable $throwable,
        ?ErrorHandlingContext $context = null,
    ): ErrorDescriptor;
}
