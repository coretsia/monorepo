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
 * Kernel runtime defaults.
 *
 * This file returns the `kernel` configuration subtree only.
 * It MUST NOT wrap values in a repeated root key such as:
 *
 *     return ['kernel' => [...]];
 *
 * Runtime code reads these values from the merged global configuration under
 * `kernel.*`.
 *
 * Baseline invariants:
 * - the `kernel` root is owned by `core/kernel`;
 * - dotted keys such as `kernel.boot.*`, `kernel.config.*`, `kernel.env.*`,
 *   `kernel.modules.*`, `kernel.modes.*`, `kernel.artifacts.*`,
 *   `kernel.fingerprint.*`, `kernel.uow.*` are config key namespaces, not roots;
 * - `kernel.config.*` owns ConfigKernel Phase B safety defaults;
 * - `kernel.config.forbidden_top_level_roots` reserves global internal config
 *   namespaces only;
 * - `kernel.config.forbidden_top_level_roots` MUST NOT include `kernel` or
 *   `foundation` because applications must be able to configure those roots;
 * - `kernel.modules.*` owns module discovery source defaults;
 * - `kernel.modes.*` owns mode preset path/schema defaults;
 * - `kernel.artifacts.*` owns Kernel artifact output path defaults;
 * - `kernel.fingerprint.*` owns deterministic fingerprint exclusion defaults;
 * - `kernel.fingerprint.*` MUST NOT duplicate the canonical dotenv files list from
 *   `kernel.env.dotenv.files`;
 * - `kernel.fingerprint.env.tracked_keys` MUST NOT be introduced;
 * - Kernel config defaults MUST NOT contain absolute paths;
 * - Kernel config defaults MUST NOT contain monorepo-only paths such as
 *   `framework/packages/core/kernel/...`;
 * - Bootstrap Phase A uses deterministic package defaults when explicit input
 *   and bootstrap-only overrides are absent;
 * - UnitOfWork attributes are json-like and float-forbidden;
 * - attributes are safe metadata only and MUST NOT contain secrets, PII,
 *   authorization data, cookies, raw payloads, raw SQL, or stack traces;
 * - keys beginning with `@` are reserved and rejected by config rules.
 */
