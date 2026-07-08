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

final class RuntimeDriverGuardRejectsInvalidHttpDriverConfigTest extends TestCase
{
    public function testDetectRejectsInvalidHttpDriverSelector(): void
    {
        $cfg = self::config([
            'kernel.runtime.http_driver' => 'http.reactphp',
            'worker.task_type' => 'queue',
        ]);

        try {
            new RuntimeDriverGuard()->detect($cfg);
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertSame(
                RuntimeDriverInvalidConfigException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID,
                $exception->reason(),
            );
            self::assertSame([], $exception->activeDriverIds());
            self::assertSame([], $exception->requiredModuleIds());

            return;
        }

        self::fail('RuntimeDriverGuard must reject invalid kernel.runtime.http_driver values.');
    }

    public function testDetectRejectsNonStringHttpDriverSelector(): void
    {
        $cfg = self::config([
            'kernel.runtime.http_driver' => true,
            'worker.task_type' => 'queue',
        ]);

        $this->expectException(RuntimeDriverInvalidConfigException::class);
        $this->expectExceptionMessage(
            RuntimeDriverInvalidConfigException::ERROR_CODE
            . ': '
            . RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID,
        );

        new RuntimeDriverGuard()->detect($cfg);
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
