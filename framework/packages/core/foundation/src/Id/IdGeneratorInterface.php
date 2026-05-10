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

namespace Coretsia\Foundation\Id;

/**
 * Canonical Foundation runtime id generator abstraction.
 *
 * This is a Foundation-owned runtime abstraction, not a core/contracts port.
 * It is used for generic safe runtime ids such as future uow_id or request_id
 * owners, according to their own SSoT policies.
 *
 * This abstraction MUST NOT control correlation id generation. Correlation ids
 * remain governed by CorrelationIdGenerator, which delegates directly to the
 * canonical UlidGenerator per epic 1.210.0.
 */
interface IdGeneratorInterface
{
    /**
     * Generates a safe opaque deterministic-format id string.
     *
     * Generated ids MUST NOT be derived from secrets, credentials, raw payloads,
     * direct user identifiers, cookies, sessions, authorization values, raw
     * headers, raw request/response bodies, raw SQL, or private customer data.
     *
     * @return non-empty-string
     */
    public function generate(): string;
}
