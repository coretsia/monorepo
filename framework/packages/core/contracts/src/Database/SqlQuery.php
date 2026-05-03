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

namespace Coretsia\Contracts\Database;

use InvalidArgumentException;

/**
 * Immutable contracts-level SQL query value object.
 *
 * This value object carries opaque SQL text and positional bindings through the
 * database contracts boundary without making SQL stringification implicit.
 *
 * SQL text is structurally validated only. It must be non-empty after trimming,
 * but it is otherwise treated as opaque application data. This class does not
 * parse, normalize, classify, validate SQL grammar, strip comments, infer query
 * operation kind, quote identifiers, compile SQL, or provide raw SQL
 * diagnostics.
 *
 * Bindings are positional only and preserve order. Binding values are limited
 * to the canonical database value domain:
 *
 * DbValue = int|string|bool|null
 *
 * This value object is outside DTO marker policy by default.
 */
final readonly class SqlQuery implements SqlQueryInterface
{
    private string $sql;

    /**
     * @var list<int|string|bool|null>
     */
    private array $bindings;

    /**
     * @param non-empty-string $sql Opaque SQL text. Must be non-empty after trimming.
     * @param list<int|string|bool|null> $bindings
     *
     * @throws InvalidArgumentException when the SQL string is empty,
     *                                  whitespace-only, bindings are not a
     *                                  positional list, or a binding value is
     *                                  outside the database value domain.
     */
    public function __construct(string $sql, array $bindings = [])
    {
        if (trim($sql) === '') {
            throw new InvalidArgumentException('SQL query must be a non-empty string.');
        }

        if (!array_is_list($bindings)) {
            throw new InvalidArgumentException('SQL query bindings must be a positional list.');
        }

        foreach ($bindings as $binding) {
            if (
                $binding === null
                || is_int($binding)
                || is_string($binding)
                || is_bool($binding)
            ) {
                continue;
            }

            throw new InvalidArgumentException(
                'SQL query bindings must contain only int, string, bool, or null values.'
            );
        }

        $this->sql = $sql;
        $this->bindings = $bindings;
    }

    /**
     * Returns the raw SQL string carried by this query.
     *
     * The returned string is opaque SQL text and MUST NOT be logged, traced,
     * exported as a metric label, copied into error descriptors, printed by
     * migration tooling, or exposed through unsafe diagnostics by default.
     *
     * @return non-empty-string
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * Returns positional query bindings.
     *
     * Binding order is semantic and MUST be preserved.
     *
     * @return list<int|string|bool|null>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }
}
