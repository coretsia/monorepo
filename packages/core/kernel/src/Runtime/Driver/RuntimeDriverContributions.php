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
 * Explicit runtime-driver contributions supplied by owner packages.
 *
 * This object contains already-selected drivers only. It does not read config,
 * does not inspect ModulePlan, and does not know which package produced the
 * contribution.
 */
final readonly class RuntimeDriverContributions
{
    /**
     * @var list<HttpDriver>
     */
    private array $httpDrivers;

    /**
     * @var list<BackgroundDriver>
     */
    private array $backgroundDrivers;

    /**
     * @param list<HttpDriver> $httpDrivers
     * @param list<BackgroundDriver> $backgroundDrivers
     */
    private function __construct(
        array $httpDrivers,
        array $backgroundDrivers,
    ) {
        $this->httpDrivers = self::normalizeHttpDrivers($httpDrivers);
        $this->backgroundDrivers = self::normalizeBackgroundDrivers($backgroundDrivers);
    }

    /**
     * @param list<HttpDriver> $httpDrivers
     * @param list<BackgroundDriver> $backgroundDrivers
     */
    public static function fromDrivers(
        array $httpDrivers,
        array $backgroundDrivers,
    ): self {
        return new self($httpDrivers, $backgroundDrivers);
    }

    /**
     * @return list<HttpDriver>
     */
    public function httpDrivers(): array
    {
        return $this->httpDrivers;
    }

    /**
     * @return list<BackgroundDriver>
     */
    public function backgroundDrivers(): array
    {
        return $this->backgroundDrivers;
    }

    /**
     * @return list<string>
     */
    public function driverIds(): array
    {
        $driverIds = [];

        foreach ($this->httpDrivers as $httpDriver) {
            $driverIds[] = $httpDriver->id();
        }

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
     * @param list<HttpDriver> $httpDrivers
     *
     * @return list<HttpDriver>
     */
    private static function normalizeHttpDrivers(array $httpDrivers): array
    {
        $driversById = [];

        foreach ($httpDrivers as $httpDriver) {
            if (!$httpDriver instanceof HttpDriver) {
                throw new \InvalidArgumentException('runtime-driver-contribution-http-driver-invalid');
            }

            $driverId = $httpDriver->id();

            if (isset($driversById[$driverId])) {
                throw new \InvalidArgumentException('runtime-driver-contribution-http-driver-duplicate');
            }

            $driversById[$driverId] = $httpDriver;
        }

        \uksort(
            $driversById,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return \array_values($driversById);
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
            if (!$backgroundDriver instanceof BackgroundDriver) {
                throw new \InvalidArgumentException('runtime-driver-contribution-background-driver-invalid');
            }

            $driverId = $backgroundDriver->id();

            if (isset($driversById[$driverId])) {
                throw new \InvalidArgumentException('runtime-driver-contribution-background-driver-duplicate');
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
