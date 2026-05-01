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

namespace Coretsia\Contracts\Observability\Health;

/**
 * Stable health status vocabulary.
 *
 * Endpoint representation belongs to platform-owned health integrations, not
 * to this contracts enum.
 */
enum HealthStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Pass->value,
            self::Warn->value,
            self::Fail->value,
        ];
    }
}
