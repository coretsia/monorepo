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
 * This guard derives active runtime drivers from Kernel-owned config inputs and,
 * for owner-scoped runtime inputs, the caller-provided ModulePlan.
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
 *
 * @internal Kernel runtime entrypoint implementation detail. Runtime adapters
 * should depend on RuntimeEntrypointGuard instead.
 */
final class RuntimeDriverGuard
{
    private const string CONFIG_HTTP_DRIVER = 'kernel.runtime.http_driver';

    private const string CONFIG_WORKER_TASK_TYPE = 'worker.task_type';

    private const string WORKER_TASK_TYPE_HTTP = 'http';
    private const string WORKER_TASK_TYPE_QUEUE = 'queue';

    private const string MODULE_PLATFORM_HTTP = 'platform.http';
    private const string MODULE_PLATFORM_WORKER = 'platform.worker';

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
     * This is the only guard method that applies ModulePlan-aware runtime
     * entrypoint validation, including Worker owner-scope and `platform.http`
     * requirements for selected non-classic HTTP drivers.
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
        $drivers = $this->detectForModulePlan($cfg, $plan);
        $httpDriver = $drivers->httpDriver();

        if (!self::httpDriverRequiresPlatformHttp($httpDriver)) {
            return;
        }

        if ($plan->hasEnabledModule(self::MODULE_PLATFORM_HTTP)) {
            return;
        }

        throw RuntimeDriverInvalidConfigException::requiresPlatformHttpModule(
            $drivers->driverIds(),
        );
    }

    /**
     * @return array{0: list<HttpDriver>, 1: list<BackgroundDriver>}
     */
    private static function activeDrivers(
        ConfigRepositoryInterface $cfg,
        bool $workerInputsInScope = true,
    ): array {
        $httpDrivers = self::configuredHttpDrivers($cfg);
        $backgroundDrivers = [];

        if (!$workerInputsInScope) {
            return [$httpDrivers, $backgroundDrivers];
        }

        if (!$cfg->has(self::CONFIG_WORKER_TASK_TYPE)) {
            throw RuntimeDriverInvalidConfigException::workerTaskTypeMissing(
                self::driverIdsFromDrivers($httpDrivers, $backgroundDrivers),
            );
        }

        $workerTaskType = $cfg->get(self::CONFIG_WORKER_TASK_TYPE);

        if (!\is_string($workerTaskType)) {
            throw RuntimeDriverInvalidConfigException::workerTaskTypeInvalid(
                self::driverIdsFromDrivers($httpDrivers, $backgroundDrivers),
            );
        }

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
     * @return list<HttpDriver>
     */
    private static function configuredHttpDrivers(ConfigRepositoryInterface $cfg): array
    {
        if (!$cfg->has(self::CONFIG_HTTP_DRIVER)) {
            throw RuntimeDriverInvalidConfigException::configKeyMissing();
        }

        $httpDriver = $cfg->get(self::CONFIG_HTTP_DRIVER);

        if (!\is_string($httpDriver)) {
            throw RuntimeDriverInvalidConfigException::configKeyInvalid();
        }

        return match ($httpDriver) {
            HttpDriver::CLASSIC->value => [],
            HttpDriver::FRANKENPHP->value => [HttpDriver::FRANKENPHP],
            HttpDriver::SWOOLE->value => [HttpDriver::SWOOLE],
            HttpDriver::ROADRUNNER->value => [HttpDriver::ROADRUNNER],
            default => throw RuntimeDriverInvalidConfigException::configKeyInvalid(),
        };
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

    private function detectForModulePlan(
        ConfigRepositoryInterface $cfg,
        ModulePlan $plan,
    ): RuntimeDrivers {
        [$httpDrivers, $backgroundDrivers] = self::activeDrivers(
            cfg: $cfg,
            workerInputsInScope: $plan->hasEnabledModule(self::MODULE_PLATFORM_WORKER),
        );

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
