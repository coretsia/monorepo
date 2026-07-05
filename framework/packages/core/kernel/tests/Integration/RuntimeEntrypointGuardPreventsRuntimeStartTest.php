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
use Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\TestCase;

final class RuntimeEntrypointGuardPreventsRuntimeStartTest extends TestCase
{
    public function testRoadrunnerWithoutPlatformHttpFailsBeforeRuntimeStart(): void
    {
        $started = false;
        $config = self::runtimeConfig(
            frankenphp: false,
            swoole: false,
            roadrunner: true,
            workerTaskType: null,
        );

        try {
            self::guard()->assertEntrypointAllowed(
                config: new ArrayConfigRepository($config),
                modulePlan: self::modulePlan([]),
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
                    frankenphp: false,
                    swoole: false,
                    roadrunner: true,
                    workerTaskType: null,
                )
            ),
            modulePlan: self::modulePlan(['platform.http']),
        );

        self::assertTrue(true);
    }

    public function testClassicHttpWithoutPlatformHttpIsAllowed(): void
    {
        self::guard()->assertEntrypointAllowed(
            config: new ArrayConfigRepository(
                self::runtimeConfig(
                    frankenphp: false,
                    swoole: false,
                    roadrunner: false,
                    workerTaskType: null,
                )
            ),
            modulePlan: self::modulePlan([]),
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
            config: new ArrayConfigRepository([
                'kernel' => [
                    'runtime' => [
                        'frankenphp' => [
                            'enabled' => false,
                        ],
                        'swoole' => [
                            'enabled' => false,
                        ],
                        'roadrunner' => [
                            'enabled' => false,
                        ],
                    ],
                ],
            ]),
            modulePlan: self::modulePlan([]),
        );

        self::assertTrue(true);
    }

    public function testMissingWorkerTaskTypeFailsWhenPlatformWorkerIsEnabled(): void
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
                            'swoole' => [
                                'enabled' => false,
                            ],
                            'roadrunner' => [
                                'enabled' => false,
                            ],
                        ],
                    ],
                ]),
                modulePlan: self::modulePlan(['platform.worker']),
            );

            $started = true;

            self::fail('Expected missing worker.task_type to fail when platform.worker is enabled.');
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertFalse($started);
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_WORKER_TASK_TYPE_MISSING,
                $exception->reason(),
            );
            self::assertSame([], $exception->requiredModuleIds());
        }
    }

    private static function guard(): RuntimeEntrypointGuard
    {
        return new RuntimeEntrypointGuard();
    }

    /**
     * @return array<string, mixed>
     */
    private static function runtimeConfig(
        bool $frankenphp,
        bool $swoole,
        bool $roadrunner,
        ?string $workerTaskType = null,
    ): array {
        $config = [
            'kernel' => [
                'runtime' => [
                    'frankenphp' => [
                        'enabled' => $frankenphp,
                    ],
                    'swoole' => [
                        'enabled' => $swoole,
                    ],
                    'roadrunner' => [
                        'enabled' => $roadrunner,
                    ],
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
