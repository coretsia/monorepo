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

/**
 * Immutable snapshot view of Foundation runtime context values.
 *
 * A ContextBag represents a point-in-time copy of ContextStore data. It does
 * not observe later ContextStore mutations and does not expose mutable internal
 * arrays through read APIs.
 *
 * Direct construction applies the same ContextStorePolicy validation and
 * mandatory resource budget as mutable ContextStore writes.
 */
final class ContextBag implements ContextAccessorInterface
{
    /**
     * @var array<string,mixed>
     */
    private array $values;

    /**
     * @param array<string,mixed> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = ContextValues::validatedCopyMap($values);
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

        return ContextValues::copyValue($this->values[$key]);
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return ContextValues::copyMap($this->values);
    }
}
