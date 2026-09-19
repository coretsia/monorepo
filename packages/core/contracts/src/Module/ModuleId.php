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

namespace Coretsia\Contracts\Module;

/**
 * Canonical module identifier.
 *
 * Format:
 *
 *     <layer>.<slug>
 *
 * Examples:
 *
 *     core.kernel
 *     platform.cli
 *     presets.micro
 *
 * The value is normalized using ASCII-only lowercase conversion and validated
 * without locale-sensitive behavior.
 */
final readonly class ModuleId
{
    private const string PATTERN = '/^[a-z][a-z0-9]*\.[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = self::normalize($value);

        if (!self::isValid($normalized)) {
            throw new \InvalidArgumentException('Invalid module id.');
        }

        $this->value = $normalized;
    }

    /**
     * @param non-empty-string $value
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * @param non-empty-string $layer
     * @param non-empty-string $slug
     */
    public static function fromLayerAndSlug(string $layer, string $slug): self
    {
        $normalizedLayer = self::normalizePart($layer);
        $normalizedSlug = self::normalizePart($slug);

        if (str_contains($normalizedLayer, '.') || str_contains($normalizedSlug, '.')) {
            throw new \InvalidArgumentException('Invalid module id parts.');
        }

        return new self($normalizedLayer . '.' . $normalizedSlug);
    }

    public static function isValid(string $value): bool
    {
        return preg_match(self::PATTERN, $value) === 1;
    }

    /**
     * @return non-empty-string
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * @return non-empty-string
     */
    public function layer(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /**
     * @return non-empty-string
     */
    public function slug(): string
    {
        return explode('.', $this->value, 2)[1];
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @return non-empty-string
     */
    private static function normalize(string $value): string
    {
        return self::asciiLower($value);
    }

    /**
     * @return non-empty-string
     */
    private static function normalizePart(string $value): string
    {
        return self::asciiLower($value);
    }

    /**
     * Locale-independent lowercase normalization for ASCII A-Z only.
     */
    private static function asciiLower(string $value): string
    {
        return strtr($value, [
            'A' => 'a',
            'B' => 'b',
            'C' => 'c',
            'D' => 'd',
            'E' => 'e',
            'F' => 'f',
            'G' => 'g',
            'H' => 'h',
            'I' => 'i',
            'J' => 'j',
            'K' => 'k',
            'L' => 'l',
            'M' => 'm',
            'N' => 'n',
            'O' => 'o',
            'P' => 'p',
            'Q' => 'q',
            'R' => 'r',
            'S' => 's',
            'T' => 't',
            'U' => 'u',
            'V' => 'v',
            'W' => 'w',
            'X' => 'x',
            'Y' => 'y',
            'Z' => 'z',
        ]);
    }
}
