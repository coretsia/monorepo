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
    public const int SCHEMA_VERSION = 1;

    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_META_KEYS = [
        'value',
        'rawValue',
        'configValue',
        'envValue',
        'rawEnvValue',
        'secret',
        'password',
        'token',
        'credential',
        'credentials',
        'privateKey',
        'authorizationHeader',
        'cookie',
        'requestBody',
        'responseBody',
    ];

    private ConfigSourceType $type;
    private string $root;
    private string $sourceId;
    private ?string $path;
    private ?string $keyPath;
    private ?string $directive;
    private int $precedence;
    private bool $redacted;

    /**
     * @var array<string,mixed>
     */
    private array $meta;

    /**
     * @param non-empty-string $root
     * @param non-empty-string $sourceId
     * @param non-empty-string|null $path Repo-relative path or logical source path. Absolute paths are forbidden.
     * @param non-empty-string|null $keyPath Logical dotted config key path.
     * @param non-empty-string|null $directive Directive name without "@" prefix.
     * @param array<string,mixed> $meta Metadata-only, json-like, no raw values.
     */
    public function __construct(
        ConfigSourceType $type,
        string $root,
        string $sourceId,
        ?string $path = null,
        ?string $keyPath = null,
        ?string $directive = null,
        int $precedence = 0,
        bool $redacted = false,
        array $meta = [],
    ) {
        if ($precedence < 0) {
            throw new \InvalidArgumentException('Config value source precedence must be non-negative.');
        }

        $this->type = $type;
        $this->root = self::normalizeRoot($root);
        $this->sourceId = self::normalizeRequiredLogicalIdentifier($sourceId, 'sourceId');
        $this->path = self::normalizeOptionalRepoRelativePath($path);
        $this->keyPath = self::normalizeOptionalLogicalIdentifier($keyPath, 'keyPath');
        $this->directive = self::normalizeOptionalDirective($directive);
        $this->precedence = $precedence;
        $this->redacted = $redacted;
        $this->meta = self::normalizeJsonLikeMap($meta, 'meta');
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function type(): ConfigSourceType
    {
        return $this->type;
    }

    /**
     * @return non-empty-string
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return non-empty-string
     */
    public function sourceId(): string
    {
        return $this->sourceId;
    }

    /**
     * @return non-empty-string|null
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * @return non-empty-string|null
     */
    public function keyPath(): ?string
    {
        return $this->keyPath;
    }

    /**
     * @return non-empty-string|null
     */
    public function directive(): ?string
    {
        return $this->directive;
    }

    /**
     * Explicit source-trace precedence rank.
     *
     * This rank belongs to the concrete source trace entry. It is intentionally
     * not derived from ConfigSourceType.
     *
     * @return int<0,max>
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
     * @return array<string,mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * @return array{
     *     directive: non-empty-string|null,
     *     keyPath: non-empty-string|null,
     *     meta: array<string,mixed>,
     *     path: non-empty-string|null,
     *     precedence: int<0,max>,
     *     redacted: bool,
     *     root: non-empty-string,
     *     schemaVersion: int,
     *     sourceId: non-empty-string,
     *     type: non-empty-string
     * }
     */
    public function toArray(): array
    {
        return [
            'directive' => $this->directive,
            'keyPath' => $this->keyPath,
            'meta' => $this->meta,
            'path' => $this->path,
            'precedence' => $this->precedence,
            'redacted' => $this->redacted,
            'root' => $this->root,
            'schemaVersion' => self::SCHEMA_VERSION,
            'sourceId' => $this->sourceId,
            'type' => $this->type->value,
        ];
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeRoot(string $root): string
    {
        if ($root === '') {
            throw new \InvalidArgumentException('Config value source root must be non-empty.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $root) !== 1) {
            throw new \InvalidArgumentException('Invalid config value source root.');
        }

        return $root;
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeRequiredLogicalIdentifier(string $value, string $field): string
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Config value source ' . $field . ' must be non-empty.');
        }

        self::assertSafeLogicalIdentifier($value, $field);

        return $value;
    }

    /**
     * @return non-empty-string|null
     */
    private static function normalizeOptionalLogicalIdentifier(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return null;
        }

        self::assertSafeLogicalIdentifier($value, $field);

        return $value;
    }

    /**
     * @return non-empty-string|null
     */
    private static function normalizeOptionalRepoRelativePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('/^\s|\s$/', $path) === 1) {
            throw new \InvalidArgumentException('Invalid config value source path.');
        }

        if (str_contains($path, "\0") || str_contains($path, "\r") || str_contains($path, "\n")) {
            throw new \InvalidArgumentException('Invalid config value source path.');
        }

        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new \InvalidArgumentException('Config value source path must not be an absolute path.');
        }

        if (str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Config value source path must not be an absolute path.');
        }

        if (str_contains($path, ':')) {
            throw new \InvalidArgumentException('Config value source path must be a safe repo-relative path.');
        }

        if (str_contains($path, '://')) {
            throw new \InvalidArgumentException('Config value source path must be a safe repo-relative path.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                throw new \InvalidArgumentException('Config value source path must not contain path traversal.');
            }
        }

        return $path;
    }

    /**
     * @return non-empty-string|null
     */
    private static function normalizeOptionalDirective(?string $directive): ?string
    {
        if ($directive === null) {
            return null;
        }

        if ($directive === '') {
            return null;
        }

        if (preg_match('/^\s|\s$/', $directive) === 1) {
            throw new \InvalidArgumentException('Invalid config value source directive.');
        }

        if (str_starts_with($directive, '@')) {
            $directive = substr($directive, 1);
        }

        if ($directive === '') {
            throw new \InvalidArgumentException('Invalid config value source directive.');
        }

        if (preg_match('/^\s|\s$/', $directive) === 1) {
            throw new \InvalidArgumentException('Invalid config value source directive.');
        }

        if (!ConfigDirective::isAllowed($directive)) {
            throw new \InvalidArgumentException('Invalid config value source directive.');
        }

        return $directive;
    }

    private static function assertSafeLogicalIdentifier(string $value, string $field): void
    {
        if (preg_match('/\s/', $value) === 1) {
            throw new \InvalidArgumentException('Config value source ' . $field . ' must not contain whitespace.');
        }

        if (str_contains($value, "\0") || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException('Invalid config value source ' . $field . '.');
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $value) === 1) {
            throw new \InvalidArgumentException('Config value source ' . $field . ' must not be an absolute path.');
        }

        if (str_starts_with($value, '/') || str_starts_with($value, '\\')) {
            throw new \InvalidArgumentException('Config value source ' . $field . ' must not be an absolute path.');
        }

        if (str_contains($value, ':')) {
            throw new \InvalidArgumentException(
                'Config value source ' . $field . ' must be a safe logical identifier.'
            );
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

    /**
     * @param array<mixed> $map
     *
     * @return array<string,mixed>
     */
    private static function normalizeJsonLikeMap(array $map, string $path): array
    {
        if (array_is_list($map) && $map !== []) {
            throw new \InvalidArgumentException('Config value source ' . $path . ' must be a map.');
        }

        $out = [];

        foreach ($map as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Invalid config value source metadata key at ' . $path . '.');
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid config value source metadata key at ' . $path . '.');
            }

            if (str_contains($key, "\0") || str_contains($key, "\r") || str_contains($key, "\n")) {
                throw new \InvalidArgumentException('Invalid config value source metadata key at ' . $path . '.');
            }

            if (in_array($key, self::FORBIDDEN_META_KEYS, true)) {
                throw new \InvalidArgumentException('Invalid config value source metadata key at ' . $path . '.');
            }

            $out[$key] = self::normalizeJsonLikeValue($value, $path . '.' . $key);
        }

        ksort($out, \SORT_STRING);

        /** @var array<string,mixed> $out */
        return $out;
    }

    private static function normalizeJsonLikeValue(mixed $value, string $path): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float config value source metadata at ' . $path . '.');
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

        throw new \InvalidArgumentException('Invalid config value source metadata at ' . $path . '.');
    }
}
