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
 * Contracts-level database connection port.
 *
 * This interface represents one logical database connection produced by a
 * driver implementation and consumed by future database runtime packages,
 * migration runtime packages, and low-level database callers.
 *
 * The connection boundary is vendor-neutral. It exposes explicit query
 * execution through SqlQueryInterface, minimal transaction primitives, logical
 * connection identity, logical driver identity, and a SQL dialect port.
 *
 * This interface MUST NOT expose PDO, PDO statements, vendor connections,
 * vendor statements, vendor result objects, transaction objects, savepoint
 * objects, connection registries, configuration repositories, platform database
 * objects, integration database objects, raw SQL execution shortcuts, or
 * database implementation details.
 *
 * Raw SQL, SQL bindings, result values, connection config, DSNs, credentials,
 * vendor diagnostics, and unsafe backend details are sensitive by default.
 * Runtime owners must apply redaction policy before any logging, tracing,
 * metrics, health output, CLI output, error descriptor extension, migration
 * output, or unsafe debug output.
 */
interface ConnectionInterface
{
    /**
     * Returns the logical connection name.
     *
     * The returned name MUST be non-empty and MUST equal the connection name
     * argument used by the driver that produced this connection.
     *
     * The connection name is logical metadata, not a DSN, filesystem path,
     * credential, vendor handle, or configuration repository key.
     *
     * Connection names are still subject to owner-approved bounded-cardinality
     * and redaction policy before use in diagnostics.
     */
    public function name(): string;

    /**
     * Returns the logical driver id for the driver that produced this
     * connection.
     *
     * The returned id MUST be non-empty, stable, deterministic, lowercase ASCII,
     * and match:
     *
     * ^[a-z][a-z0-9_-]*$
     *
     * The driver id is logical metadata, not a PHP extension name, PDO driver
     * name, Composer package name, service id, class name, vendor SDK name, DSN,
     * or vendor connection object.
     *
     * The platform-supported driver allowlist is runtime-owned by future
     * platform/database configuration policy, not by this contracts interface.
     */
    public function driverId(): string;

    /**
     * Executes an explicit SQL query object and returns a contracts-level query
     * result.
     *
     * Raw SQL strings MUST NOT be accepted directly by this contracts boundary.
     * SQL text and bindings must cross the boundary through SqlQueryInterface.
     *
     * Execution behavior, placeholder semantics, SQL grammar validity, retry
     * policy, error handling, diagnostics, and backend-specific behavior are
     * implementation-owned.
     */
    public function execute(SqlQueryInterface $query): QueryResultInterface;

    /**
     * Begins a transaction according to implementation-owned backend semantics.
     *
     * Nested transactions, savepoints, isolation levels, retry policy, deadlock
     * handling, timeout handling, and transaction manager orchestration are
     * outside this contracts surface.
     */
    public function beginTransaction(): void;

    /**
     * Commits the current transaction according to implementation-owned backend
     * semantics.
     *
     * Diagnostics around transaction commits MUST NOT expose raw SQL, bindings,
     * credentials, DSNs, vendor object dumps, or unsafe backend details by
     * default.
     */
    public function commit(): void;

    /**
     * Rolls back the current transaction according to implementation-owned
     * backend semantics.
     *
     * Diagnostics around transaction rollbacks MUST NOT expose raw SQL,
     * bindings, credentials, DSNs, vendor object dumps, or unsafe backend
     * details by default.
     */
    public function rollBack(): void;

    /**
     * Returns the SQL dialect port associated with this connection.
     *
     * Dialect behavior is exposed through a contracts-level vendor-neutral
     * boundary. This method MUST NOT expose vendor dialect objects, Doctrine
     * platform objects, PDO objects, query builders, schema builders, grammar
     * objects, or compiler internals.
     */
    public function dialect(): SqlDialectInterface;
}
