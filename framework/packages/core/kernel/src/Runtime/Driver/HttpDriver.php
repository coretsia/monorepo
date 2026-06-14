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

namespace Coretsia\Kernel\Runtime\Driver;

/**
 * Canonical HTTP runtime driver ids.
 *
 * This enum owns only the stable HTTP runtime driver identifiers.
 *
 * It intentionally contains no config-reading logic, no runtime detection
 * logic, and no compatibility matrix logic. Driver selection and matrix
 * validation are owned by RuntimeDriverGuard and the runtime drivers SSoT.
 */
enum HttpDriver: string
{
    case CLASSIC = 'http.classic';
    case FRANKENPHP = 'http.frankenphp';
    case SWOOLE = 'http.swoole';
    case ROADRUNNER = 'http.roadrunner';
    case WORKER = 'http.worker';

    /**
     * Returns the canonical runtime driver id.
     */
    public function id(): string
    {
        return $this->value;
    }
}
