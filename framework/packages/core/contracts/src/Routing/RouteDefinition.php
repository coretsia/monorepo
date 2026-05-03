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

namespace Coretsia\Contracts\Routing;

/**
 * Format-neutral declared route descriptor.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts routing shape policy.
 *
 * The descriptor MUST NOT expose raw request objects, response objects,
 * PSR-7 objects, middleware objects, controller objects, service instances,
 * raw headers, raw cookies, raw request/response bodies, raw payloads,
 * credentials, tokens, raw SQL, or absolute local paths.
 */
final readonly class RouteDefinition
{
    public const int SCHEMA_VERSION = 1;

    private string $name;

    /**
     * @var list<non-empty-string>
     */
    private array $methods;
    private string $pathTemplate;
    private string $handler;

    /**
     * @var array<non-empty-string,non-empty-string>
     */
    private array $requirements;

    /**
     * @var array<string,mixed>
     */
    private array $defaults;

    /**
     * @var array<string,mixed>
     */
    private array $metadata;

    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $methods
     * @param non-empty-string $pathTemplate
     * @param non-empty-string $handler
     * @param array<non-empty-string,non-empty-string> $requirements
     * @param array<string,mixed> $defaults
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        string $name,
        array $methods,
        string $pathTemplate,
        string $handler,
        array $requirements = [],
        array $defaults = [],
        array $metadata = [],
    ) {
        $this->name = self::normalizeSafeSingleLineField($name, 'name');
        $this->methods = self::normalizeMethods($methods);
        $this->pathTemplate = self::normalizePathTemplate($pathTemplate);
        $this->handler = self::normalizeSafeSingleLineField($handler, 'handler');
        $this->requirements = self::normalizeRequirements($requirements);
        $this->defaults = self::normalizeRootJsonLikeMap($defaults, 'defaults');
        $this->metadata = self::normalizeRootJsonLikeMap($metadata, 'metadata');
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * @return non-empty-string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<non-empty-string>
     */
    public function methods(): array
    {
        return $this->methods;
    }

    /**
     * @return non-empty-string
     */
    public function pathTemplate(): string
    {
        return $this->pathTemplate;
    }

    /**
     * @return non-empty-string
     */
    public function handler(): string
    {
        return $this->handler;
    }

    /**
     * @return array<non-empty-string,non-empty-string>
     */
    public function requirements(): array
    {
        return $this->requirements;
    }

    /**
     * @return array<string,mixed>
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * @return array<string,mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array{
     *     defaults: array<string,mixed>,
     *     handler: non-empty-string,
     *     metadata: array<string,mixed>,
     *     methods: list<non-empty-string>,
     *     name: non-empty-string,
     *     pathTemplate: non-empty-string,
     *     requirements: array<non-empty-string,non-empty-string>,
     *     schemaVersion: int
     * }
     */
    public function toArray(): array
    {
        return [
            'defaults' => $this->defaults,
            'handler' => $this->handler,
            'metadata' => $this->metadata,
            'methods' => $this->methods,
            'name' => $this->name,
            'pathTemplate' => $this->pathTemplate,
            'requirements' => $this->requirements,
            'schemaVersion' => self::SCHEMA_VERSION,
        ];
    }

    /**
     * @return non-empty-string
     */
    private static function normalizeSafeSingleLineField(string $value, string $field): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Invalid route definition ' . $field . '.');
        }

        if (!self::isSafeSingleLineString($value)) {
            throw new \InvalidArgumentException('Invalid route definition ' . $field . '.');
        }

        if (preg_match('/\s/', $value) === 1) {
            throw new \InvalidArgumentException('Invalid route definition ' . $field . '.');
        }

        return $value;
    }

    /**
     * @return non-empty-string
     */
    private static function normalizePathTemplate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Invalid route definition pathTemplate.');
        }

        if (!str_starts_with($value, '/')) {
            throw new \InvalidArgumentException('Invalid route definition pathTemplate.');
        }

        if (!self::isSafeSingleLineString($value)) {
            throw new \InvalidArgumentException('Invalid route definition pathTemplate.');
        }

        if (preg_match('/\s/', $value) === 1) {
            throw new \InvalidArgumentException('Invalid route definition pathTemplate.');
        }

        return $value;
    }

    /**
     * @param array<mixed> $methods
     *
     * @return list<non-empty-string>
     */
    private static function normalizeMethods(array $methods): array
    {
        if (!array_is_list($methods)) {
            throw new \InvalidArgumentException('Route definition methods must be a list.');
        }

        if ($methods === []) {
            throw new \InvalidArgumentException('Route definition methods must not be empty.');
        }

        $out = [];

        foreach ($methods as $method) {
            if (!is_string($method)) {
                throw new \InvalidArgumentException('Invalid route definition method.');
            }

            $method = strtoupper(trim($method));

            if ($method === '') {
                throw new \InvalidArgumentException('Invalid route definition method.');
            }

            if (!self::isSafeSingleLineString($method)) {
                throw new \InvalidArgumentException('Invalid route definition method.');
            }

            if (preg_match('/^[A-Z][A-Z0-9_-]*$/', $method) !== 1) {
                throw new \InvalidArgumentException('Invalid route definition method.');
            }

            $out[$method] = true;
        }

        $methods = array_keys($out);

        sort($methods, \SORT_STRING);

        return $methods;
    }

    /**
     * @param array<mixed> $requirements
     *
     * @return array<non-empty-string,non-empty-string>
     */
    private static function normalizeRequirements(array $requirements): array
    {
        if (array_is_list($requirements) && $requirements !== []) {
            throw new \InvalidArgumentException('Route definition requirements must be a map.');
        }

        $out = [];

        foreach ($requirements as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Invalid route definition requirement key.');
            }

            $key = trim($key);

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid route definition requirement key.');
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid route definition requirement key.');
            }

            if (!is_string($value)) {
                throw new \InvalidArgumentException('Invalid route definition requirement value at ' . $key . '.');
            }

            $value = trim($value);

            if ($value === '') {
                throw new \InvalidArgumentException('Invalid route definition requirement value at ' . $key . '.');
            }

            if (!self::isSafeSingleLineString($value)) {
                throw new \InvalidArgumentException('Invalid route definition requirement value at ' . $key . '.');
            }

            $out[$key] = $value;
        }

        ksort($out, \SORT_STRING);

        return $out;
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string,mixed>
     */
    private static function normalizeRootJsonLikeMap(array $map, string $path): array
    {
        if (array_is_list($map) && $map !== []) {
            throw new \InvalidArgumentException('Route definition ' . $path . ' must be a map.');
        }

        return self::normalizeJsonLikeMap($map, $path);
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
                throw new \InvalidArgumentException('Invalid route definition map key at ' . $path . '.');
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid route definition map key at ' . $path . '.');
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid route definition map key at ' . $path . '.');
            }

            $out[$key] = self::normalizeJsonLikeValue($value, $path . \chr(46) . $key);
        }

        ksort($out, \SORT_STRING);

        return $out;
    }

    private static function normalizeJsonLikeValue(mixed $value, string $path): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (!self::isSafeString($value)) {
                throw new \InvalidArgumentException('Invalid route definition string at ' . $path . '.');
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float route definition value at ' . $path . '.');
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

        throw new \InvalidArgumentException('Invalid route definition value at ' . $path . '.');
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
