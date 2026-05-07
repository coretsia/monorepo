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

namespace Coretsia\Foundation\Observability\Tracing;

use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;

/**
 * No-op trace context propagation implementation.
 *
 * This implementation does not inspect, store, inject, log, or emit carrier
 * values. Extraction always returns an empty safe context.
 */
final class NoopContextPropagation implements ContextPropagationInterface
{
    public function inject(array $carrier, array $context = []): array
    {
        unset($context);

        return $carrier;
    }

    public function extract(array $carrier): array
    {
        unset($carrier);

        return [];
    }
}
