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
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeDriverGuardRejectsWorkerTaskTypeInvalidTest extends TestCase
{
    public function testRejectsInvalidWorkerTaskTypeWithDeterministicDiagnostics(): void
    {
        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => false,
            'worker.enabled' => true,
            'worker.task_type' => 'scheduler',
        ]);

        try {
            new RuntimeDriverGuard()->detect($cfg);
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertSame(
                RuntimeDriverInvalidConfigException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_WORKER_TASK_TYPE_INVALID,
                $exception->reason(),
            );
            self::assertSame([], $exception->activeDriverIds());
            self::assertSame([], $exception->requiredModuleIds());

            return;
        }

        self::fail('RuntimeDriverGuard must reject invalid worker.task_type values.');
    }

    public function testInvalidWorkerTaskTypeDoesNotLeakRawConfigDumpsOrEnvValues(): void
    {
        $rawWorkerTaskType = 'scheduler-with-raw-env-value-APP_SECRET_123';

        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => false,
            'worker.enabled' => true,
            'worker.task_type' => $rawWorkerTaskType,
            'APP_SECRET' => 'raw-env-secret-value',
        ]);

        try {
            new RuntimeDriverGuard()->detect($cfg);
        } catch (RuntimeDriverInvalidConfigException $exception) {
            $diagnosticSurface = [
                'message' => $exception->getMessage(),
                'errorCode' => $exception->errorCode(),
                'reason' => $exception->reason(),
                'activeDriverIds' => $exception->activeDriverIds(),
                'requiredModuleIds' => $exception->requiredModuleIds(),
            ];

            $encodedSurface = \json_encode(
                $diagnosticSurface,
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
            );

            self::assertStringNotContainsString($rawWorkerTaskType, $encodedSurface);
            self::assertStringNotContainsString('APP_SECRET', $encodedSurface);
            self::assertStringNotContainsString('raw-env-secret-value', $encodedSurface);
            self::assertStringNotContainsString('worker.task_type', $encodedSurface);
            self::assertStringNotContainsString('kernel.runtime', $encodedSurface);
            self::assertStringNotContainsString('scheduler-with-raw-env-value', $encodedSurface);

            self::assertSame(
                RuntimeDriverInvalidConfigException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_WORKER_TASK_TYPE_INVALID,
                $exception->reason(),
            );

            return;
        }

        self::fail('RuntimeDriverGuard must reject invalid worker.task_type values.');
    }

    public function testAssertCompatibleRejectsInvalidWorkerTaskType(): void
    {
        $cfg = self::config([
            'kernel.runtime.frankenphp.enabled' => false,
            'kernel.runtime.swoole.enabled' => false,
            'kernel.runtime.roadrunner.enabled' => false,
            'worker.enabled' => true,
            'worker.task_type' => 'scheduler',
        ]);

        try {
            new RuntimeDriverGuard()->assertCompatible($cfg);
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertSame(
                RuntimeDriverInvalidConfigException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_WORKER_TASK_TYPE_INVALID,
                $exception->reason(),
            );
            self::assertSame([], $exception->activeDriverIds());
            self::assertSame([], $exception->requiredModuleIds());

            return;
        }

        self::fail('RuntimeDriverGuard::assertCompatible() must reject invalid worker.task_type values.');
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
