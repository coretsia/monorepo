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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;

/**
 * Public Kernel-owned runtime-driver selection and matrix-resolution boundary.
 *
 * This resolver reads only Kernel-owned runtime-driver selector configuration,
 * composes explicit owner-provided runtime-driver contributions, validates
 * canonical driver conflicts, and returns RuntimeDrivers.
 *
 * It does not validate module participation, package availability, adapter
 * readiness, transport readiness, or runtime executability.
 */
final class RuntimeDriverResolver
{
    private const string CONFIG_HTTP_DRIVER = 'kernel.runtime.http_driver';

    /**
     * Resolves Kernel-selected runtime drivers plus explicit owner
     * contributions into the canonical coherent runtime-driver set.
     *
     * @throws RuntimeDriverConflictException
     * @throws RuntimeDriverInvalidConfigException
     */
    public function resolve(
        ConfigRepositoryInterface $config,
        RuntimeDriverContributions $contributions,
    ): RuntimeDrivers {
        return self::composeRuntimeDrivers(
            kernelHttpDriver: self::configuredHttpDriver($config),
            contributions: $contributions,
        );
    }

    /**
     * @throws RuntimeDriverInvalidConfigException
     */
    private static function configuredHttpDriver(
        ConfigRepositoryInterface $config,
    ): HttpDriver {
        if (!$config->has(self::CONFIG_HTTP_DRIVER)) {
            throw RuntimeDriverInvalidConfigException::configKeyMissing();
        }

        $httpDriver = $config->get(self::CONFIG_HTTP_DRIVER);

        if (!\is_string($httpDriver)) {
            throw RuntimeDriverInvalidConfigException::configKeyInvalid();
        }

        return match ($httpDriver) {
            HttpDriver::CLASSIC->value => HttpDriver::CLASSIC,
            HttpDriver::FRANKENPHP->value => HttpDriver::FRANKENPHP,
            HttpDriver::SWOOLE->value => HttpDriver::SWOOLE,
            HttpDriver::ROADRUNNER->value => HttpDriver::ROADRUNNER,
            default => throw RuntimeDriverInvalidConfigException::configKeyInvalid(),
        };
    }

    /**
     * @throws RuntimeDriverConflictException
     */
    private static function composeRuntimeDrivers(
        HttpDriver $kernelHttpDriver,
        RuntimeDriverContributions $contributions,
    ): RuntimeDrivers {
        $httpDriver = self::composeHttpDriver(
            kernelHttpDriver: $kernelHttpDriver,
            contributedHttpDrivers: $contributions->httpDrivers(),
            contributedBackgroundDrivers: $contributions->backgroundDrivers(),
        );

        return new RuntimeDrivers(
            $httpDriver,
            ...$contributions->backgroundDrivers(),
        );
    }

    /**
     * @param list<HttpDriver> $contributedHttpDrivers
     * @param list<BackgroundDriver> $contributedBackgroundDrivers
     *
     * @throws RuntimeDriverConflictException
     */
    private static function composeHttpDriver(
        HttpDriver $kernelHttpDriver,
        array $contributedHttpDrivers,
        array $contributedBackgroundDrivers,
    ): HttpDriver {
        if ($contributedHttpDrivers === []) {
            return $kernelHttpDriver;
        }

        if ($kernelHttpDriver === HttpDriver::CLASSIC && \count($contributedHttpDrivers) === 1) {
            return $contributedHttpDrivers[0];
        }

        $httpDrivers = [
            $kernelHttpDriver,
            ...$contributedHttpDrivers,
        ];

        self::throwHttpDriverConflict(
            httpDrivers: $httpDrivers,
            backgroundDrivers: $contributedBackgroundDrivers,
        );
    }

    /**
     * @param list<HttpDriver> $httpDrivers
     * @param list<BackgroundDriver> $backgroundDrivers
     */
    private static function throwHttpDriverConflict(
        array $httpDrivers,
        array $backgroundDrivers,
    ): never {
        $activeDriverIds = self::driverIdsFromDrivers($httpDrivers, $backgroundDrivers);
        $conflictingDriverIds = self::httpDriverIds($httpDrivers);

        if (\in_array(HttpDriver::WORKER, $httpDrivers, true)) {
            throw RuntimeDriverConflictException::workerHttpConflictsWithHttpDriver(
                activeDriverIds: $activeDriverIds,
                conflictingDriverIds: $conflictingDriverIds,
            );
        }

        throw RuntimeDriverConflictException::multipleHttpDrivers(
            activeDriverIds: $activeDriverIds,
            conflictingDriverIds: $conflictingDriverIds,
        );
    }

    /**
     * @param list<HttpDriver> $httpDrivers
     *
     * @return list<string>
     */
    private static function httpDriverIds(array $httpDrivers): array
    {
        $driverIds = [];

        foreach ($httpDrivers as $httpDriver) {
            $driverIds[] = $httpDriver->id();
        }

        \usort(
            $driverIds,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $driverIds;
    }

    /**
     * @param list<HttpDriver> $httpDrivers
     * @param list<BackgroundDriver> $backgroundDrivers
     *
     * @return list<string>
     */
    private static function driverIdsFromDrivers(
        array $httpDrivers,
        array $backgroundDrivers,
    ): array {
        $driverIds = [];

        foreach ($httpDrivers as $httpDriver) {
            $driverIds[] = $httpDriver->id();
        }

        foreach ($backgroundDrivers as $backgroundDriver) {
            $driverIds[] = $backgroundDriver->id();
        }

        \usort(
            $driverIds,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $driverIds;
    }
}
