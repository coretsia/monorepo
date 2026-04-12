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

namespace Coretsia\Platform\Cli\Exception;

/**
 * @internal
 * Phase 0 deterministic CLI failure surface (code + reason token).
 *
 * IMPORTANT:
 * - code(): MUST be a CLI-owned code string from Coretsia\Platform\Cli\Error\ErrorCodes
 * - reason(): MUST be a short fixed token; MUST NOT contain paths/secrets.
 */
interface CliExceptionInterface
{
    public function code(): string;

    public function reason(): string;
}
