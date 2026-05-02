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
 * by module id ascending using byte-order strcmp. The contracts package does
 * not prescribe the source.
 */
interface ManifestReaderInterface
{
    public function read(): ModuleManifest;
}
