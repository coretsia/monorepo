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

namespace Coretsia\Kernel\Runtime\Driver;

/**
 * Immutable selected runtime drivers.
 *
 * This value object stores the already selected HTTP driver and background
 * drivers only.
 *
 * It intentionally contains no config-reading logic, no runtime detection
 * logic, no module compatibility rules, and no compatibility matrix logic.
 * Driver selection and matrix validation are owned by RuntimeDriverGuard and
 * the runtime drivers SSoT.
 */
final readonly class RuntimeDrivers
{
    private HttpDriver $httpDriver;

    /**
     * @var list<BackgroundDriver>
     */
    private array $backgroundDrivers;

    public function __construct(
        HttpDriver $httpDriver,
        BackgroundDriver ...$backgroundDrivers,
    ) {
        $this->httpDriver = $httpDriver;
        $this->backgroundDrivers = self::normalizeBackgroundDrivers($backgroundDrivers);
    }

    public function httpDriver(): HttpDriver
    {
        return $this->httpDriver;
    }

    /**
     * @return list<BackgroundDriver>
     */
    public function backgroundDrivers(): array
    {
        return $this->backgroundDrivers;
    }

    /**
     * Returns all active runtime driver ids sorted by canonical id.
     *
     * @return list<string>
     */
    public function driverIds(): array
    {
        $driverIds = [
            $this->httpDriverId(),
            ...$this->backgroundDriverIds(),
        ];

        \usort(
            $driverIds,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $driverIds;
    }

    public function httpDriverId(): string
    {
        return $this->httpDriver->id();
    }

    /**
     * Returns active background runtime driver ids sorted by canonical id.
     *
     * @return list<string>
     */
    public function backgroundDriverIds(): array
    {
        $driverIds = [];

        foreach ($this->backgroundDrivers as $backgroundDriver) {
            $driverIds[] = $backgroundDriver->id();
        }

        \usort(
            $driverIds,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $driverIds;
    }

    /**
     * @param list<BackgroundDriver> $backgroundDrivers
     *
     * @return list<BackgroundDriver>
     */
    private static function normalizeBackgroundDrivers(array $backgroundDrivers): array
    {
        $driversById = [];

        foreach ($backgroundDrivers as $backgroundDriver) {
            $driverId = $backgroundDriver->id();

            if (isset($driversById[$driverId])) {
                throw new \InvalidArgumentException('runtime-drivers-background-driver-duplicate');
            }

            $driversById[$driverId] = $backgroundDriver;
        }

        \uksort(
            $driversById,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return \array_values($driversById);
    }
}
