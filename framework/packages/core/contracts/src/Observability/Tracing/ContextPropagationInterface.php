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

namespace Coretsia\Contracts\Observability\Tracing;

/**
 * Format-neutral trace context propagation port.
 *
 * This contract intentionally does not require PSR-7 requests/responses,
 * framework HTTP request objects, raw headers, or vendor propagation carriers.
 */
interface ContextPropagationInterface
{
    /**
     * Injects safe trace context into a scalar/list/map carrier.
     *
     * Carrier keys MUST be safe strings. Carrier values MUST be safe strings
     * or lists of safe strings. Implementations MUST NOT inject secrets,
     * credentials, cookies, authorization headers, request bodies, response
     * bodies, raw SQL, or profile payloads.
     *
     * Context MUST be a safe json-like map:
     * null, bool, int, string, list, or string-keyed map. Floats are forbidden.
     *
     * @param array<string,string|list<string>> $carrier
     * @param array<string,mixed> $context
     *
     * @return array<string,string|list<string>>
     */
    public function inject(array $carrier, array $context = []): array;

    /**
     * Extracts safe trace context from a scalar/list/map carrier.
     *
     * The returned context MUST be a safe json-like map and MUST NOT expose raw
     * headers, credentials, cookies, request bodies, response bodies, raw SQL,
     * profile payloads, or private customer data.
     *
     * @param array<string,string|list<string>> $carrier
     *
     * @return array<string,mixed>
     */
    public function extract(array $carrier): array;
}
