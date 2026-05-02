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

namespace Coretsia\Contracts\Validation;

/**
 * Immutable safe validation violation descriptor.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 *
 * A violation exposes structural validation diagnostics only. It MUST NOT
 * contain raw input values, raw request data, raw queue messages, raw SQL,
 * credentials, tokens, private customer data, or absolute local paths.
 */
final readonly class Violation
{
    public const int SCHEMA_VERSION = 1;

    private string $path;

    private string $code;

    private ?string $rule;

    private ?int $index;

    private ?string $message;

    /**
     * Safe deterministic metadata map.
     *
     * Values are normalized recursively and may contain only:
     * null, bool, int, string, list values, or string-keyed maps.
     *
     * Floats, objects, closures, resources, and throwables are rejected.
     *
     * @var array<string, mixed>
     */
    private array $meta;

    /**
     * @param array<string,mixed> $meta
     */
    public function __construct(
        string $path,
        string $code,
        ?string $rule = null,
        ?int $index = null,
        ?string $message = null,
        array $meta = [],
    ) {
        $this->path = self::normalizePath($path);
        $this->code = self::normalizeCode($code);
        $this->rule = self::normalizeOptionalSingleLineString($rule, 'rule');
        $this->index = self::normalizeIndex($index);
        $this->message = self::normalizeOptionalSingleLineString($message, 'message');
        $this->meta = self::normalizeMeta($meta);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function rule(): ?string
    {
        return $this->rule;
    }

    public function index(): ?int
    {
        return $this->index;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * Returns deterministic safe violation metadata.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * @return array{
     *     code: string,
     *     index?: int,
     *     message?: string,
     *     meta: array<string, mixed>,
     *     path: string,
     *     rule?: string,
     *     schemaVersion: int
     * }
     */
    public function toArray(): array
    {
        $out = [
            'code' => $this->code,
        ];

        if ($this->index !== null) {
            $out['index'] = $this->index;
        }

        if ($this->message !== null) {
            $out['message'] = $this->message;
        }

        $out['meta'] = $this->meta;
        $out['path'] = $this->path;

        if ($this->rule !== null) {
            $out['rule'] = $this->rule;
        }

        $out['schemaVersion'] = self::SCHEMA_VERSION;

        return $out;
    }

    private static function normalizePath(string $path): string
    {
        if ($path !== trim($path)) {
            throw new \InvalidArgumentException('Invalid validation violation path.');
        }

        if (!self::isSafeSingleLineString($path)) {
            throw new \InvalidArgumentException('Invalid validation violation path.');
        }

        if (self::looksLikeAbsolutePath($path)) {
            throw new \InvalidArgumentException('Invalid validation violation path.');
        }

        return $path;
    }

    private static function looksLikeAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private static function normalizeCode(string $code): string
    {
        $code = trim($code);

        if ($code === '') {
            throw new \InvalidArgumentException('Invalid validation violation code.');
        }

        if (!self::isSafeSingleLineString($code)) {
            throw new \InvalidArgumentException('Invalid validation violation code.');
        }

        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $code) !== 1) {
            throw new \InvalidArgumentException('Invalid validation violation code.');
        }

        return $code;
    }

    private static function normalizeOptionalSingleLineString(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Invalid validation violation ' . $field . '.');
        }

        if (!self::isSafeSingleLineString($value)) {
            throw new \InvalidArgumentException('Invalid validation violation ' . $field . '.');
        }

        return $value;
    }

    private static function normalizeIndex(?int $index): ?int
    {
        if ($index === null) {
            return null;
        }

        if ($index < 0) {
            throw new \InvalidArgumentException('Invalid validation violation index.');
        }

        return $index;
    }

    /**
     * @param array<string,mixed> $meta
     *
     * @return array<string,mixed>
     */
    private static function normalizeMeta(array $meta): array
    {
        if (array_is_list($meta) && $meta !== []) {
            throw new \InvalidArgumentException('Validation violation meta must be a map.');
        }

        /** @var array<string,mixed> $normalized */
        $normalized = self::normalizeJsonLikeMap($meta, 'meta');

        return $normalized;
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string,mixed>
     */
    private static function normalizeJsonLikeMap(array $map, string $path): array
    {
        $out = [];

        foreach ($map as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Invalid validation violation meta key at ' . $path);
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid validation violation meta key at ' . $path);
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid validation violation meta key at ' . $path);
            }

            $out[$key] = self::normalizeJsonLikeValue($value, $path . \chr(46) . $key);
        }

        ksort($out, \SORT_STRING);

        /** @var array<string,mixed> $out */
        return $out;
    }

    private static function normalizeJsonLikeValue(mixed $value, string $path): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (!self::isSafeString($value)) {
                throw new \InvalidArgumentException('Invalid validation violation meta string at ' . $path);
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float validation violation meta at ' . $path);
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $out = [];

                foreach ($value as $item) {
                    $out[] = self::normalizeJsonLikeValue($item, $path . '[]');
                }

                return $out;
            }

            return self::normalizeJsonLikeMap($value, $path);
        }

        throw new \InvalidArgumentException('Invalid validation violation meta at ' . $path);
    }

    private static function isSafeSingleLineString(string $value): bool
    {
        return self::isSafeString($value)
            && !str_contains($value, "\r")
            && !str_contains($value, "\n");
    }

    private static function isSafeString(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
