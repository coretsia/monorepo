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
 * Validation rules for the `kernel` configuration subtree.
 *
 * These rules intentionally keep the initial Kernel config strict:
 * - the file validates the `kernel` root subtree;
 * - unknown keys are rejected at every declared map level;
 * - reserved `@*` keys are rejected by the same strict-shape policy;
 * - `kernel.config.forbidden_top_level_roots` must be a list of non-empty safe
 *   strings without whitespace;
 * - `kernel.config.forbidden_top_level_roots` must not include `kernel` or
 *   `foundation` in defaults because applications must be able to configure
 *   those roots;
 * - `kernel.boot.default_env` must be a non-empty string;
 * - `kernel.boot.default_preset` must be a non-empty string and is the only
 *   package-level preset fallback;
 * - per-app preset overrides belong to bootstrap-only `skeleton/config/app.php`
 *   and are validated by BootstrapOverridesLoader, not by these config rules;
 * - `kernel.boot.default_debug` must be a bool;
 * - `kernel.env.source_policy.default_local` must be `strict_dotenv`;
 * - `kernel.env.source_policy.default_production` must be `allow_system`;
 * - `kernel.env.dotenv.files` must be a list of non-empty strings;
 * - `kernel.modules.discovery.source` must be a non-empty safe string;
 * - `kernel.modules.discovery.source` shape validation does not enforce the
 *   concrete source value; supported source membership is validated by
 *   ModulePlanResolver;
 * - `kernel.modules.discovery.allowed_sources` must be a list of non-empty
 *   safe strings;
 * - `kernel.modes.schema_version` must be integer `1`;
 * - `kernel.modes.defaults_path` must be a non-empty relative safe path;
 * - `kernel.modes.overrides_path` must be a non-empty relative safe path;
 * - `kernel.artifacts.cache_dir` must be a non-empty relative safe path;
 * - `kernel.fingerprint.skeleton_ignore_prefixes` must be a list of non-empty
 *   relative safe paths;
 * - `kernel.fingerprint.*` must not define an `env` subtree or
 *   `env.tracked_keys`; env fingerprint coverage is derived from resolved
 *   bootstrap/config/env source metadata, not from a duplicate config allowlist;
 * - `kernel.uow.attributes.max_depth` must be an integer greater than zero;
 * - `kernel.uow.attributes.max_keys` must be an integer greater than zero;
 * - this epic introduces no runtime feature flags, reset tag constants, HTTP
 *   adapter config, CLI adapter config, outcome mapping config, artifact schema
 *   version config, or fingerprint env tracked-key config.
 *
 * The defaults file must return the subtree only:
 *
 *     config/kernel.php => ['config' => [...], 'boot' => [...], 'env' => [...], 'modules' => [...], 'modes' => [...], 'artifacts' => [...], 'fingerprint' => [...], 'uow' => [...]]
 *
 * It must not return:
 *
 *     ['kernel' => [...]]
 */
return [
    'schemaVersion' => 1,
    'configRoot' => 'kernel',
    'additionalKeys' => false,
    'keys' => [
        'config' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'forbidden_top_level_roots' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'non-empty-string-no-ws',
                    ],
                ],
            ],
        ],
        'boot' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'default_env' => [
                    'required' => true,
                    'type' => 'non-empty-string',
                ],
                'default_preset' => [
                    'required' => true,
                    'type' => 'non-empty-string',
                ],
                'default_debug' => [
                    'required' => true,
                    'type' => 'bool',
                ],
            ],
        ],
        'env' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'source_policy' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'default_local' => [
                            'required' => true,
                            'type' => 'non-empty-string',
                            'allowedValues' => [
                                'strict_dotenv',
                            ],
                        ],
                        'default_production' => [
                            'required' => true,
                            'type' => 'non-empty-string',
                            'allowedValues' => [
                                'allow_system',
                            ],
                        ],
                    ],
                ],
                'dotenv' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'files' => [
                            'required' => true,
                            'type' => 'list',
                            'items' => [
                                'type' => 'non-empty-string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'modules' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'discovery' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'source' => [
                            'required' => true,
                            'type' => 'non-empty-string-no-ws',
                        ],
                        'allowed_sources' => [
                            'required' => true,
                            'type' => 'list',
                            'items' => [
                                'type' => 'non-empty-string-no-ws',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'modes' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'schema_version' => [
                    'required' => true,
                    'type' => 'int',
                    'allowedValues' => [
                        1,
                    ],
                ],
                'defaults_path' => [
                    'required' => true,
                    'type' => 'relative-safe-path',
                ],
                'overrides_path' => [
                    'required' => true,
                    'type' => 'relative-safe-path',
                ],
            ],
        ],
        'artifacts' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'cache_dir' => [
                    'required' => true,
                    'type' => 'relative-safe-path',
                ],
            ],
        ],
        'fingerprint' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'skeleton_ignore_prefixes' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'relative-safe-path',
                    ],
                ],
            ],
        ],
        'uow' => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => [
                'attributes' => [
                    'required' => true,
                    'type' => 'map',
                    'additionalKeys' => false,
                    'keys' => [
                        'max_depth' => [
                            'required' => true,
                            'type' => 'int',
                            'min' => 1,
                        ],
                        'max_keys' => [
                            'required' => true,
                            'type' => 'int',
                            'min' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
