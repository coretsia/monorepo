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
 * Canonical UnitOfWork outcome tokens.
 *
 * This class is intentionally enum-like instead of a native PHP enum:
 *
 * - exported UnitOfWork shapes carry plain strings;
 * - no object instance may cross Kernel hook/export boundaries;
 * - tokens are stable lowercase ASCII values;
 * - comparisons are byte-for-byte string comparisons.
 *
 * This class owns only the stable outcome vocabulary. HTTP/CLI outcome mapping
 * is policy-owned by `docs/ssot/uow-outcome-policy.md` and is implemented by
 * runtime/adapters in their owning epics.
 *
 * @phpstan-type OutcomeValue self::SUCCESS|self::HANDLED_ERROR|self::FATAL_ERROR
 */
final class Outcome
{
    public const string SUCCESS = 'success';
    public const string HANDLED_ERROR = 'handled_error';
    public const string FATAL_ERROR = 'fatal_error';

    /**
     * @var list<self::SUCCESS|self::HANDLED_ERROR|self::FATAL_ERROR>
     */
    private const array VALUES = [
        self::SUCCESS,
        self::HANDLED_ERROR,
        self::FATAL_ERROR,
    ];

    private function __construct()
    {
    }

    /**
     * Returns canonical UnitOfWork outcome tokens in stable declaration order.
     *
     * The returned order is not a sorting rule for exported shapes. Shape
     * export ordering is governed by `docs/ssot/uow-shapes.md`.
     *
     * @return list<self::SUCCESS|self::HANDLED_ERROR|self::FATAL_ERROR>
     */
    public static function values(): array
    {
        return self::VALUES;
    }

    /**
     * Checks whether the given string is a canonical UnitOfWork outcome token.
     */
    public static function isValid(string $value): bool
    {
        return match ($value) {
            self::SUCCESS,
            self::HANDLED_ERROR,
            self::FATAL_ERROR => true,
            default => false,
        };
    }
}
