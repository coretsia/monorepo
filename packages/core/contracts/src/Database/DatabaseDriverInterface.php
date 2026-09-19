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
 * Contracts-level database driver port.
 *
 * This interface represents a vendor-neutral driver boundary for future
 * database runtime packages, database driver packages, and integration-owned
 * database drivers.
 *
 * Drivers expose a stable logical driver id and produce contracts-level
 * connections from explicit connection-scoped inputs. The driver contract does
 * not own driver discovery, driver registries, platform-supported driver
 * allowlists, connection registries, configuration loading, configuration
 * validation, tuning merge behavior, PDO wrappers, vendor connection objects,
 * exception mapping, observability emitters, DI wiring, config defaults, config
 * rules, or generated artifacts.
 *
 * Driver implementations MUST treat connect() as a function of the explicit
 * inputs passed to it:
 *
 * - connectionName
 * - config
 * - tuning
 *
 * Driver implementations MUST NOT read global config directly. They MUST NOT
 * read package config files directly. They MUST NOT read environment variables
 * directly when those values should have been resolved into the explicit
 * connection config input by the future runtime owner.
 *
 * Raw driver config, DSNs, credentials, tokens, certificates, private endpoints,
 * SQL, vendor diagnostics, and unsafe backend details are sensitive by default.
 * Runtime owners and driver implementations must apply redaction policy before
 * any logging, tracing, metrics, health output, CLI output, error descriptor
 * extension, migration output, or unsafe debug output.
 */
interface DatabaseDriverInterface
{
    /**
     * Returns the logical driver id.
     *
     * The returned id MUST be non-empty, stable, deterministic,
     * vendor-neutral at the contracts boundary, lowercase ASCII, and match:
     *
     * ^[a-z][a-z0-9_-]*$
     *
     * The logical driver id is not a PHP extension name, PDO driver name,
     * Composer package name, service id, class name, DSN, vendor SDK name, or
     * vendor connection object.
     *
     * The platform-supported driver allowlist is owned by future
     * platform/database configuration policy, not by this contracts interface.
     * This contracts surface locks only the generic logical driver-id shape.
     *
     * @return non-empty-string
     */
    public function id(): string;

    /**
     * Creates or opens a contracts-level database connection.
     *
     * The connection name is logical metadata. It MUST be non-empty and the
     * produced connection is expected to expose the same value through
     * ConnectionInterface::name().
     *
     * The config input is secrets-allowed, driver-owned, connection-scoped
     * configuration. It MAY contain credentials, tokens, DSNs, passwords,
     * private endpoints, certificate material, or other driver-required
     * connection secrets. It MUST NOT be logged, traced, exported as metric
     * labels, copied into error descriptor extensions, printed by migration
     * tooling, printed by CLI diagnostics, exposed through health output, or
     * copied into unsafe debug output.
     *
     * The tuning input is no-secrets effective driver tuning. It is expected to
     * be computed by the future database runtime owner before calling this
     * method, for example from driver defaults plus per-connection overrides.
     * The driver MUST treat it as final input and MUST NOT merge global tuning
     * by itself.
     *
     * PDO options are not exposed through this contracts surface as PDO
     * attributes or PDO constants. If a future PDO-backed driver supports PDO
     * options, they remain driver-owned string-keyed config or tuning entries
     * and are mapped internally by that driver.
     *
     * Driver implementations own backend-specific connection behavior, failure
     * handling, diagnostics, and produced connection invariants.
     *
     * @param non-empty-string $connectionName
     * @param array<string,mixed> $config Secrets-allowed driver-owned config.
     * @param array<string,mixed> $tuning No-secrets effective driver tuning.
     */
    public function connect(
        string $connectionName,
        array $config,
        array $tuning,
    ): ConnectionInterface;
}
