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
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;

/**
 * Always-on safe-write guard for ContextStore.
 *
 * The policy accepts only canonical keys from ContextKeys and JSON-safe
 * deterministic values:
 *
 * - null
 * - bool
 * - int
 * - string
 * - list<value>
 * - array<string,value>
 *
 * It rejects floats, objects, closures, resources, non-string map keys,
 * unknown keys, and reserved @* keys. Failure messages are deterministic and
 * safe: they include only keys, path-to-value, and stable reason tokens.
 */
final class ContextStorePolicy
{
    public function assertCanWrite(string $key, mixed $value): void
    {
        $this->assertKey($key);
        $this->assertValue($value, $key);
    }

    public function assertKey(string $key): void
    {
        if ($key === '') {
            throw new ContextInvalidKeyException($key, 'context-key-empty');
        }

        if (\str_starts_with($key, '@')) {
            throw new ContextInvalidKeyException($key, 'context-key-reserved');
        }

        if (!ContextKeys::isKnown($key)) {
            throw new ContextInvalidKeyException($key, 'context-key-unknown');
        }
    }

    public function assertValue(mixed $value, string $path = 'value'): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        if (\is_float($value)) {
            throw new ContextWriteForbiddenException($path, 'context-write-forbidden-float');
        }

        if ($value instanceof \Closure) {
            throw new ContextWriteForbiddenException($path, 'context-write-forbidden-closure');
        }

        if (\is_object($value)) {
            throw new ContextWriteForbiddenException($path, 'context-write-forbidden-object');
        }

        if (\is_resource($value)) {
            throw new ContextWriteForbiddenException($path, 'context-write-forbidden-resource');
        }

        if (\is_array($value)) {
            $this->assertArrayValue($value, $path);

            return;
        }

        throw new ContextWriteForbiddenException($path, 'context-write-forbidden-type');
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private function assertArrayValue(array $value, string $path): void
    {
        if (\array_is_list($value)) {
            foreach ($value as $index => $item) {
                $this->assertValue($item, self::listPath($path, $index));
            }

            return;
        }

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new ContextWriteForbiddenException($path, 'context-write-forbidden-map-key');
            }

            $this->assertValue($item, self::mapPath($path, $key));
        }
    }

    private static function listPath(string $path, int $index): string
    {
        return $path . '[' . $index . ']';
    }

    private static function mapPath(string $path, string $key): string
    {
        if ($path === '') {
            return $key;
        }

        return $path . '.' . $key;
    }
}
