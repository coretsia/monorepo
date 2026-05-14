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

namespace Coretsia\Foundation\Runtime\Reset;

/**
 * Normalized reset priority.
 *
 * Reset priorities are deterministic ordering keys for enhanced reset planning.
 *
 * Higher priority values execute first. Comparison is numeric and does not use
 * locale, collation, string ordering, or platform-specific formatting.
 */
final readonly class ResetPriority
{
    private const string PATTERN = '/\A-?\d+\z/';

    private function __construct(
        private int $value,
    ) {
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function fromMetaValue(mixed $value): self
    {
        if (\is_int($value)) {
            return new self($value);
        }

        if (!\is_string($value)) {
            throw ResetException::metaInvalid();
        }

        if (\preg_match(self::PATTERN, $value) !== 1) {
            throw ResetException::metaInvalid();
        }

        return new self((int)$value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function compareDescending(self $other): int
    {
        return $other->value <=> $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
