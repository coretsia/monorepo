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
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeDriverGuardRejectsWorkerHttpWithAnyConfiguredHttpDriverTest extends TestCase
{
    /**
     * @param array<string,mixed> $runtimeConfig
     * @param list<string> $expectedDriverIds
     */
    #[DataProvider('workerHttpConflictProvider')]
    public function testDetectRejectsWorkerHttpWithAnyConfiguredHttpDriver(
        array $runtimeConfig,
        array $expectedDriverIds,
    ): void {
        try {
            new RuntimeDriverGuard()->detect(self::config($runtimeConfig));
        } catch (RuntimeDriverConflictException $exception) {
            self::assertSame(
                RuntimeDriverConflictException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
                $exception->reason(),
            );
            self::assertSame($expectedDriverIds, $exception->activeDriverIds());
            self::assertSame($expectedDriverIds, $exception->conflictingDriverIds());

            return;
        }

        self::fail('RuntimeDriverGuard must reject http.worker with any configured HTTP runtime driver.');
    }

    /**
     * @param array<string,mixed> $runtimeConfig
     * @param list<string> $expectedDriverIds
     */
    #[DataProvider('workerHttpConflictProvider')]
    public function testAssertCompatibleRejectsWorkerHttpWithAnyConfiguredHttpDriver(
        array $runtimeConfig,
        array $expectedDriverIds,
    ): void {
        try {
            new RuntimeDriverGuard()->assertCompatible(self::config($runtimeConfig));
        } catch (RuntimeDriverConflictException $exception) {
            self::assertSame(
                RuntimeDriverConflictException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
                $exception->reason(),
            );
            self::assertSame($expectedDriverIds, $exception->activeDriverIds());
            self::assertSame($expectedDriverIds, $exception->conflictingDriverIds());

            return;
        }

        self::fail(
            'RuntimeDriverGuard::assertCompatible() must reject http.worker with any configured HTTP runtime driver.'
        );
    }

    /**
     * @return iterable<string, array{0:array<string,mixed>,1:list<string>}>
     */
    public static function workerHttpConflictProvider(): iterable
    {
        yield 'frankenphp + worker http' => [
            [
                'kernel.runtime.frankenphp.enabled' => true,
                'kernel.runtime.swoole.enabled' => false,
                'kernel.runtime.roadrunner.enabled' => false,
                'worker.enabled' => true,
                'worker.task_type' => 'http',
            ],
            [
                'http.frankenphp',
                'http.worker',
            ],
        ];

        yield 'roadrunner + worker http' => [
            [
                'kernel.runtime.frankenphp.enabled' => false,
                'kernel.runtime.swoole.enabled' => false,
                'kernel.runtime.roadrunner.enabled' => true,
                'worker.enabled' => true,
                'worker.task_type' => 'http',
            ],
            [
                'http.roadrunner',
                'http.worker',
            ],
        ];

        yield 'swoole + worker http' => [
            [
                'kernel.runtime.frankenphp.enabled' => false,
                'kernel.runtime.swoole.enabled' => true,
                'kernel.runtime.roadrunner.enabled' => false,
                'worker.enabled' => true,
                'worker.task_type' => 'http',
            ],
            [
                'http.swoole',
                'http.worker',
            ],
        ];
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
                throw new RuntimeException('runtime-driver-guard-test-config-all-forbidden');
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                throw new RuntimeException('runtime-driver-guard-test-config-source-of-forbidden');
            }

            /**
             * @return list<ConfigValueSource>
             */
            public function explain(): array
            {
                throw new RuntimeException('runtime-driver-guard-test-config-explain-forbidden');
            }
        };
    }
}
