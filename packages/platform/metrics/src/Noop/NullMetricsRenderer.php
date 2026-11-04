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

namespace Coretsia\Platform\Metrics\Noop;

use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;

/**
 * Stateless null renderer for the selected platform.metrics baseline.
 *
 * It does not collect, retain, serialize, or expose metric samples. HTTP
 * endpoints, exporters, and recording backends remain outside this baseline.
 */
final class NullMetricsRenderer implements MetricsRendererInterface
{
    public function contentType(): string
    {
        return 'text/plain; charset=utf-8';
    }

    public function render(): string
    {
        return '';
    }
}
