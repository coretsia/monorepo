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

namespace Coretsia\Platform\Routing\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Routing\Provider\RoutingServiceProvider;

/**
 * Routing runtime module metadata.
 *
 * Exposes the canonical Routing package identity and its declared service
 * providers without performing route compilation, artifact reads, runtime
 * discovery, container construction, or lifecycle operations.
 *
 * Routing owns target-local route artifacts and artifact-only matching.
 * Kernel and HTTP retain their existing lifecycle and transport boundaries.
 */
final class RoutingModule
{
    public const string MODULE_ID = 'platform.routing';
    public const string PACKAGE_ID = 'platform/routing';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-routing';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'routing';

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
     * Returns Routing service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            RoutingServiceProvider::class,
        ];
    }
}
