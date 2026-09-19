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

use Coretsia\Foundation\Context\Exception\ContextInvalidKeyException;

/**
 * Internal context value copy boundary.
 *
 * Context values are json-like runtime values only. Objects, closures,
 * resources, floats, unsupported types, and invalid map keys must be rejected
 * before values are copied into ContextStore or ContextBag.
 *
 * ContextStorePolicy resource limits must also be enforced before recursive
 * copying starts. ContextValues assumes that copied values already satisfy the
 * canonical Foundation context resource budget.
 *
 * @internal Foundation context implementation detail.
 */
final class ContextValues
{
    private function __construct()
    {
    }

    /**
     * Validates and copies a context value map from an untrusted constructor
     * boundary.
     *
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function validatedCopyMap(array $values): array
    {
        $policy = new ContextStorePolicy();
        $copy = [];

        foreach ($values as $key => $value) {
            if (!\is_string($key)) {
                throw new ContextInvalidKeyException((string)$key, 'context-key-invalid');
            }

            $policy->assertCanWrite($key, $value);

            $copy[$key] = self::copyValue($value);
        }

        return $copy;
    }

    /**
     * Copies an already validated context value map.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public static function copyMap(array $values): array
    {
        $copy = [];

        foreach ($values as $key => $value) {
            if (!\is_string($key)) {
                throw new \LogicException('context-values-copy-non-string-key');
            }

            $copy[$key] = self::copyValue($value);
        }

        return $copy;
    }

    public static function copyValue(mixed $value): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        if (!\is_array($value)) {
            throw new \LogicException('context-values-copy-non-json-like-value');
        }

        $copy = [];

        foreach ($value as $key => $item) {
            $copy[$key] = self::copyValue($item);
        }

        return $copy;
    }
}
