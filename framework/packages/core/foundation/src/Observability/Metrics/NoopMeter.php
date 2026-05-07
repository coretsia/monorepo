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

namespace Coretsia\Foundation\Observability\Metrics;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

/**
 * No-op meter implementation for the Foundation observability baseline.
 *
 * This implementation records no metric names, values, labels, payloads, or
 * private data. It emits no diagnostics.
 */
final class NoopMeter implements MeterPortInterface
{
    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        unset($name, $delta, $labels);
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        unset($name, $value, $labels);
    }
}
