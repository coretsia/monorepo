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

namespace Coretsia\Platform\HttpApp\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;

/**
 * HttpApp runtime module metadata.
 *
 * Exposes the canonical HttpApp package identity and its declared service
 * providers without performing route matching, middleware execution, action
 * invocation, runtime discovery, manifest construction, container construction,
 * config loading, or lifecycle operations.
 *
 * HttpApp owns application routing integration, route-specific middleware
 * selection, and explicit action dispatch. HTTP retains ownership of PSR-15
 * pipeline composition and transport boundaries; Routing retains ownership of
 * route artifacts and matching; Kernel retains ownership of runtime lifecycle.
 */
final class HttpAppModule
{
    public const string MODULE_ID = 'platform.http-app';
    public const string PACKAGE_ID = 'platform/http-app';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-http-app';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'http_app';

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
     * Returns HttpApp service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            HttpAppServiceProvider::class,
        ];
    }
}
