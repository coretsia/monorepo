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

namespace Coretsia\Foundation\Discovery;

/**
 * Canonical deterministic ordering primitive for Foundation discovery lists.
 *
 * Single ordering rule:
 *
 * 1. priority DESC
 * 2. id ASC using byte-order string comparison (`strcmp`)
 *
 * This class is deliberately static and non-instantiable. It is not a DI
 * service, not a strategy extension point, and not a runtime dependency.
 */
final class DeterministicOrder
{
    private function __construct()
    {
    }

    /**
     * Compares two discovery entries by the canonical Foundation order.
     */
    public static function compare(
        int $leftPriority,
        string $leftId,
        int $rightPriority,
        string $rightId,
    ): int {
        if ($leftPriority !== $rightPriority) {
            return $rightPriority <=> $leftPriority;
        }

        return \strcmp($leftId, $rightId);
    }

    /**
     * Sorts a list by the canonical Foundation order.
     *
     * @template T
     *
     * @param list<T> $items
     * @param callable(T): string $idOf
     * @param callable(T): int $priorityOf
     *
     * @return list<T>
     */
    public static function sort(array $items, callable $idOf, callable $priorityOf): array
    {
        $sorted = \array_values($items);

        \usort(
            $sorted,
            static fn (mixed $left, mixed $right): int => self::compare(
                $priorityOf($left),
                $idOf($left),
                $priorityOf($right),
                $idOf($right),
            ),
        );

        return $sorted;
    }
}
