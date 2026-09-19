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
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RuntimeDriverResolverRejectsInvalidHttpDriverConfigTest extends TestCase
{
    public function testResolveRejectsInvalidHttpDriverSelector(): void
    {
        $cfg = self::config([
            'kernel.runtime.http_driver' => 'http.reactphp',
        ]);

        try {
            new RuntimeDriverResolver()->resolve(
                config: $cfg,
                contributions: RuntimeDriverContributions::fromDrivers(
                    httpDrivers: [],
                    backgroundDrivers: [],
                ),
            );
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertSame(
                RuntimeDriverInvalidConfigException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID,
                $exception->reason(),
            );
            return;
        }

        self::fail('RuntimeDriverResolver must reject invalid kernel.runtime.http_driver values.');
    }

    public function testResolveRejectsNonStringHttpDriverSelector(): void
    {
        $cfg = self::config([
            'kernel.runtime.http_driver' => true,
        ]);

        $this->expectException(RuntimeDriverInvalidConfigException::class);
        $this->expectExceptionMessage(
            RuntimeDriverInvalidConfigException::ERROR_CODE
            . ': '
            . RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID,
        );

        new RuntimeDriverResolver()->resolve(
            config: $cfg,
            contributions: RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [],
            ),
        );
    }

    public function testInvalidConfigExceptionDoesNotExposeOwnerPackageDiagnostics(): void
    {
        foreach (
            [
                'requiresPlatformHttpModule',
                'requiresPlatformWorkerModule',
                'workerTaskTypeMissing',
                'workerTaskTypeInvalid',
                'activeDriverIds',
                'requiredModuleIds',
            ] as $method
        ) {
            self::assertFalse(
                \method_exists(RuntimeDriverInvalidConfigException::class, $method),
                $method,
            );
        }

        foreach (
            [
                'configKeyMissing',
                'configKeyInvalid',
                'errorCode',
                'reason',
            ] as $method
        ) {
            self::assertTrue(
                \method_exists(RuntimeDriverInvalidConfigException::class, $method),
                $method,
            );
        }
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
                throw new RuntimeException('runtime-driver-resolver-test-config-all-forbidden');
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                throw new RuntimeException('runtime-driver-resolver-test-config-source-of-forbidden');
            }

            /**
             * @return list<ConfigValueSource>
             */
            public function explain(): array
            {
                throw new RuntimeException('runtime-driver-resolver-test-config-explain-forbidden');
            }
        };
    }
}