return [
    /*
     * ConfigKernel Phase B safety defaults.
     *
     * These values are consumed by Kernel-owned config services such as
     * ConfigNamespaceGuard. They are policy inputs, not root ownership rules.
     *
     * Forbidden top-level roots are global internal namespaces reserved for the
     * framework implementation itself.
     *
     * Important:
     *
     * - `kernel` MUST NOT be listed here;
     * - `foundation` MUST NOT be listed here;
     * - applications must remain able to configure framework-owned public roots
     *   through normal config files and overlays.
     */
    'config' => [
        'forbidden_top_level_roots' => [
            'coretsia',
            '_internal',
        ],
    ],

    /*
     * Bootstrap Phase A defaults.
     *
     * These values are used only as deterministic fallback defaults for
     * BootstrapConfig resolution.
     *
     * General resolution order is:
     *
     * - explicit BootstrapInput values;
     * - bootstrap-only overrides from skeleton/config/app.php;
     * - these package defaults.
     *
     * Preset resolution has a dedicated deterministic precedence:
     *
     * - explicit BootstrapInput::preset();
     * - skeleton/config/app.php presets[appTarget];
     * - skeleton/config/app.php preset;
     * - kernel.boot.default_preset.
     *
     * `default_preset` is the only package-level preset fallback.
     *
     * Per-app preset overrides are skeleton-local bootstrap-only policy and may be
     * defined in skeleton/config/app.php under the `presets` key.
     *
     * The Kernel package intentionally does not define package-level per-app
     * preset defaults.
     *
     * Full config merge, environment overlays, directives, validation, explain,
     * and application config discovery are owned by ConfigKernel Phase B.
     */
    'boot' => [
        'default_env' => 'local',
        'default_preset' => 'micro',
        'default_debug' => false,
    ],

    /*
     * Bootstrap Phase A env source defaults.
     *
     * `source_policy` controls source precedence between parsed dotenv values
     * and system/process env values.
     *
     * It is intentionally separate from Coretsia\Contracts\Env\EnvPolicy,
     * which remains a missing-value policy only:
     *
     * - required
     * - optional
     * - defaulted
     */
    'env' => [
        'source_policy' => [
            'default_local' => 'strict_dotenv',
            'default_production' => 'allow_system',
        ],
        'dotenv' => [
            'files' => [
                '.env',
                '.env.local',
                '.env.<env>',
                '.env.<env>.local',
            ],
        ],
    ],

    /*
     * Module discovery defaults.
     *
     * Module discovery is metadata-only. The Kernel module plan resolver reads
     * installed Composer metadata through the configured discovery source and
     * MUST NOT scan framework packages, vendor directories, skeleton
     * directories, or module classes at runtime.
     *
     * `source` is validated by ModulePlanResolver against `allowed_sources`
     * before discovery. Config rules validate only the string/list shape.
     */
    'modules' => [
        'discovery' => [
            'source' => 'composer',
            'allowed_sources' => [
                'composer',
            ],
        ],
    ],

    /*
     * Mode preset loading defaults.
     *
     * This section does not select the active mode/preset.
     *
     * The selected preset is resolved by Bootstrap Phase A and exposed through
     * BootstrapConfig::preset(). The fallback default preset is owned by
     * `kernel.boot.default_preset`.
     *
     * `schema_version` is the canonical mode preset schema expected by the
     * Kernel-owned mode preset validator.
     *
     * `defaults_path` is package-relative and resolves from the core/kernel
     * package root.
     *
     * `overrides_path` is skeleton-root-relative and resolves from
     * BootstrapConfig::skeletonRoot().
     *
     * These defaults intentionally avoid absolute paths and monorepo-only
     * paths so split packages and installed applications can resolve them
     * deterministically.
     */
    'modes' => [
        'schema_version' => 1,
        'defaults_path' => 'resources/modes',
        'overrides_path' => 'config/modes',
    ],

    /*
     * Artifact output defaults.
     *
     * Artifacts are build-time/cache outputs owned by core/kernel.
     *
     * `cache_dir` is BootstrapConfig::skeletonRoot()-relative. It MUST remain a
     * relative-safe path and MUST NOT contain a `skeleton/` prefix, `..`,
     * absolute path syntax, host-specific path fragments, or monorepo-only paths.
     *
     * Artifact schema versions are owned by artifact code and SSoT documents.
     * They are intentionally not runtime-configurable.
     */
    'artifacts' => [
        'cache_dir' => 'var/cache',
    ],

    /*
     * Fingerprint defaults.
     *
     * Fingerprinting is deterministic and safe by construction. It MUST NOT
     * include raw config values, raw env values, secrets, absolute paths,
     * timestamps, mtimes, permissions, owners, hostnames, or process-specific
     * bytes.
     *
     * `skeleton_ignore_prefixes` values are BootstrapConfig::skeletonRoot()
     * relative. They are used only as deterministic exclusions for skeleton-local
     * generated/operational paths.
     *
     * Env fingerprint coverage is derived from resolved BootstrapConfig values,
     * the canonical kernel.env.dotenv.files list, env-overlay mappings, and
     * EnvRepositoryInterface source metadata.
     *
     * This config intentionally does not define
     * kernel.fingerprint.env.tracked_keys. The canonical dotenv files list stays
     * owned by kernel.env.dotenv.files and MUST NOT be duplicated here.
     */
    'fingerprint' => [
        'skeleton_ignore_prefixes' => [
            'var/cache',
            'var/maintenance',
        ],
    ],

    /*
     * UnitOfWork shape configuration.
     *
     * This epic owns only stable shape limits for context attributes.
     * Lifecycle execution, HTTP/CLI outcome derivation, reset triggering, and
     * adapter-specific attribute population are implemented by later runtime
     * epics or platform adapters.
     */
    'uow' => [
        /*
         * Limits for UnitOfWorkContext.attributes.
         *
         * `attributes` must be a json-like map:
         *
         * - allowed scalars: null, bool, int, string;
         * - forbidden scalars: float, including NaN, INF, and -INF;
         * - allowed containers: lists and string-keyed maps only;
         * - forbidden values: objects, closures, resources, service instances,
         *   enums as objects, raw payloads, raw SQL, tokens, cookies, session
         *   ids, Authorization values, stack traces, and PII.
         *
         * `max_depth` and `max_keys` are defensive bounds used by the kernel
         * UnitOfWorkContext guard before attributes cross hooks, adapters, or
         * artifact boundaries.
         */
        'attributes' => [
            'max_depth' => 10,
            'max_keys' => 200,
        ],
    ],
];
