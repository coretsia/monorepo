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

namespace Coretsia\Contracts\Observability\Errors;

/**
 * Format-neutral safe error handling context.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 *
 * The context MUST NOT contain raw transport objects, request/response
 * objects, raw headers, cookies, bodies, raw SQL, credentials, tokens,
 * session identifiers, profile payloads, private customer data, or absolute
 * local paths.
 */
final readonly class ErrorHandlingContext
{
    private ?string $operation;

    private ?string $correlationId;

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
    private array $metadata;

    /**
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        ?string $operation = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        $this->operation = self::normalizeOptionalString($operation, 'operation');
        $this->correlationId = self::normalizeOptionalString($correlationId, 'correlationId');
        $this->metadata = self::normalizeMetadata($metadata);
    }

    public function operation(): ?string
    {
        return $this->operation;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * Returns deterministic safe context metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array{
     *     correlationId: string|null,
     *     metadata: array<string, mixed>,
     *     operation: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'correlationId' => $this->correlationId,
            'metadata' => $this->metadata,
            'operation' => $this->operation,
        ];
    }

    private static function normalizeOptionalString(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Invalid error handling context ' . $field);
        }

        if (!self::isSafeSingleLineString($value)) {
            throw new \InvalidArgumentException('Invalid error handling context ' . $field);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $metadata
     *
     * @return array<string,mixed>
     */
    private static function normalizeMetadata(array $metadata): array
    {
        if (array_is_list($metadata) && $metadata !== []) {
            throw new \InvalidArgumentException('Error handling context metadata must be a map.');
        }

        /** @var array<string,mixed> $normalized */
        $normalized = self::normalizeJsonLikeMap($metadata, 'metadata');

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
                throw new \InvalidArgumentException('Invalid error handling context metadata key at ' . $path);
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid error handling context metadata key at ' . $path);
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid error handling context metadata key at ' . $path);
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
                throw new \InvalidArgumentException('Invalid error handling context metadata string at ' . $path);
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float error handling context metadata at ' . $path);
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

        throw new \InvalidArgumentException('Invalid error handling context metadata at ' . $path);
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
