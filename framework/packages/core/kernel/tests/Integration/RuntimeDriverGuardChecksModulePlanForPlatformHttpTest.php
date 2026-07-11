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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\Driver\BackgroundDriver;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverGuardChecksModulePlanForPlatformHttpTest extends TestCase
{
    /**
     * @param array<string,mixed> $config
     * @param list<string> $expectedActiveDriverIds
     */
    #[DataProvider('nonClassicHttpDriverProvider')]
    public function testNonClassicHttpDriversRequirePlatformHttpModule(
        array $config,
        array $expectedActiveDriverIds,
    ): void {
        try {
            new RuntimeDriverGuard()->assertHttpDriverCompatibleWithModules(
                cfg: self::config($config),
                plan: self::modulePlan([]),
                contributions: self::runtimeDriverContributions(null),
            );
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertSame(
                'CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG',
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_REQUIRES_PLATFORM_HTTP_MODULE,
                $exception->reason(),
            );
            self::assertSame($expectedActiveDriverIds, $exception->activeDriverIds());
            self::assertSame(['platform.http'], $exception->requiredModuleIds());

            return;
        }

        self::fail('Non-classic HTTP runtime drivers must require platform.http in the caller-provided ModulePlan.');
    }

    /**
     * @param array<string,mixed> $config
     */
    #[DataProvider('nonClassicHttpDriverProvider')]
    public function testNonClassicHttpDriversAreAllowedWhenPlatformHttpModuleIsEnabled(
        array $config,
        array $_expectedActiveDriverIds,
    ): void {
        new RuntimeDriverGuard()->assertHttpDriverCompatibleWithModules(
            cfg: self::config($config),
            plan: self::modulePlan(['platform.http']),
            contributions: self::runtimeDriverContributions(null),
        );

        self::assertTrue(true);
    }

    public function testWorkerTaskTypeConfigIsOutOfScopeForKernelRuntimeGuard(): void
    {
        $cfg = self::config([
            'kernel.runtime.http_driver' => 'http.classic',
            'worker.task_type' => 'http',
        ]);

        new RuntimeDriverGuard()->assertHttpDriverCompatibleWithModules(
            cfg: $cfg,
            plan: self::modulePlan([]),
            contributions: self::runtimeDriverContributions(null),
        );

        self::assertTrue(true);
    }

    public function testWorkerQueueBackgroundDriverDoesNotRequirePlatformHttpModule(): void
    {
        $cfg = self::config([
            'kernel.runtime.http_driver' => 'http.classic',
        ]);

        $drivers = new RuntimeDriverGuard()->assertHttpDriverCompatibleWithModules(
            cfg: $cfg,
            plan: self::modulePlan([]),
            contributions: self::runtimeDriverContributions('queue'),
        );

        self::assertSame(
            [
                'bg.worker_queue',
                'http.classic',
            ],
            $drivers->driverIds(),
        );
    }

    public function testWorkerHttpContributionRequiresPlatformHttp(): void
    {
        $cfg = self::config([
            'kernel.runtime.http_driver' => 'http.classic',
        ]);

        try {
            new RuntimeDriverGuard()->assertHttpDriverCompatibleWithModules(
                cfg: $cfg,
                plan: self::modulePlan([]),
                contributions: self::runtimeDriverContributions('http'),
            );
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_REQUIRES_PLATFORM_HTTP_MODULE,
                $exception->reason(),
            );
            self::assertSame(['http.worker'], $exception->activeDriverIds());
            self::assertSame(['platform.http'], $exception->requiredModuleIds());

            return;
        }

        self::fail('http.worker contribution must require platform.http.');
    }

    public function testWorkerHttpContributionIsAllowedWhenPlatformHttpIsEnabled(): void
    {
        $cfg = self::config([
            'kernel.runtime.http_driver' => 'http.classic',
        ]);

        $drivers = new RuntimeDriverGuard()->assertHttpDriverCompatibleWithModules(
            cfg: $cfg,
            plan: self::modulePlan(['platform.http']),
            contributions: self::runtimeDriverContributions('http'),
        );

        self::assertSame(['http.worker'], $drivers->driverIds());
    }

    /**
     * @return iterable<string, array{0:array<string,mixed>,1:list<string>}>
     */
    public static function nonClassicHttpDriverProvider(): iterable
    {
        yield 'frankenphp requires platform.http' => [
            [
                'kernel.runtime.http_driver' => 'http.frankenphp',
            ],
            [
                'http.frankenphp',
            ],
        ];

        yield 'roadrunner requires platform.http' => [
            [
                'kernel.runtime.http_driver' => 'http.roadrunner',
            ],
            [
                'http.roadrunner',
            ],
        ];

        yield 'swoole requires platform.http' => [
            [
                'kernel.runtime.http_driver' => 'http.swoole',
            ],
            [
                'http.swoole',
            ],
        ];
    }

    private static function runtimeDriverContributions(?string $workerTaskType): RuntimeDriverContributions
    {
        return match ($workerTaskType) {
            null => RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [],
            ),

            'queue' => RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [BackgroundDriver::WORKER_QUEUE],
            ),

            'http' => RuntimeDriverContributions::fromDrivers(
                httpDrivers: [HttpDriver::WORKER],
                backgroundDrivers: [],
            ),

            default => throw new \LogicException('runtime-driver-test-worker-task-type-invalid'),
        };
    }

    /**
     * @param list<string> $enabledModuleIds
     */
    private static function modulePlan(array $enabledModuleIds): ModulePlan
    {
        $enabled = self::moduleIds($enabledModuleIds);
        $entries = [];

        foreach ($enabled as $moduleId) {
            $entries[] = new ModulePlanEntry(
                moduleId: $moduleId,
                composerName: self::composerNameForModuleId($moduleId),
            );
        }

        return new ModulePlan(
            app: 'web',
            preset: 'micro',
            enabled: $enabled,
            disabled: [],
            optionalMissing: [],
            topologicalOrder: $enabled,
            modules: $entries,
            warnings: [],
        );
    }

    private static function composerNameForModuleId(ModuleId $moduleId): string
    {
        return 'coretsia/' . \str_replace('.', '-', $moduleId->value());
    }

    /**
     * @param list<string> $moduleIds
     *
     * @return list<ModuleId>
     */
    private static function moduleIds(array $moduleIds): array
    {
        \usort(
            $moduleIds,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return \array_map(
            static fn (string $moduleId): ModuleId => ModuleId::fromString($moduleId),
            $moduleIds,
        );
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function config(array $values): ArrayConfigRepository
    {
        $config = [
            'kernel' => [
                'runtime' => [
                    'http_driver' => $values['kernel.runtime.http_driver'],
                ],
            ],
        ];

        if (\array_key_exists('worker.task_type', $values)) {
            $config['worker'] = [
                'task_type' => $values['worker.task_type'],
            ];
        }

        return new ArrayConfigRepository($config);
    }
}
