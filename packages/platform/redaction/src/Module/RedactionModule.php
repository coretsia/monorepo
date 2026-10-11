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

namespace Coretsia\Platform\Redaction\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;

/**
 * Sensitive-data redaction runtime module metadata.
 *
 * It exposes redaction module metadata and the provider list without owning
 * runtime discovery or manifest construction, and without performing config
 * loading, service resolution, filesystem access, or runtime work.
 *
 * `platform/redaction` owns the shared deterministic sensitive-data redaction
 * implementation. Consumer modules remain responsible for their own output
 * policy, destination-boundary validation, and safe-by-construction shapes.
 */
final class RedactionModule
{
    public const string MODULE_ID = 'platform.redaction';
    public const string PACKAGE_ID = 'platform/redaction';
    public const string COMPOSER_PACKAGE = 'coretsia/platform-redaction';
    public const string KIND = 'runtime';

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

    /**
     * Returns redaction service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            RedactionServiceProvider::class,
        ];
    }
}
