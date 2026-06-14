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
 * Canonical runtime driver matrix guard.
 *
 * This guard derives active runtime drivers from canonical config inputs only.
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
 * This guard owns only runtime-driver matrix selection and the explicit
 * ModulePlan compatibility rule required by the runtime drivers SSoT.
 */
final class RuntimeDriverGuard
{
    private const string CONFIG_FRANKENPHP_ENABLED = 'kernel.runtime.frankenphp.enabled';
    private const string CONFIG_SWOOLE_ENABLED = 'kernel.runtime.swoole.enabled';
    private const string CONFIG_ROADRUNNER_ENABLED = 'kernel.runtime.roadrunner.enabled';

    private const string CONFIG_WORKER_ENABLED = 'worker.enabled';
    private const string CONFIG_WORKER_TASK_TYPE = 'worker.task_type';

    private const string WORKER_TASK_TYPE_HTTP = 'http';
    private const string WORKER_TASK_TYPE_QUEUE = 'queue';

    private const string MODULE_PLATFORM_HTTP = 'platform.http';

    /**
     * Derives the active runtime driver set from canonical config inputs.
     *
     * @throws RuntimeDriverConflictException
     * @throws RuntimeDriverInvalidConfigException
     */
    public function detect(ConfigRepositoryInterface $cfg): RuntimeDrivers
    {
        [$httpDrivers, $backgroundDrivers] = self::activeDrivers($cfg);

        if (\count($httpDrivers) > 1) {
            self::throwHttpDriverConflict($httpDrivers, $backgroundDrivers);
        }

        if ($httpDrivers === []) {
            $httpDrivers[] = HttpDriver::CLASSIC;
        }

        return new RuntimeDrivers(
            $httpDrivers[0],
            ...$backgroundDrivers,
        );
    }

    /**
     * Asserts config-only runtime driver matrix compatibility.
     *
     * This method intentionally does not inspect ModulePlan.
     *
     * @throws RuntimeDriverConflictException
     * @throws RuntimeDriverInvalidConfigException
     */
    public function assertCompatible(ConfigRepositoryInterface $cfg): void
    {
        $this->detect($cfg);
    }

    /**
     * Asserts runtime driver compatibility against the caller-provided ModulePlan.
     *
     * This is the only guard method that validates the `platform.http`
     * ModulePlan requirement for selected non-classic HTTP drivers.
     *
     * The ModulePlan is caller-provided. This method must not resolve it
     * internally and must not inspect Composer metadata, providers, package
     * paths, module manifests, generated artifacts, config source files, or
     * container services.
     *
     * @throws RuntimeDriverConflictException
     * @throws RuntimeDriverInvalidConfigException
     */
    public function assertHttpDriverCompatibleWithModules(
        ConfigRepositoryInterface $cfg,
        ModulePlan $plan,
    ): void {
        $drivers = $this->detect($cfg);
        $httpDriver = $drivers->httpDriver();

        if (!self::httpDriverRequiresPlatformHttp($httpDriver)) {
            return;
        }

        if (self::modulePlanHasEnabledModule($plan, self::MODULE_PLATFORM_HTTP)) {
            return;
        }

        throw RuntimeDriverInvalidConfigException::requiresPlatformHttpModule(
            $drivers->driverIds(),
        );
    }

    /**
     * @return array{0: list<HttpDriver>, 1: list<BackgroundDriver>}
     */
    private static function activeDrivers(ConfigRepositoryInterface $cfg): array
    {
        $httpDrivers = [];
        $backgroundDrivers = [];

        if ($cfg->get(self::CONFIG_FRANKENPHP_ENABLED, false) === true) {
            $httpDrivers[] = HttpDriver::FRANKENPHP;
        }

        if ($cfg->get(self::CONFIG_SWOOLE_ENABLED, false) === true) {
            $httpDrivers[] = HttpDriver::SWOOLE;
        }

        if ($cfg->get(self::CONFIG_ROADRUNNER_ENABLED, false) === true) {
            $httpDrivers[] = HttpDriver::ROADRUNNER;
        }

        if ($cfg->get(self::CONFIG_WORKER_ENABLED, false) !== true) {
            return [$httpDrivers, $backgroundDrivers];
        }

        if (!$cfg->has(self::CONFIG_WORKER_TASK_TYPE)) {
            return [$httpDrivers, $backgroundDrivers];
        }

        $workerTaskType = $cfg->get(self::CONFIG_WORKER_TASK_TYPE);

        if ($workerTaskType === self::WORKER_TASK_TYPE_HTTP) {
            $httpDrivers[] = HttpDriver::WORKER;

            return [$httpDrivers, $backgroundDrivers];
        }

        if ($workerTaskType === self::WORKER_TASK_TYPE_QUEUE) {
            $backgroundDrivers[] = BackgroundDriver::WORKER_QUEUE;

            return [$httpDrivers, $backgroundDrivers];
        }

        throw RuntimeDriverInvalidConfigException::workerTaskTypeInvalid(
            self::driverIdsFromDrivers($httpDrivers, $backgroundDrivers),
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

    private static function modulePlanHasEnabledModule(ModulePlan $plan, string $moduleId): bool
    {
        foreach ($plan->enabled() as $enabledModuleId) {
            if ($enabledModuleId->value() === $moduleId) {
                return true;
            }
        }

        return false;
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
