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

namespace Coretsia\Kernel\Module;

use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Kernel\Provider\KernelServiceProvider;

/**
 * Kernel runtime module metadata.
 *
 * This class is intentionally small until the canonical module descriptor /
 * module manifest integration is wired by the owning Kernel runtime epics.
 *
 * It exposes stable Kernel package metadata and the provider list without
 * performing filesystem scanning, container construction, config loading,
 * runtime lifecycle execution, reset execution, hook dispatching, or transport
 * adapter behavior.
 *
 * `core/kernel` owns stable format-neutral UnitOfWork shapes and policy
 * contracts. HTTP, CLI, queue, scheduler, and other runtime adapters attach
 * safe adapter-specific metadata outside this module metadata class.
 */
final class KernelModule
{
    public const string MODULE_ID = 'core.kernel';
    public const string PACKAGE_ID = 'core/kernel';
    public const string COMPOSER_PACKAGE = 'coretsia/core-kernel';
    public const string KIND = 'runtime';
    public const string CONFIG_ROOT = 'kernel';

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
     * Returns Kernel service providers in module-declared order.
     *
     * `ContainerBuilder` must preserve this caller-supplied order exactly and
     * must not re-sort providers.
     *
     * @return list<class-string<ServiceProviderInterface>>
     */
    public function providers(): array
    {
        return [
            KernelServiceProvider::class,
        ];
    }
}
