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

namespace Coretsia\Foundation\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class FoundationConfigSubtreeShapeContractTest extends TestCase
{
    public function testFoundationDefaultsReturnSubtreeOnlyWithoutRepeatedRoot(): void
    {
        $config = self::foundationConfig();

        self::assertArrayNotHasKey(
            'foundation',
            $config,
            'config/foundation.php must return the foundation subtree only, not a repeated foundation root.',
        );
    }

    public function testFoundationDefaultsContainNoReservedDirectiveKeysAtAnyDepth(): void
    {
        self::assertSame(
            [],
            self::reservedDirectiveKeyPaths(self::foundationConfig()),
            'Foundation config subtree must not contain reserved @* keys.',
        );
    }

    public function testFoundationDefaultsDoNotDefineForbiddenFeatureFlags(): void
    {
        $config = self::foundationConfig();

        self::assertFalse(
            self::hasDotPath($config, 'tags.enabled'),
            'Foundation config subtree must not define forbidden config key: tags.enabled',
        );

        self::assertFalse(
            self::hasDotPath($config, 'reset.enabled'),
            'Foundation config subtree must not define forbidden config key: reset.enabled',
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function foundationConfig(): array
    {
        $config = require self::foundationConfigPath();

        if (!\is_array($config)) {
            self::fail('config/foundation.php must return an array.');
        }

        return $config;
    }

    private static function foundationConfigPath(): string
    {
        return \dirname(__DIR__, 2) . '/config/foundation.php';
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

    /**
     * @param array<array-key, mixed> $config
     */
    private static function hasDotPath(array $config, string $dotPath): bool
    {
        $current = $config;
        $segments = \explode('.', $dotPath);
        $lastIndex = \array_key_last($segments);

        foreach ($segments as $index => $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return false;
            }

            if ($index === $lastIndex) {
                return true;
            }

            $current = $current[$segment];
        }

        return false;
    }
}
