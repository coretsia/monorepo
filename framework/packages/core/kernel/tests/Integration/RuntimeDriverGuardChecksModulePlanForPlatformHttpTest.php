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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        );

        self::assertTrue(true);
    }

    public function testClassicHttpDoesNotRequirePlatformHttpModule(): void
    {
        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => false,
            'worker.enabled' => false,
            'worker.task_type' => 'queue',
        ]);

        new RuntimeDriverGuard()->assertHttpDriverCompatibleWithModules(
            cfg: $cfg,
            plan: self::modulePlan([]),
        );

        self::assertSame(
            ['http.classic'],
            new RuntimeDriverGuard()->detect($cfg)->driverIds(),
        );
    }

    public function testWorkerQueueBackgroundDriverDoesNotRequirePlatformHttpModule(): void
    {
        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => false,
            'worker.enabled' => true,
            'worker.task_type' => 'queue',
        ]);

        new RuntimeDriverGuard()->assertHttpDriverCompatibleWithModules(
            cfg: $cfg,
            plan: self::modulePlan([]),
        );

        self::assertSame(
            [
                'bg.worker_queue',
                'http.classic',
            ],
            new RuntimeDriverGuard()->detect($cfg)->driverIds(),
        );
    }

    /**
     * @return iterable<string, array{0:array<string,mixed>,1:list<string>}>
     */
    public static function nonClassicHttpDriverProvider(): iterable
    {
        yield 'frankenphp requires platform.http' => [
            [
                'kernel.runtime.frankenphp.enabled' => true,
                'kernel.runtime.swoole.enabled' => false,
                'kernel.runtime.roadrunner.enabled' => false,
                'worker.enabled' => false,
                'worker.task_type' => 'queue',
            ],
            [
                'http.frankenphp',
            ],
        ];

        yield 'roadrunner requires platform.http' => [
            [
                'kernel.runtime.frankenphp.enabled' => false,
                'kernel.runtime.swoole.enabled' => false,
                'kernel.runtime.roadrunner.enabled' => true,
                'worker.enabled' => false,
                'worker.task_type' => 'queue',
            ],
            [
                'http.roadrunner',
            ],
        ];

        yield 'swoole requires platform.http' => [
            [
                'kernel.runtime.frankenphp.enabled' => false,
                'kernel.runtime.swoole.enabled' => true,
                'kernel.runtime.roadrunner.enabled' => false,
                'worker.enabled' => false,
                'worker.task_type' => 'queue',
            ],
            [
                'http.swoole',
            ],
        ];

        yield 'worker http requires platform.http' => [
            [
                'kernel.runtime.frankenphp.enabled' => false,
                'kernel.runtime.swoole.enabled' => false,
                'kernel.runtime.roadrunner.enabled' => false,
                'worker.enabled' => true,
                'worker.task_type' => 'http',
            ],
            [
                'http.worker',
            ],
        ];
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
    private static function config(array $values): ConfigRepositoryInterface
    {
        return new class($values) implements ConfigRepositoryInterface {
            /**
             * @param array<string,mixed> $values
             */
            public function __construct(
                private readonly array $values,
            ) {
            }

            public function has(string $keyPath): bool
            {
                return \array_key_exists($keyPath, $this->values);
            }

            public function get(string $keyPath, mixed $default = null): mixed
            {
                if (!\array_key_exists($keyPath, $this->values)) {
                    return $default;
                }

                return $this->values[$keyPath];
            }

            /**
             * @return array<string,mixed>
             */
            public function all(): array
            {
                throw new RuntimeException('runtime-driver-guard-module-plan-test-config-all-forbidden');
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                throw new RuntimeException('runtime-driver-guard-module-plan-test-config-source-of-forbidden');
            }

            /**
             * @return list<ConfigValueSource>
             */
            public function explain(): array
            {
                throw new RuntimeException('runtime-driver-guard-module-plan-test-config-explain-forbidden');
            }
        };
    }
}
