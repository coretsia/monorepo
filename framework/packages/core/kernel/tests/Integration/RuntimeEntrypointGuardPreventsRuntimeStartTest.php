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
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\TestCase;

final class RuntimeEntrypointGuardPreventsRuntimeStartTest extends TestCase
{
    public function testResolveEntrypointDriversReturnsValidatedComposedDrivers(): void
    {
        $drivers = self::guard()->resolveEntrypointDrivers(
            config: new ArrayConfigRepository(
                self::runtimeConfig(
                    httpDriver: 'http.classic',
                    workerTaskType: null,
                ),
            ),
            modulePlan: self::modulePlan([
                'platform.http',
                'platform.worker',
            ]),
            runtimeDriverContributions: RuntimeDriverContributions::fromDrivers(
                httpDrivers: [
                    HttpDriver::WORKER,
                ],
                backgroundDrivers: [],
            ),
        );

        self::assertSame(HttpDriver::WORKER, $drivers->httpDriver());
        self::assertSame('http.worker', $drivers->httpDriverId());
        self::assertSame([], $drivers->backgroundDrivers());
        self::assertSame([], $drivers->backgroundDriverIds());
        self::assertSame(
            [
                'http.worker',
            ],
            $drivers->driverIds(),
        );
    }

    public function testRoadrunnerWithoutPlatformHttpFailsBeforeRuntimeStart(): void
    {
        $started = false;
        $config = self::runtimeConfig(
            httpDriver: 'http.roadrunner',
            workerTaskType: null,
        );

        try {
            self::guard()->assertEntrypointAllowed(
                config: new ArrayConfigRepository($config),
                modulePlan: self::modulePlan([]),
                runtimeDriverContributions: self::noRuntimeDriverContributions(),
            );

            $started = true;

            self::fail('Expected runtime entrypoint guard to reject roadrunner without platform.http.');
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertFalse($started);
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_REQUIRES_PLATFORM_HTTP_MODULE,
                $exception->reason(),
            );
            self::assertSame(['http.roadrunner'], $exception->activeDriverIds());
            self::assertSame(['platform.http'], $exception->requiredModuleIds());
        }
    }

    public function testRoadrunnerWithPlatformHttpIsAllowed(): void
    {
        self::guard()->assertEntrypointAllowed(
            config: new ArrayConfigRepository(
                self::runtimeConfig(
                    httpDriver: 'http.roadrunner',
                    workerTaskType: null,
                )
            ),
            modulePlan: self::modulePlan(['platform.http']),
            runtimeDriverContributions: self::noRuntimeDriverContributions(),
        );

        self::assertTrue(true);
    }

    public function testClassicHttpWithoutPlatformHttpIsAllowed(): void
    {
        self::guard()->assertEntrypointAllowed(
            config: new ArrayConfigRepository(
                self::runtimeConfig(
                    httpDriver: 'http.classic',
                    workerTaskType: null,
                )
            ),
            modulePlan: self::modulePlan([]),
            runtimeDriverContributions: self::noRuntimeDriverContributions(),
        );

        self::assertTrue(true);
    }

    public function testMissingRuntimeDriverConfigFailsBeforeRuntimeStart(): void
    {
        $started = false;

        try {
            self::guard()->assertEntrypointAllowed(
                config: new ArrayConfigRepository([
                    'kernel' => [
                        'runtime' => [
                            'frankenphp' => [
                                'enabled' => false,
                            ],
                        ],
                    ],
                ]),
                modulePlan: self::modulePlan([]),
                runtimeDriverContributions: self::noRuntimeDriverContributions(),
            );

            $started = true;

            self::fail('Expected runtime entrypoint guard to reject incomplete runtime driver config.');
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertFalse($started);
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_MISSING,
                $exception->reason(),
            );
        }
    }

    public function testClassicHttpWithoutWorkerRootIsAllowedWhenPlatformWorkerIsNotEnabled(): void
    {
        self::guard()->assertEntrypointAllowed(
            config: new ArrayConfigRepository(
                self::runtimeConfig(
                    httpDriver: 'http.classic',
                    workerTaskType: null,
                )
            ),
            modulePlan: self::modulePlan([]),
            runtimeDriverContributions: self::noRuntimeDriverContributions(),
        );

        self::assertTrue(true);
    }

    public function testMissingWorkerTaskTypeIsOutOfScopeForKernelEntrypointGuard(): void
    {
        self::guard()->assertEntrypointAllowed(
            config: new ArrayConfigRepository(
                self::runtimeConfig(
                    httpDriver: 'http.classic',
                    workerTaskType: null,
                )
            ),
            modulePlan: self::modulePlan(['platform.worker']),
            runtimeDriverContributions: self::noRuntimeDriverContributions(),
        );

        self::assertTrue(true);
    }

    private static function noRuntimeDriverContributions(): RuntimeDriverContributions
    {
        return RuntimeDriverContributions::fromDrivers(
            httpDrivers: [],
            backgroundDrivers: [],
        );
    }

    private static function guard(): RuntimeEntrypointGuard
    {
        return new RuntimeEntrypointGuard();
    }

    /**
     * @return array<string, mixed>
     */
    private static function runtimeConfig(
        string $httpDriver,
        ?string $workerTaskType = null,
    ): array {
        $config = [
            'kernel' => [
                'runtime' => [
                    'http_driver' => $httpDriver,
                ],
            ],
        ];

        if ($workerTaskType !== null) {
            $config['worker'] = [
                'task_type' => $workerTaskType,
            ];
        }

        return $config;
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
                composerName: 'coretsia/' . \str_replace('.', '-', $moduleId->value()),
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
}
