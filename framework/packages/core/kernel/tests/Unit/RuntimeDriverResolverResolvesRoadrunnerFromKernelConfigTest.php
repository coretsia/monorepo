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
use Coretsia\Kernel\Runtime\Driver\RuntimeDrivers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeDriverResolverResolvesRoadrunnerFromKernelConfigTest extends TestCase
{
    public function testResolvesRoadrunnerFromKernelConfig(): void
    {
        $drivers = self::resolve('http.roadrunner');

        self::assertSame(HttpDriver::ROADRUNNER, $drivers->httpDriver());
        self::assertSame('http.roadrunner', $drivers->httpDriverId());
        self::assertSame([], $drivers->backgroundDrivers());
        self::assertSame(['http.roadrunner'], $drivers->driverIds());
    }

    /**
     * @param non-empty-string $selector
     */
    #[DataProvider('additionalNonClassicSelectors')]
    public function testResolvesOtherNonClassicKernelSelectorsWithoutModulePlan(
        string $selector,
        HttpDriver $expected,
    ): void {
        $drivers = self::resolve($selector);

        self::assertSame($expected, $drivers->httpDriver());
        self::assertSame($selector, $drivers->httpDriverId());
        self::assertSame([], $drivers->backgroundDrivers());
        self::assertSame([$selector], $drivers->driverIds());
    }

    /**
     * @return iterable<string, array{0: non-empty-string, 1: HttpDriver}>
     */
    public static function additionalNonClassicSelectors(): iterable
    {
        yield 'frankenphp' => ['http.frankenphp', HttpDriver::FRANKENPHP];
        yield 'swoole' => ['http.swoole', HttpDriver::SWOOLE];
    }

    private static function resolve(string $selector): RuntimeDrivers
    {
        return new RuntimeDriverResolver()->resolve(
            config: self::config([
                'kernel.runtime.http_driver' => $selector,
            ]),
            contributions: RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [],
            ),
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function config(array $values): ConfigRepositoryInterface
    {
        return new class($values) implements ConfigRepositoryInterface {
            /** @param array<string, mixed> $values */
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
