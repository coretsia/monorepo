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

namespace Coretsia\Platform\Cli\Provider;

/**
 * Stateless factory/wiring helper for CLI services.
 *
 * Invariants:
 * - MUST NOT keep mutable runtime state (no caches/buffers/singletons).
 * - MUST be deterministic for the same inputs.
 *
 * @internal
 */
final class CliServiceFactory
{
}
