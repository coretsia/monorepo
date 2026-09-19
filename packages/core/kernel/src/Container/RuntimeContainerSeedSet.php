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
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimePathContext;

/**
 * Immutable exact map of entrypoint-owned runtime container seed instances.
 *
 * Arbitrary seed ids are forbidden. The map must contain exactly one instance
 * for every id returned by RuntimeContainerSeedIds::entrypointOwned().
 *
 * @internal Kernel artifact-runtime boundary.
 */
final readonly class RuntimeContainerSeedSet
{
    /**
     * @var array<class-string, object>
     */
    private array $instances;

    /**
     * @param array<array-key, mixed> $instances
     */
    public function __construct(array $instances)
    {
        self::assertExactIds($instances);
        self::assertInstanceTypes($instances);

        \ksort($instances, \SORT_STRING);

        /** @var array<class-string, object> $instances */
        $this->instances = $instances;
    }

    /**
     * @return array<class-string, object>
     */
    public function instances(): array
    {
        return $this->instances;
    }

    /**
     * @return list<class-string>
     */
    public function ids(): array
    {
        return \array_keys($this->instances);
    }

    /**
     * @param array<array-key, mixed> $instances
     */
    private static function assertExactIds(array $instances): void
    {
        if (\array_is_list($instances)) {
            throw new \InvalidArgumentException('runtime-container-seed-set-ids-invalid');
        }

        $actual = \array_keys($instances);
        \sort($actual, \SORT_STRING);

        $expected = RuntimeContainerSeedIds::entrypointOwned();
        \sort($expected, \SORT_STRING);

        if ($actual !== $expected) {
            throw new \InvalidArgumentException('runtime-container-seed-set-ids-invalid');
        }
    }

    /**
     * @param array<array-key, mixed> $instances
     */
    private static function assertInstanceTypes(array $instances): void
    {
        foreach ($instances as $id => $instance) {
            if (!\is_string($id) || !\is_object($instance)) {
                throw new \InvalidArgumentException('runtime-container-seed-set-instance-invalid');
            }

            $valid = match ($id) {
                ConfigRepositoryInterface::class => $instance instanceof ConfigRepositoryInterface,
                ModulePlan::class => $instance instanceof ModulePlan,
                RuntimePathContext::class => $instance instanceof RuntimePathContext,
                default => false,
            };

            if (!$valid) {
                throw new \InvalidArgumentException('runtime-container-seed-set-instance-invalid');
            }
        }
    }
}
