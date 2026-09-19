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

final class KernelConfigSubtreeShapeContractTest extends TestCase
{
    public function testKernelDefaultsReturnSubtreeOnlyWithoutRepeatedRoot(): void
    {
        $config = self::kernelConfig();

        self::assertArrayNotHasKey(
            'kernel',
            $config,
            'config/kernel.php must return the kernel subtree only, not a repeated kernel root.',
        );
    }

    public function testKernelDefaultsContainNoReservedDirectiveKeysAtAnyDepth(): void
    {
        self::assertSame(
            [],
            self::reservedDirectiveKeyPaths(self::kernelConfig()),
            'Kernel config subtree must not contain reserved @* keys.',
        );
    }

    public function testKernelDefaultsContainCanonicalUnitOfWorkAttributeLimits(): void
    {
        $config = self::kernelConfig();

        self::assertIsArray(
            $config['uow'] ?? null,
            'config/kernel.php must define the uow config namespace.',
        );

        self::assertIsArray(
            $config['uow']['attributes'] ?? null,
            'config/kernel.php must define the uow.attributes config namespace.',
        );

        self::assertSame(
            10,
            $config['uow']['attributes']['max_depth'] ?? null,
            'kernel.uow.attributes.max_depth default must be 10.',
        );

        self::assertSame(
            200,
            $config['uow']['attributes']['max_keys'] ?? null,
            'kernel.uow.attributes.max_keys default must be 200.',
        );
    }

    public function testKernelDefaultsContainPositiveIntegerUnitOfWorkAttributeLimits(): void
    {
        $config = self::kernelConfig();

        $maxDepth = $config['uow']['attributes']['max_depth'] ?? null;
        $maxKeys = $config['uow']['attributes']['max_keys'] ?? null;

        self::assertIsInt(
            $maxDepth,
            'kernel.uow.attributes.max_depth must be an integer.',
        );

        self::assertGreaterThan(
            0,
            $maxDepth,
            'kernel.uow.attributes.max_depth must be greater than zero.',
        );

        self::assertIsInt(
            $maxKeys,
            'kernel.uow.attributes.max_keys must be an integer.',
        );

        self::assertGreaterThan(
            0,
            $maxKeys,
            'kernel.uow.attributes.max_keys must be greater than zero.',
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function kernelConfig(): array
    {
        $config = require self::kernelConfigPath();

        if (!\is_array($config)) {
            self::fail('config/kernel.php must return an array.');
        }

        return $config;
    }

    private static function kernelConfigPath(): string
    {
        return \dirname(__DIR__, 2) . '/config/kernel.php';
    }

    /**
     * @param array<array-key, mixed> $value
     * @param list<string> $path
     *
     * @return list<string>
     */
    private static function reservedDirectiveKeyPaths(array $value, array $path = []): array
    {
        $violations = [];

        foreach ($value as $key => $item) {
            $keyAsString = (string)$key;
            $currentPath = [...$path, $keyAsString];

            if (\is_string($key) && \str_starts_with($key, '@')) {
                $violations[] = \implode('.', $currentPath);
            }

            if (\is_array($item)) {
                $violations = [
                    ...$violations,
                    ...self::reservedDirectiveKeyPaths($item, $currentPath),
                ];
            }
        }

        return $violations;
    }
}
