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

namespace Coretsia\Foundation\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Provider\FoundationServiceProvider;

/**
 * Foundation runtime module metadata.
 *
 * This class is intentionally small until the canonical module descriptor /
 * module manifest integration is wired by the owning kernel/module-plan epics.
 *
 * It exposes stable Foundation package metadata and the provider list without
 * performing filesystem scanning, container construction, config loading, or
 * runtime side effects.
 */
final class FoundationModule
{
    public const string MODULE_ID = 'core.foundation';
    public const string PACKAGE_ID = 'core/foundation';
    public const string COMPOSER_PACKAGE = 'coretsia/core-foundation';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'foundation';

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
     * Returns Foundation service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            FoundationServiceProvider::class,
        ];
    }
}
