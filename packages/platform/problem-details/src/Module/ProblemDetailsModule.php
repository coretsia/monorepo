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

namespace Coretsia\Platform\ProblemDetails\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider;

/**
 * ProblemDetails runtime module metadata.
 *
 * It exposes canonical module identity, the config root, and the provider
 * list without owning runtime discovery or manifest construction, and
 * without performing config loading, service resolution, container
 * construction, or error normalization.
 *
 * `platform/problem-details` owns HTTP Problem Details adaptation and
 * rendering. `platform/errors` retains format-neutral Throwable normalization
 * and reporting, including consumption of the shared redaction boundary.
 * `platform/http` owns middleware execution and transport lifecycle.
 */
final class ProblemDetailsModule
{
    public const string MODULE_ID = 'platform.problem-details';
    public const string PACKAGE_ID = 'platform/problem-details';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-problem-details';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'problem_details';

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
     * Returns ProblemDetails service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            ProblemDetailsServiceProvider::class,
        ];
    }
}
