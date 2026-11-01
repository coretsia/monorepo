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

namespace Coretsia\Platform\Logging\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Logging\Provider\LoggingServiceProvider;

/**
 * Logging runtime module metadata.
 *
 * It exposes logging module metadata and the provider list without owning
 * runtime discovery or manifest construction, and without performing config
 * loading, service resolution, filesystem access, stream opening, or runtime
 * initialization.
 *
 * `platform/logging` owns PSR-3 log processing and emission. Kernel retains
 * module planning and runtime lifecycle ownership, while platform.redaction
 * owns shared sensitive-data redaction.
 */
final class LoggingModule
{
    public const string MODULE_ID = 'platform.logging';
    public const string PACKAGE_ID = 'platform/logging';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-logging';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'logging';

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
     * Returns Logging service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            LoggingServiceProvider::class,
        ];
    }
}
