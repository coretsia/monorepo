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

namespace Coretsia\Contracts\Observability\Tracing;

/**
 * Stable sampling decision vocabulary for tracing contracts.
 *
 * The decision is intentionally vendor-neutral and does not expose concrete
 * tracing SDK policy objects.
 */
enum SamplingDecision: string
{
    case Record = 'record';
    case Drop = 'drop';
    case Defer = 'defer';

    /**
     * @return list<non-empty-string>
     */
    public static function values(): array
    {
        return [
            self::Record->value,
            self::Drop->value,
            self::Defer->value,
        ];
    }
}
