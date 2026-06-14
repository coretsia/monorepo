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
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeDriverGuardRejectsWorkerHttpWithRoadrunnerTest extends TestCase
{
    public function testDetectRejectsWorkerHttpWithRoadrunnerHttpDriver(): void
    {
        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => true,
            'worker.enabled' => true,
            'worker.task_type' => 'http',
        ]);

        try {
            new RuntimeDriverGuard()->detect($cfg);
        } catch (RuntimeDriverConflictException $exception) {
            self::assertSame(
                RuntimeDriverConflictException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
                $exception->reason(),
            );
            self::assertSame(
                [
                    'http.roadrunner',
                    'http.worker',
                ],
                $exception->activeDriverIds(),
            );
            self::assertSame(
                [
                    'http.roadrunner',
                    'http.worker',
                ],
                $exception->conflictingDriverIds(),
            );

            return;
        }

        self::fail('RuntimeDriverGuard must reject http.worker with http.roadrunner.');
    }

    public function testAssertCompatibleRejectsWorkerHttpWithRoadrunnerHttpDriver(): void
    {
        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => true,
            'worker.enabled' => true,
            'worker.task_type' => 'http',
        ]);

        try {
            new RuntimeDriverGuard()->assertCompatible($cfg);
        } catch (RuntimeDriverConflictException $exception) {
            self::assertSame(
                RuntimeDriverConflictException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
                $exception->reason(),
            );
            self::assertSame(
                [
                    'http.roadrunner',
                    'http.worker',
                ],
                $exception->activeDriverIds(),
            );
            self::assertSame(
                [
                    'http.roadrunner',
                    'http.worker',
                ],
                $exception->conflictingDriverIds(),
            );

            return;
        }

        self::fail('RuntimeDriverGuard::assertCompatible() must reject http.worker with http.roadrunner.');
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
