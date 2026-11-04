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

namespace Coretsia\Platform\Metrics\Default;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Platform\Metrics\Labels\AllowedLabelSet;

/**
 * Stateless label-validating noop meter for the selected platform.metrics module.
 *
 * Global label-key and scalar-type admission runs for every invocation. Both
 * admitted and rejected calls discard their samples without reporting, logging,
 * buffering, exporting, or mutating UnitOfWork context. Metric-specific catalog
 * enforcement and safe bounded-cardinality values remain producer/backend owned.
 */
final class DefaultMeter implements MeterPortInterface
{
    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        if (!AllowedLabelSet::accepts($labels)) {
            return;
        }

        unset($name, $delta, $labels);
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        if (!AllowedLabelSet::accepts($labels)) {
            return;
        }

        unset($name, $value, $labels);
    }
}
