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

namespace Coretsia\Platform\Metrics\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Metrics\Provider\MetricsServiceProvider;

/**
 * Metrics runtime module metadata.
 *
 * It exposes metrics module metadata and the provider list without owning
 * runtime discovery or manifest construction, and without performing config
 * loading, service resolution, filesystem access, metric collection,
 * aggregation, rendering, or runtime initialization.
 *
 * `platform/metrics` owns the metrics label-admission and null-renderer
 * baseline. Kernel retains module planning and UnitOfWork lifecycle
 * ownership, while core/foundation retains the canonical noop meter fallback.
 */
final class MetricsModule
{
    public const string MODULE_ID = 'platform.metrics';
    public const string PACKAGE_ID = 'platform/metrics';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-metrics';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'metrics';

    public function id(): string
    {
        return self::MODULE_ID;
    }

    public function packageId(): string
    {
        return self::PACKAGE_ID;
    }

    public function composerPackage(): string
    {
        return self::COMPOSER_PACKAGE;
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function configRoot(): string
    {
        return self::CONFIG_ROOT;
    }

    /**
     * Returns Metrics service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            MetricsServiceProvider::class,
        ];
    }
}
