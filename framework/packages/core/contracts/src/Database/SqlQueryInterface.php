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
 * Contracts-level SQL query carrier.
 *
 * This interface represents the database query boundary shared by future
 * database runtime packages, database driver packages, and migration runtime
 * packages.
 *
 * SQL text is carried as opaque application data. This contract does not parse,
 * normalize, classify, validate grammar, strip comments, infer operation kind,
 * quote identifiers, compile SQL, expose vendor statement handles, or provide
 * raw SQL diagnostics.
 *
 * Bindings are positional only. Binding order is semantic and must be preserved
 * by implementations.
 *
 * Raw SQL and raw binding values are sensitive by default. Runtime owners must
 * apply redaction policy before any logging, tracing, metrics, health output,
 * CLI output, error descriptor extension, migration output, or unsafe debug
 * output.
 */
interface SqlQueryInterface
{
    /**
     * Returns the raw SQL string carried by this query.
     *
     * The returned string is opaque SQL text and MUST NOT be logged, traced,
     * exported as a metric label, copied into error descriptors, printed by
     * migration tooling, or exposed through unsafe diagnostics by default.
     */
    public function sql(): string;

    /**
     * Returns positional query bindings.
     *
     * Binding order MUST be preserved and MUST match placeholder order according
     * to the future database runtime owner and concrete driver semantics.
     *
     * Associative bindings, named bindings, nested arrays, objects, closures,
     * resources, streams, file handles, service instances, vendor database
     * values, request/response objects, and PSR-7 objects are outside this
     * contract.
     *
     * @return list<int|string|bool|null>
     */
    public function bindings(): array;
}
