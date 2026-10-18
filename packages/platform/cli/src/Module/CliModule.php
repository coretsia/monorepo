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

namespace Coretsia\Platform\Cli\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;

/**
 * CLI runtime module metadata.
 *
 * It exposes CLI module metadata and the provider list without owning
 * runtime discovery or manifest construction, and without performing
 * filesystem scanning, container construction, config loading, command
 * discovery, dispatch, or output rendering.
 */
final class CliModule
{
    public const string MODULE_ID = 'platform.cli';
    public const string PACKAGE_ID = 'platform/cli';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-cli';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'cli';

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
     * Returns CLI service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            CliServiceProvider::class,
        ];
    }
}
