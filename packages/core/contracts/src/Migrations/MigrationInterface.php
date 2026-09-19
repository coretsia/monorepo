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

namespace Coretsia\Contracts\Migrations;

use Coretsia\Contracts\Database\ConnectionInterface;

/**
 * Contracts-level migration boundary.
 *
 * This interface represents the minimal migration execution contract consumed
 * by future migration runtime packages.
 *
 * Migration execution depends only on the contracts-level database connection
 * port. This interface MUST NOT depend on database drivers, connection
 * factories, connection registries, migration runners, migration registries,
 * migration contexts, CLI input/output objects, PDO objects, vendor connection
 * objects, vendor statement objects, vendor schema builders, service container
 * objects, platform packages, integration packages, generated artifacts, or
 * runtime wiring objects.
 *
 * Migration identity, naming, versioning, ordering, discovery, dependency
 * graphs, checksums, registry records, transaction policy, connection
 * selection, CLI behavior, and metadata extraction are runtime-owned by future
 * migration owners and are intentionally outside this contracts surface.
 *
 * Migration implementations may execute SQL through the received
 * ConnectionInterface using SqlQueryInterface or the canonical SqlQuery value
 * object from the database contracts boundary. Raw SQL, bindings, credentials,
 * DSNs, vendor diagnostics, and unsafe backend details are sensitive by
 * default and MUST NOT be exposed through unsafe diagnostics by default.
 */
interface MigrationInterface
{
    /**
     * Applies the migration.
     *
     * The received connection is the only database-specific dependency exposed
     * by this contracts boundary. Execution behavior, transaction wrapping,
     * migration ordering, diagnostics, failure handling, and registry updates
     * are runtime-owned.
     */
    public function up(ConnectionInterface $connection): void;

    /**
     * Reverts the migration when the migration author and runtime owner support
     * rollback semantics.
     *
     * Rollback planning, transaction orchestration, migration batch behavior,
     * registry updates, diagnostics, and failure handling are runtime-owned.
     */
    public function down(ConnectionInterface $connection): void;
}
