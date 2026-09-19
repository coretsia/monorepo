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

namespace Coretsia\Contracts\Worker;

/**
 * Closed vocabulary of worker task-source types.
 */
enum WorkerTaskType: string
{
    case Queue = 'queue';
    case Http = 'http';

    /**
     * @return list<non-empty-string>
     */
    public static function values(): array
    {
        return [
            self::Queue->value,
            self::Http->value,
        ];
    }
}
