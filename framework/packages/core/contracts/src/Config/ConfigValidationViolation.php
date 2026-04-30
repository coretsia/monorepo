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
 * Immutable safe config validation violation shape.
 *
 * Violations expose structural diagnostics only. They must not contain raw
 * config values, raw env values, secrets, or absolute local paths.
 */
final readonly class ConfigValidationViolation
{
    /**
     * @var list<string>
     */
    private const array ACTUAL_TYPES = [
        'null',
        'bool',
        'int',
        'string',
        'list',
        'map',
        'float',
        'object',
        'resource',
        'callable',
        'unknown',
    ];

    private string $root;

    private string $path;

    private string $reason;

    private ?string $expected;

    private ?string $actualType;

    public function __construct(
        string $root,
        string $path,
        string $reason,
        ?string $expected = null,
        ?string $actualType = null,
    ) {
        $this->root = self::normalizeRoot($root);
        $this->path = self::normalizeSafeText($path, 'path', allowEmpty: true);
        $this->reason = self::normalizeReason($reason);
        $this->expected = self::normalizeOptionalSafeText($expected, 'expected');
        $this->actualType = self::normalizeOptionalActualType($actualType);
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function expected(): ?string
    {
        return $this->expected;
    }

    public function actualType(): ?string
    {
        return $this->actualType;
    }

    /**
     * @return array{
     *     actualType?: string,
     *     expected?: string,
     *     path: string,
     *     reason: string,
     *     root: string
     * }
     */
    public function toArray(): array
    {
        $out = [];

        if ($this->actualType !== null) {
            $out['actualType'] = $this->actualType;
        }

        if ($this->expected !== null) {
            $out['expected'] = $this->expected;
        }

        $out['path'] = $this->path;
        $out['reason'] = $this->reason;
        $out['root'] = $this->root;

        return $out;
    }

    private static function normalizeRoot(string $root): string
    {
        $root = trim($root);

        if ($root === '') {
            throw new \InvalidArgumentException('Config validation violation root must be non-empty.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $root) !== 1) {
            throw new \InvalidArgumentException('Invalid config validation violation root.');
        }

        return $root;
    }

    private static function normalizeReason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Config validation violation reason must be non-empty.');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.-]*$/', $reason) !== 1) {
            throw new \InvalidArgumentException('Invalid config validation violation reason.');
        }

        return $reason;
    }

    private static function normalizeOptionalSafeText(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::normalizeSafeText($value, $field, allowEmpty: false);
    }

    private static function normalizeOptionalActualType(?string $actualType): ?string
    {
        if ($actualType === null) {
            return null;
        }

        $actualType = self::normalizeSafeText($actualType, 'actualType', allowEmpty: false);

        if (!in_array($actualType, self::ACTUAL_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid config validation violation actualType.');
        }

        return $actualType;
    }

    private static function normalizeSafeText(string $value, string $field, bool $allowEmpty): string
    {
        $value = trim($value);

        if ($value === '') {
            if ($allowEmpty) {
                return '';
            }

            throw new \InvalidArgumentException('Config validation violation ' . $field . ' must be non-empty.');
        }

        if (str_contains($value, "\0") || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException('Invalid config validation violation ' . $field . '.');
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $value) === 1) {
            throw new \InvalidArgumentException(
                'Config validation violation ' . $field . ' must not contain an absolute path.'
            );
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '\\')) {
            throw new \InvalidArgumentException(
                'Config validation violation ' . $field . ' must not contain an absolute path.'
            );
        }

        if (str_contains($value, '://')) {
            throw new \InvalidArgumentException('Config validation violation ' . $field . ' must be safe text.');
        }

        return $value;
    }
}
