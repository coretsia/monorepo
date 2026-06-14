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

namespace Coretsia\Tools\Tests\Integration\Runtime\Support;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use RuntimeException;

/**
 * In-memory ConfigRepositoryInterface used only by runtime-driver matrix tests.
 *
 * Unsupported source/introspection methods throw intentionally. RuntimeDriverGuard
 * must not call them.
 */
final readonly class RuntimeDriverMatrixConfigRepository implements ConfigRepositoryInterface
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private array $values,
    ) {
    }

    public function has(string $keyPath): bool
    {
        return array_key_exists($keyPath, $this->values);
    }

    public function get(string $keyPath, mixed $default = null): mixed
    {
        if (!array_key_exists($keyPath, $this->values)) {
            return $default;
        }

        return $this->values[$keyPath];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        throw new RuntimeException('runtime-driver-matrix-config-repository-all-forbidden');
    }

    public function sourceOf(string $keyPath): ?ConfigValueSource
    {
        throw new RuntimeException('runtime-driver-matrix-config-repository-source-of-forbidden');
    }

    /**
     * @return list<ConfigValueSource>
     */
    public function explain(): array
    {
        throw new RuntimeException('runtime-driver-matrix-config-repository-explain-forbidden');
    }
}
