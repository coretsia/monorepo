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

namespace Coretsia\Kernel\Runtime;

/**
 * Canonical UnitOfWork type tokens.
 *
 * This class is intentionally enum-like instead of a native PHP enum:
 *
 * - exported UnitOfWork shapes carry plain strings;
 * - no object instance may cross Kernel hook/export boundaries;
 * - tokens are stable lowercase ASCII values;
 * - comparisons are byte-for-byte string comparisons.
 *
 * @phpstan-type UnitOfWorkTypeValue self::HTTP|self::CLI|self::QUEUE|self::SCHEDULER
 */
final class UnitOfWorkType
{
    public const string HTTP = 'http';
    public const string CLI = 'cli';
    public const string QUEUE = 'queue';
    public const string SCHEDULER = 'scheduler';

    /**
     * @var list<self::HTTP|self::CLI|self::QUEUE|self::SCHEDULER>
     */
    private const array VALUES = [
        self::HTTP,
        self::CLI,
        self::QUEUE,
        self::SCHEDULER,
    ];

    private function __construct()
    {
    }

    /**
     * Returns canonical UnitOfWork type tokens in stable declaration order.
     *
     * The returned order is not a sorting rule for exported shapes. Shape
     * export ordering is governed by `docs/ssot/uow-shapes.md`.
     *
     * @return list<self::HTTP|self::CLI|self::QUEUE|self::SCHEDULER>
     */
    public static function values(): array
    {
        return self::VALUES;
    }

    /**
     * Checks whether the given string is a canonical UnitOfWork type token.
     */
    public static function isValid(string $value): bool
    {
        return match ($value) {
            self::HTTP,
            self::CLI,
            self::QUEUE,
            self::SCHEDULER => true,
            default => false,
        };
    }
}
