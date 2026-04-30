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
 * Contracts-level config source tracking model.
 *
 * This value object identifies where a config value came from without storing
 * the raw config value or raw env value.
 */
final readonly class ConfigValueSource
{
    private ConfigSourceType $type;

    private string $root;

    private string $path;

    private string $keyPath;

    private ?string $sourceId;

    private int $precedence;

    private bool $redacted;

    public function __construct(
        ConfigSourceType $type,
        string $root,
        string $path,
        string $keyPath,
        ?string $sourceId = null,
        int $precedence = 0,
        bool $redacted = false,
    ) {
        if ($precedence < 0) {
            throw new \InvalidArgumentException('Config value source precedence must be non-negative.');
        }

        $this->type = $type;
        $this->root = self::normalizeRoot($root);
        $this->path = self::normalizeLogicalIdentifier($path, 'path', allowEmpty: true);
        $this->keyPath = self::normalizeLogicalIdentifier($keyPath, 'keyPath', allowEmpty: true);
        $this->sourceId = self::normalizeOptionalLogicalIdentifier($sourceId, 'sourceId');
        $this->precedence = $precedence;
        $this->redacted = $redacted;
    }

    public function type(): ConfigSourceType
    {
        return $this->type;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function keyPath(): string
    {
        return $this->keyPath;
    }

    public function sourceId(): ?string
    {
        return $this->sourceId;
    }

    /**
     * Explicit source-trace precedence rank.
     *
     * This rank belongs to the concrete source trace entry. It is intentionally
     * not derived from ConfigSourceType.
     */
    public function precedence(): int
    {
        return $this->precedence;
    }

    public function isRedacted(): bool
    {
        return $this->redacted;
    }

    /**
     * @return array{
     *     keyPath: string,
     *     path: string,
     *     precedence: int,
     *     redacted: bool,
     *     root: string,
     *     sourceId: string|null,
     *     type: string
     * }
     */
    public function toArray(): array
    {
        return [
            'keyPath' => $this->keyPath,
            'path' => $this->path,
            'precedence' => $this->precedence,
            'redacted' => $this->redacted,
            'root' => $this->root,
            'sourceId' => $this->sourceId,
            'type' => $this->type->value,
        ];
    }

    private static function normalizeRoot(string $root): string
    {
        $root = trim($root);

        if ($root === '') {
            throw new \InvalidArgumentException('Config value source root must be non-empty.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $root) !== 1) {
            throw new \InvalidArgumentException('Invalid config value source root.');
        }

        return $root;
    }

    private static function normalizeOptionalLogicalIdentifier(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::normalizeLogicalIdentifier($value, $field, allowEmpty: false);
    }

    private static function normalizeLogicalIdentifier(string $value, string $field, bool $allowEmpty): string
    {
        $value = trim($value);

        if ($value === '') {
            if ($allowEmpty) {
                return '';
            }

            throw new \InvalidArgumentException('Config value source ' . $field . ' must be non-empty.');
        }

        self::assertSafeLogicalIdentifier($value, $field);

        return $value;
    }

    private static function assertSafeLogicalIdentifier(string $value, string $field): void
    {
        if (str_contains($value, "\0") || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException('Invalid config value source ' . $field . '.');
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $value) === 1) {
            throw new \InvalidArgumentException('Config value source ' . $field . ' must not be an absolute path.');
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '\\')) {
            throw new \InvalidArgumentException('Config value source ' . $field . ' must not be an absolute path.');
        }

        if (str_contains($value, '://')) {
            throw new \InvalidArgumentException(
                'Config value source ' . $field . ' must be a safe logical identifier.'
            );
        }

        $normalized = str_replace('\\', '/', $value);

        if (
            $normalized === '..'
            || str_starts_with($normalized, '../')
            || str_contains($normalized, '/../')
            || str_ends_with($normalized, '/..')
        ) {
            throw new \InvalidArgumentException('Config value source ' . $field . ' must not contain path traversal.');
        }
    }
}
