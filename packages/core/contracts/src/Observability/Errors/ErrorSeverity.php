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

namespace Coretsia\Contracts\Observability\Errors;

/**
 * Stable normalized error severity vocabulary.
 *
 * Severity values are contracts-level values. Runtime loggers may map them to
 * implementation-specific log levels, but those logger levels are not part of
 * this contract.
 */
enum ErrorSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';

    /**
     * @return list<non-empty-string>
     */
    public static function values(): array
    {
        return [
            self::Info->value,
            self::Warning->value,
            self::Error->value,
            self::Critical->value,
        ];
    }
}
