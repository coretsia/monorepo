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

namespace Coretsia\Platform\Http\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;

/**
 * HTTP runtime module metadata.
 *
 * It exposes canonical module identity, the config root, and the provider
 * list without owning runtime discovery or manifest construction, and
 * without performing config loading, container construction, middleware
 * execution, HTTP request lifecycle coordination, Kernel UnitOfWork
 * execution, or reset orchestration.
 *
 * `platform/http` owns PSR-15 middleware pipeline composition and HTTP
 * request lifecycle coordination. Kernel retains ModulePlan, UnitOfWork,
 * and reset semantics. Routing, system endpoints, and transport-specific
 * entrypoints remain outside this module metadata class.
 */
final class HttpModule
{
    public const string MODULE_ID = 'platform.http';
    public const string PACKAGE_ID = 'platform/http';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-http';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'http';

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
     * Returns Http service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            HttpServiceProvider::class,
        ];
    }
}
