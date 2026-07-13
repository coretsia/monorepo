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
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;

/**
 * Canonical runtime driver guard.
 *
 * This guard derives the Kernel-owned runtime driver selection from
 * Kernel-owned config inputs only.
 *
 * It is intentionally stateless and deterministic. It must not inspect
 * environment variables, loaded PHP extensions, process names, CLI argv, ports,
 * filesystem adapter presence, container services, generated artifacts,
 * config source metadata, or reflection.
 *
 * Config reads are intentionally limited to ConfigRepositoryInterface::get()
 * and ConfigRepositoryInterface::has().
 *
 * Generic config shape and unknown-key validation is owned by config rules.
 * This guard owns only Kernel runtime driver selection from Kernel-owned
 * config inputs.
 *
 * @internal Kernel runtime entrypoint implementation detail. Runtime adapters
 * should depend on RuntimeEntrypointGuard instead.
 */
final class RuntimeDriverGuard
{
    private const string CONFIG_HTTP_DRIVER = 'kernel.runtime.http_driver';

    private const string MODULE_PLATFORM_HTTP = 'platform.http';

    /**
     * Derives the active runtime driver set from canonical Kernel config inputs.
     *
     * @throws RuntimeDriverInvalidConfigException
     */
    public function detect(ConfigRepositoryInterface $cfg): RuntimeDrivers
    {
        return new RuntimeDrivers(
            self::configuredHttpDriver($cfg),
        );
    }

    /**
     * Resolves Kernel-selected runtime drivers plus explicit owner contributions.
     *
     * @throws RuntimeDriverConflictException
     * @throws RuntimeDriverInvalidConfigException
     */
    public function resolve(
        ConfigRepositoryInterface $cfg,
        RuntimeDriverContributions $contributions,
    ): RuntimeDrivers {
        return self::composeRuntimeDrivers(
            kernelHttpDriver: self::configuredHttpDriver($cfg),
            contributions: $contributions,
        );
    }

    /**
     * Asserts config-only runtime driver compatibility.
     *
     * This method intentionally does not inspect ModulePlan.
     *
     * @throws RuntimeDriverInvalidConfigException
     */
    public function assertCompatible(ConfigRepositoryInterface $cfg): void
    {
        $this->detect($cfg);
    }

    /**
     * Resolves the composed runtime-driver set and validates HTTP driver
     * compatibility against the caller-provided ModulePlan.
     *
     * TODO(kernel-boundaries): move platform-owned HTTP module compatibility
     * checks out of Kernel runtime guard in a follow-up boundary refactor.
     *
     * The ModulePlan is caller-provided. This method must not resolve it
     * internally and must not inspect Composer metadata, providers, package
     * paths, module manifests, generated artifacts, config source files, or
     * container services.
     *
     * @throws RuntimeDriverConflictException
     * @throws RuntimeDriverInvalidConfigException
     */
    public function resolveForModules(
        ConfigRepositoryInterface $cfg,
        ModulePlan $plan,
        RuntimeDriverContributions $contributions,
    ): RuntimeDrivers {
        $drivers = $this->resolve($cfg, $contributions);
        $httpDriver = $drivers->httpDriver();

        if (!self::httpDriverRequiresPlatformHttp($httpDriver)) {
            return $drivers;
        }

        if ($plan->hasEnabledModule(self::MODULE_PLATFORM_HTTP)) {
            return $drivers;
        }

        throw RuntimeDriverInvalidConfigException::requiresPlatformHttpModule(
            $drivers->driverIds(),
        );
    }

    /**
     * @param ConfigRepositoryInterface $cfg
     * @return HttpDriver
     */
    private static function configuredHttpDriver(ConfigRepositoryInterface $cfg): HttpDriver
    {
        if (!$cfg->has(self::CONFIG_HTTP_DRIVER)) {
            throw RuntimeDriverInvalidConfigException::configKeyMissing();
        }

        $httpDriver = $cfg->get(self::CONFIG_HTTP_DRIVER);

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

    private static function httpDriverRequiresPlatformHttp(HttpDriver $httpDriver): bool
    {
        return match ($httpDriver) {
            HttpDriver::FRANKENPHP,
            HttpDriver::SWOOLE,
            HttpDriver::ROADRUNNER,
            HttpDriver::WORKER => true,
            HttpDriver::CLASSIC => false,
        };
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
