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
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverResolverResolvesClassicWithEmptyContributionsTest extends TestCase
{
    public function testResolvesClassicHttpWithEmptyContributions(): void
    {
        $drivers = new RuntimeDriverResolver()->resolve(
            config: self::config([
                'kernel.runtime.http_driver' => 'http.classic',
            ]),
            contributions: self::emptyContributions(),
        );

        self::assertSame(HttpDriver::CLASSIC, $drivers->httpDriver());
        self::assertSame('http.classic', $drivers->httpDriverId());
        self::assertSame([], $drivers->backgroundDrivers());
        self::assertSame([], $drivers->backgroundDriverIds());
        self::assertSame(['http.classic'], $drivers->driverIds());
    }

    private static function emptyContributions(): RuntimeDriverContributions
    {
        return RuntimeDriverContributions::fromDrivers(
            httpDrivers: [],
            backgroundDrivers: [],
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function config(array $values): ConfigRepositoryInterface
    {
        return new class($values) implements ConfigRepositoryInterface {
            /**
             * @param array<string, mixed> $values
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
                return $this->values[$keyPath] ?? $default;
            }

            /** @return array<string, mixed> */
            public function all(): array
            {
                throw new \RuntimeException('runtime-driver-resolver-test-config-all-forbidden');
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                throw new \RuntimeException('runtime-driver-resolver-test-config-source-of-forbidden');
            }

            /** @return list<ConfigValueSource> */
            public function explain(): array
            {
                throw new \RuntimeException('runtime-driver-resolver-test-config-explain-forbidden');
            }
        };
    }
}
