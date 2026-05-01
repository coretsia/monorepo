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
 * Format-neutral successful route match descriptor.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts routing shape policy.
 *
 * The descriptor MUST NOT expose raw request objects, response objects,
 * PSR-7 objects, middleware objects, controller objects, service instances,
 * raw headers, raw cookies, raw request/response bodies, raw payloads,
 * credentials, tokens, raw SQL, or absolute local paths.
 */
final readonly class RouteMatch
{
    private string $name;

    private string $pathTemplate;

    private string $handler;

    /**
     * @var array<string,mixed>
     */
    private array $parameters;

    /**
     * @var array<string,mixed>
     */
    private array $metadata;

    /**
     * @param array<string,mixed> $parameters
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        string $name,
        string $pathTemplate,
        string $handler,
        array $parameters = [],
        array $metadata = [],
    ) {
        $this->name = self::normalizeSafeSingleLineField($name, 'name');
        $this->pathTemplate = self::normalizeSafeSingleLineField($pathTemplate, 'pathTemplate');
        $this->handler = self::normalizeSafeSingleLineField($handler, 'handler');
        $this->parameters = self::normalizeRootJsonLikeMap($parameters, 'parameters');
        $this->metadata = self::normalizeRootJsonLikeMap($metadata, 'metadata');
    }

    public function name(): string
    {
        return $this->name;
    }

    public function pathTemplate(): string
    {
        return $this->pathTemplate;
    }

    public function handler(): string
    {
        return $this->handler;
    }

    /**
     * Route parameters MAY originate from user-controlled path segments.
     * They MUST NOT be treated as observability-safe by default.
     *
     * @return array<string,mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
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
     *     handler: string,
     *     metadata: array<string,mixed>,
     *     name: string,
     *     parameters: array<string,mixed>,
     *     pathTemplate: string
     * }
     */
    public function toArray(): array
    {
        return [
            'handler' => $this->handler,
            'metadata' => $this->metadata,
            'name' => $this->name,
            'parameters' => $this->parameters,
            'pathTemplate' => $this->pathTemplate,
        ];
    }

    private static function normalizeSafeSingleLineField(string $value, string $field): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Invalid route match ' . $field . '.');
        }

        if (!self::isSafeSingleLineString($value)) {
            throw new \InvalidArgumentException('Invalid route match ' . $field . '.');
        }

        return $value;
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string,mixed>
     */
    private static function normalizeRootJsonLikeMap(array $map, string $path): array
    {
        if (array_is_list($map) && $map !== []) {
            throw new \InvalidArgumentException('Route match ' . $path . ' must be a map.');
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
                throw new \InvalidArgumentException('Invalid route match map key at ' . $path . '.');
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid route match map key at ' . $path . '.');
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid route match map key at ' . $path . '.');
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
                throw new \InvalidArgumentException('Invalid route match string at ' . $path . '.');
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float route match value at ' . $path . '.');
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

        throw new \InvalidArgumentException('Invalid route match value at ' . $path . '.');
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
