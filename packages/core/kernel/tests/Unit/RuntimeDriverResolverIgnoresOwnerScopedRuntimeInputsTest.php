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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeDriverResolverIgnoresOwnerScopedRuntimeInputsTest extends TestCase
{
    public function testResolveIgnoresWorkerTaskTypeInConfigOnlyResolution(): void
    {
        $cfg = new ArrayConfigRepository([
            'kernel' => [
                'runtime' => [
                    'http_driver' => 'http.classic',
                ],
            ],
            'worker' => [
                'task_type' => 'queue',
            ],
        ]);

        $drivers = new RuntimeDriverResolver()->resolve(
            config: $cfg,
            contributions: RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [],
            ),
        );

        self::assertSame(HttpDriver::CLASSIC, $drivers->httpDriver());
        self::assertSame([], $drivers->backgroundDrivers());
        self::assertSame(['http.classic'], $drivers->driverIds());
    }

    public function testResolveDoesNotReadWorkerTaskType(): void
    {
        $cfg = new class() implements ConfigRepositoryInterface {
            public function has(string $keyPath): bool
            {
                if ($keyPath === 'worker.task_type') {
                    throw new RuntimeException('worker-task-type-must-not-be-read');
                }

                return $keyPath === 'kernel.runtime.http_driver';
            }

            public function get(string $keyPath, mixed $default = null): mixed
            {
                if ($keyPath === 'worker.task_type') {
                    throw new RuntimeException('worker-task-type-must-not-be-read');
                }

                if ($keyPath === 'kernel.runtime.http_driver') {
                    return 'http.classic';
                }

                return $default;
            }

            /**
             * @return array<string,mixed>
             */
            public function all(): array
            {
                throw new RuntimeException('all-forbidden');
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                throw new RuntimeException('source-of-forbidden');
            }

            /**
             * @return list<ConfigValueSource>
             */
            public function explain(): array
            {
                throw new RuntimeException('explain-forbidden');
            }
        };

        $drivers = new RuntimeDriverResolver()->resolve(
            config: $cfg,
            contributions: RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [],
            ),
        );

        self::assertSame(['http.classic'], $drivers->driverIds());
    }
}
