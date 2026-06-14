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

namespace Coretsia\Kernel\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class KernelRuntimeDriverConfigDefaultsContractTest extends TestCase
{
    public function testKernelRuntimeDriverDefaultsAreAllDisabled(): void
    {
        $config = self::kernelConfig();

        self::assertIsArray(
            $config['runtime'] ?? null,
            'config/kernel.php must define the runtime config namespace.',
        );

        self::assertSame(
            false,
            $config['runtime']['frankenphp']['enabled'] ?? null,
            'kernel.runtime.frankenphp.enabled default must be false.',
        );

        self::assertSame(
            false,
            $config['runtime']['swoole']['enabled'] ?? null,
            'kernel.runtime.swoole.enabled default must be false.',
        );

        self::assertSame(
            false,
            $config['runtime']['roadrunner']['enabled'] ?? null,
            'kernel.runtime.roadrunner.enabled default must be false.',
        );
    }

    public function testKernelRuntimeDriverDefaultsDefineOnlyKernelOwnedRuntimeFlags(): void
    {
        $config = self::kernelConfig();

        self::assertSame(
            [
                'frankenphp',
                'roadrunner',
                'swoole',
            ],
            self::sortedKeys($config['runtime'] ?? null),
            'kernel.runtime defaults must contain only Kernel-owned runtime driver flags.',
        );

        self::assertSame(
            ['enabled'],
            self::sortedKeys($config['runtime']['frankenphp'] ?? null),
            'kernel.runtime.frankenphp must contain only enabled.',
        );

        self::assertSame(
            ['enabled'],
            self::sortedKeys($config['runtime']['swoole'] ?? null),
            'kernel.runtime.swoole must contain only enabled.',
        );

        self::assertSame(
            ['enabled'],
            self::sortedKeys($config['runtime']['roadrunner'] ?? null),
            'kernel.runtime.roadrunner must contain only enabled.',
        );
    }

    public function testKernelDefaultsDoNotIntroduceWorkerRoot(): void
    {
        $config = self::kernelConfig();

        self::assertArrayNotHasKey(
            'worker',
            $config,
            'core/kernel config defaults must not introduce the future-owned worker root.',
        );

        self::assertSame(
            [],
            self::pathsForArrayKey($config, 'worker'),
            'core/kernel config defaults must not define worker.* keys at any depth.',
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelConfig(): array
    {
        $config = require self::kernelConfigPath();

        self::assertIsArray($config);

        /** @var array<string,mixed> $config */
        return $config;
    }

    private static function kernelConfigPath(): string
    {
        return \dirname(__DIR__, 2) . '/config/kernel.php';
    }

    /**
     * @return list<string>
     */
    private static function sortedKeys(mixed $value): array
    {
        self::assertIsArray($value);

        $keys = \array_keys($value);

        foreach ($keys as $key) {
            self::assertIsString($key);
        }

        \usort(
            $keys,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        /** @var list<string> $keys */
        return $keys;
    }

    /**
     * @param array<array-key,mixed> $value
     * @param list<string> $path
     *
     * @return list<string>
     */
    private static function pathsForArrayKey(array $value, string $needle, array $path = []): array
    {
        $matches = [];

        foreach ($value as $key => $item) {
            $keyAsString = (string)$key;
            $currentPath = [...$path, $keyAsString];

            if ($key === $needle) {
                $matches[] = \implode('.', $currentPath);
            }

            if (\is_array($item)) {
                $matches = [
                    ...$matches,
                    ...self::pathsForArrayKey($item, $needle, $currentPath),
                ];
            }
        }

        return $matches;
    }
}
