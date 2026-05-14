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
 * Normalized reset group id.
 *
 * Reset groups are deterministic ordering keys for enhanced reset planning.
 *
 * The group id is a locale-independent string value intended for byte-order
 * comparison through strcmp().
 */
final readonly class ResetGroup
{
    private const string ASCII_WHITESPACE = " \t\n\r\f\v";

    private const string PATTERN = '/\A[a-z0-9][a-z0-9._-]*\z/';

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = \trim($value, self::ASCII_WHITESPACE);

        if ($normalized === '') {
            throw ResetException::metaInvalid();
        }

        if (\preg_match(self::PATTERN, $normalized) !== 1) {
            throw ResetException::metaInvalid();
        }

        return new self($normalized);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function compare(self $other): int
    {
        return \strcmp($this->value, $other->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
