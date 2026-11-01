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

/**
 * Platform logging runtime defaults.
 *
 * This file returns the `logging` configuration subtree only. The merged
 * configuration is read at runtime through ConfigRepositoryInterface under
 * `logging.*`; this array MUST NOT contain a repeated `logging` root.
 *
 * Selection of the platform.logging module belongs to Kernel ModulePlan.
 * When that module is selected, `stream` uses fixed php://stderr output,
 * `file` uses append-only daily UTC files, and the string `null` selects the
 * existing Foundation NoopLogger. PHP null is not a valid driver value.
 * Excluding the module retains the Foundation binding regardless of config.
 *
 * No redaction mode, redaction-disable switch, destination discovery,
 * environment read, or runtime service is permitted in these defaults.
 */
return [
    /*
     * Logging backend selection.
     *
     * Allowed values:
     *
     * - `stream`: use the fixed php://stderr PSR-3 backend.
     * - `file`: use the automatic daily UTC file backend.
     * - `null`: use the existing Foundation NoopLogger.
     *
     * The driver is always a string. PHP null is not permitted.
     * Module selection remains owned by Kernel ModulePlan.
     */
    'driver' => 'stream',

    /*
     * Daily file output settings.
     *
     * Used only by driver `file`; other drivers retain these defaults without
     * activating filesystem output. The stream destination is fixed internally
     * to php://stderr and has no configurable key.
     *
     * `path` defaults to an empty string. File mode requires a configured
     * absolute local directory, validated by LoggingServiceFactory; runtime
     * availability is checked per emission without creating directories.
     *
     * `name` is an extension-free filename stem using the existing
     * reset-group-id grammar: [a-z][a-z0-9_-]{0,63}.
     *
     * File names follow {app_target}-{file.name}-{YYYY-MM-DD}.{format} (UTC).
     * Daily partitioning is automatic; no rotation-mode, file-permission,
     * retention, archival, or total-file-size configuration is exposed.
     */
    'file' => [
        'path' => '',
        'name' => 'app',
    ],

    /*
     * Minimum PSR-3 logging severity.
     *
     * Allowed values:
     *
     * - `debug`
     * - `info`
     * - `notice`
     * - `warning`
     * - `error`
     * - `critical`
     * - `alert`
     * - `emergency`
     *
     * Records below the configured severity are not emitted.
     */
    'level' => 'info',

    /*
     * Maximum serialized bytes per log record.
     *
     * Allowed range: 1024..65536.
     *
     * The limit includes the complete serialized record and final LF.
     * It does not configure log rotation, retention, or total file size.
     */
    'max_record_bytes' => 65536,

    /*
     * Emitted service identity.
     *
     * `auto` resolves to the Kernel-selected canonical application target:
     * web, api, console, or worker.
     *
     * Explicit service names must satisfy the existing reset-group-id
     * grammar: [a-z][a-z0-9_-]{0,63}.
     *
     * The selected application target remains separately available.
     * Service identity is resolved during logger construction.
     */
    'service_name' => 'auto',

    /*
     * Serialized record format.
     *
     * Allowed values:
     *
     * - `jsonl`: deterministic JSON Lines output.
     * - `logfmt`: deterministic single-line key=value output.
     *
     * Both formats use the same admitted and redacted canonical record.
     * Format selection does not affect severity filtering, shared
     * redaction, module selection, or driver selection. In file mode, the
     * selected format determines the daily filename extension.
     */
    'format' => 'jsonl',
];
