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

namespace Coretsia\Contracts\Security;

/**
 * Stable contracts-level redaction disclosure modes.
 *
 * Placeholder is the baseline default. Length, Hash, and HashAndLength require
 * explicit owner selection through RedactionContext. One selected context mode
 * applies uniformly to every branch selected during one recursive redaction
 * operation; the implementation MUST NOT silently change mode by kind, field,
 * branch, classifier result, debug state, or environment state.
 *
 * A non-placeholder recursive mode is valid only when owner policy permits the
 * same metadata disclosure for every sensitive branch that may be selected.
 * Heterogeneous boundaries require omission or Placeholder unless separately
 * governed values are split into separate redaction operations.
 */
enum RedactionMode: string
{
    case Placeholder = 'placeholder';
    case Length = 'length';
    case Hash = 'hash';
    case HashAndLength = 'hash-and-length';
}
