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

namespace Coretsia\Platform\Errors\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;

/**
 * Errors runtime module metadata.
 *
 * It exposes canonical module identity, the config root, and the provider
 * list without owning runtime discovery or manifest construction, and
 * without performing config loading, service resolution, container
 * construction, or error normalization.
 *
 * `platform/errors` owns format-neutral Throwable normalization through
 * its runtime services. CLI, HTTP, and Worker adapters retain their own
 * rendering, process lifecycle, and transport-specific responsibilities.
 */
final class ErrorsModule
{
    public const string MODULE_ID = 'platform.errors';
    public const string PACKAGE_ID = 'platform/errors';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-errors';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'errors';

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
     * Returns Errors service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            ErrorsServiceProvider::class,
        ];
    }
}
