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

namespace Coretsia\Contracts\Database;

/**
 * Contracts-level database query result port.
 *
 * This interface represents a materialized scalar-only query result boundary.
 * It does not expose cursors, generators, iterators, streams, resources, file
 * handles, vendor result objects, vendor row objects, PDO statements, lazy
 * result handles, metadata objects, typed row mappers, generated keys, or
 * pagination helpers.
 *
 * Result rows use the canonical database value domain:
 *
 * DbValue = int|string|bool|null
 * DbRow = array<string, DbValue>
 * DbRows = list<DbRow>
 *
 * Float values MUST NOT cross this contracts boundary. Database decimals,
 * floating-point values, big integers that are not safely bounded, date/time
 * values, binary values, and JSON values are represented as strings by runtime
 * owners before reaching this interface.
 *
 * Raw result values are sensitive by default. Runtime owners must apply
 * redaction policy before any logging, tracing, metrics, health output, CLI
 * output, error descriptor extension, migration output, or unsafe debug output.
 */
interface QueryResultInterface
{
    /**
     * Returns materialized database rows.
     *
     * Row order MUST preserve database result order as produced by the
     * implementation. Row maps SHOULD use deterministic string-key ordering
     * when rows are constructed by owner code.
     *
     * Row values MUST be scalar-only database values and MUST NOT contain
     * floats, arrays, objects, resources, streams, closures, vendor value
     * objects, date objects, or runtime wiring objects.
     *
     * @return list<array<string,int|string|bool|null>>
     */
    public function rows(): array;

    /**
     * Returns the number of rows affected according to implementation-owned
     * backend semantics.
     *
     * If a driver cannot determine the number of affected rows, it SHOULD
     * return 0 unless a future owner SSoT defines a more precise policy.
     */
    public function affectedRows(): int;
}
