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
 * Foundation runtime defaults.
 *
 * This file returns the `foundation` configuration subtree only.
 * It MUST NOT wrap values in a repeated root key such as:
 *
 *     return ['foundation' => [...]];
 *
 * Runtime code reads these values from the merged global configuration under
 * `foundation.*`.
 *
 * Baseline invariants:
 * - tag discovery is always available when `core/foundation` is enabled;
 * - reset orchestration is always available when `core/foundation` is enabled;
 * - empty discovery lists are represented by empty-list semantics;
 * - no feature flag may disable tags or reset orchestration;
 * - keys beginning with `@` are reserved and rejected by config rules.
 */
return [
    /*
     * PSR-11 container behavior.
     *
     * These options control conservative reflection/autowire behavior for
     * concrete classes. Interfaces are never autowired.
     *
     * `Container::canAutowire()` is intentionally strict: if the merged global
     * configuration does not contain `foundation.container`, container code
     * must fail deterministically instead of silently guessing defaults.
     */
    'container' => [
        /*
         * Allow autowiring for concrete classes.
         *
         * This does not allow autowiring interfaces, abstract classes, or
         * unknown service ids. It only permits concrete-class autowire checks
         * when container configuration is present and reflection is allowed.
         */
        'autowire_concrete' => true,

        /*
         * Allow reflection while resolving concrete-class autowiring metadata.
         *
         * Runtime reset execution must not rely on reflection/autowire; this
         * option is limited to container construction/resolution behavior.
         */
        'allow_reflection_for_concrete' => true,
    ],

    /*
     * Reset orchestration behavior.
     *
     * Foundation owns reset discovery through the effective reset discovery tag.
     * Kernel code must call the reset orchestrator and must not enumerate tagged
     * reset services directly.
     */
    'reset' => [
        /*
         * Effective reset discovery tag.
         *
         * The reserved default tag name is `kernel.reset`.
         *
         * `kernel.reset` is the default tag value, not a kernel-owned runtime
         * feature. Consumers outside Foundation must not hardcode this string
         * and must not read this key directly.
         *
         * `ResetOrchestrator` uses this value to obtain reset services through
         * `TagRegistry::all($effectiveResetTag)` and executes them in the exact
         * registry order in legacy/base mode.
         */
        'tag' => 'kernel.reset',
    ],
];
