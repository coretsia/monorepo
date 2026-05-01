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
 * Format-neutral exception mapper port.
 *
 * Implementations MAY inspect Throwable internally, but the returned
 * ErrorDescriptor MUST NOT expose the raw Throwable, stack trace, raw payloads,
 * raw SQL, credentials, tokens, cookies, request/response bodies, profile
 * payloads, private customer data, or absolute local paths.
 *
 * Runtime discovery of mapper implementations is platform-owned. This
 * contracts package does not introduce or own DI tags.
 */
interface ExceptionMapperInterface
{
    /**
     * Maps a Throwable to a safe normalized error descriptor.
     */
    public function map(Throwable $throwable): ErrorDescriptor;
}
