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

namespace Coretsia\Contracts\Observability\Metrics;

/**
 * Vendor-neutral metrics emitting port.
 *
 * Metric names MUST follow the canonical observability naming rules.
 * Metric label keys MUST stay within the SSoT allowlist.
 */
interface MeterPortInterface
{
    /**
     * Increments a counter-like metric.
     *
     * Label keys MUST be allowlisted. Baseline allowed label keys are:
     * method, status, driver, operation, table, outcome.
     *
     * Label values MUST be safe and bounded-cardinality. Labels MUST NOT
     * contain raw paths, raw queries, headers, cookies, request bodies,
     * response bodies, auth identifiers, session identifiers, tokens, raw SQL,
     * profile payloads, arbitrary user identifiers, or private customer data.
     *
     * @param array<string,string|int|bool> $labels
     */
    public function increment(string $name, int $delta = 1, array $labels = []): void;

    /**
     * Observes an integer metric value.
     *
     * Durations SHOULD be represented as integer milliseconds when emitted
     * through this generic contract.
     *
     * Label keys and values MUST follow the same allowlist and redaction rules
     * as counter labels.
     *
     * @param array<string,string|int|bool> $labels
     */
    public function observe(string $name, int $value, array $labels = []): void;
}
