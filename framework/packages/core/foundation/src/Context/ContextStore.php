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

namespace Coretsia\Foundation\Context;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Runtime\ResetInterface;

/**
 * Mutable Foundation-owned runtime context store.
 *
 * ContextStore is unit-of-work-local state. Every write is guarded by
 * ContextStorePolicy, and reset() clears all stored values between UoWs.
 */
final class ContextStore implements ContextAccessorInterface, ResetInterface
{
    /**
     * @var array<string,mixed>
     */
    private array $values = [];

    public function __construct(
        private readonly ContextStorePolicy $policy = new ContextStorePolicy(),
    ) {
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->values);
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }

        return self::copyValue($this->values[$key]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->policy->assertCanWrite($key, $value);

        $this->values[$key] = self::copyValue($value);
    }

    public function remove(string $key): void
    {
        $this->policy->assertKey($key);

        unset($this->values[$key]);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function snapshot(): ContextBag
    {
        return new ContextBag($this->values);
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return self::copyMap($this->values);
    }

    public function reset(): void
    {
        $this->clear();
    }

    /**
     * @param array<string,mixed> $values
     *
     * @return array<string,mixed>
     */
    private static function copyMap(array $values): array
    {
        $copy = [];

        foreach ($values as $key => $value) {
            $copy[$key] = self::copyValue($value);
        }

        return $copy;
    }

    private static function copyValue(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        $copy = [];

        foreach ($value as $key => $item) {
            $copy[$key] = self::copyValue($item);
        }

        return $copy;
    }
}
