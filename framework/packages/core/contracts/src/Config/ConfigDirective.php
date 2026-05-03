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

namespace Coretsia\Contracts\Config;

/**
 * Canonical config directive allowlist.
 *
 * Directive keys use the "@" prefix in config input, but enum values are stored
 * without that prefix.
 */
enum ConfigDirective: string
{
    private const string PREFIX = '@';

    case Append = 'append';
    case Prepend = 'prepend';
    case Remove = 'remove';
    case Merge = 'merge';
    case Replace = 'replace';

    /**
     * @return non-empty-string
     */
    public function key(): string
    {
        return self::PREFIX . $this->value;
    }

    public static function fromKey(string $key): self
    {
        $directive = self::tryFromKey($key);

        if ($directive === null) {
            throw new \InvalidArgumentException('Invalid config directive key.');
        }

        return $directive;
    }

    public static function tryFromKey(string $key): ?self
    {
        if (!str_starts_with($key, self::PREFIX)) {
            return null;
        }

        return self::tryFrom(substr($key, strlen(self::PREFIX)));
    }

    public static function isAllowed(string $name): bool
    {
        return self::isAllowedName($name);
    }

    public static function isAllowedName(string $name): bool
    {
        return self::tryFrom($name) !== null;
    }

    public static function isAllowedKey(string $key): bool
    {
        return self::tryFromKey($key) !== null;
    }

    public static function isReservedDirectiveKey(string $key): bool
    {
        return str_starts_with($key, self::PREFIX);
    }

    /**
     * @return list<non-empty-string>
     */
    public static function names(): array
    {
        return [
            self::Append->value,
            self::Prepend->value,
            self::Remove->value,
            self::Merge->value,
            self::Replace->value,
        ];
    }

    /**
     * @return list<non-empty-string>
     */
    public static function keys(): array
    {
        return [
            self::Append->key(),
            self::Prepend->key(),
            self::Remove->key(),
            self::Merge->key(),
            self::Replace->key(),
        ];
    }
}
