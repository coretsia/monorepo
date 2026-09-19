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

namespace Coretsia\Contracts\Module;

/**
 * Port for reading the installed module manifest.
 *
 * Implementations must return a ModuleManifest whose descriptors are ordered
 * by module id ascending using byte-order strcmp. Discovery-source details are
 * intentionally not exposed through this contracts boundary.
 */
interface ManifestReaderInterface
{
    /**
     * Reads the installed module manifest.
     *
     * Implementations own manifest parsing and internal cache/artifact access,
     * subject to canonical module discovery policy. This method returns only the
     * contracts-level manifest shape and MUST NOT expose Composer internals,
     * filesystem paths, generated artifact paths, package scanning details,
     * service container state, or runtime wiring objects through the contract.
     *
     * Repository package-index artifacts and physical package paths MUST NOT
     * become required inputs of this contracts boundary.
     *
     * The returned manifest MUST be deterministic. Its descriptors are expected
     * to be ordered by module id ascending using byte-order strcmp, as enforced
     * by ModuleManifest.
     */
    public function read(): ModuleManifest;
}
