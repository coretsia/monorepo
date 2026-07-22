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

namespace Coretsia\Kernel\Container;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Psr\Container\ContainerInterface;

/**
 * Canonical production runtime container seed service ids.
 *
 * These ids form the canonical allowlist for external references expected to be
 * supplied by production artifact-only runtime boot. This class stores service
 * ids only and never runtime objects.
 *
 * @internal Kernel compiled-container policy.
 */
final class RuntimeContainerSeedIds
{
    /**
     * @var list<class-string>
     */
    private const array IDS = [
        Container::class,
        ContainerInterface::class,
        TagRegistry::class,
        ConfigRepositoryInterface::class,
        ModulePlan::class,
        RuntimePathContext::class,
    ];

    private function __construct()
    {
    }

    /**
     * @return list<class-string>
     */
    public static function all(): array
    {
        return self::IDS;
    }
}
