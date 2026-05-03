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

namespace Coretsia\Contracts\Observability\Profiling;

/**
 * Format-neutral captured profiling artifact.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 *
 * The payload is opaque. The contracts package MUST NOT inspect, normalize,
 * parse, log, or expose payload contents.
 */
final readonly class ProfileArtifact
{
    public const int SCHEMA_VERSION = 1;
    private string $name;

    /**
     * Safe deterministic metadata map.
     *
     * Values are normalized recursively and may contain only:
     * null, bool, int, string, list values, or string-keyed maps.
     *
     * Floats, objects, closures, resources, and runtime wiring objects are
     * rejected.
     *
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * Opaque implementation-owned payload carrier.
     *
     * The payload is not a public semantic schema.
     */
    private ?string $payload;

    /**
     * @param non-empty-string $name
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        string $name,
        array $metadata = [],
        ?string $payload = null,
    ) {
        $this->name = self::normalizeName($name);
        $this->metadata = self::normalizeMetadata($metadata);
        $this->payload = $payload;
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
     * Returns deterministic safe profile metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Returns the opaque implementation-owned payload carrier.
     *
     * The returned string MUST NOT be logged, used as a metric label, attached
     * to spans, or embedded into error descriptor extensions.
     */
    public function payload(): ?string
    {
        return $this->payload;
    }

    /**
     * Returns the safe public artifact envelope.
     *
     * The opaque payload is intentionally not included in this exported shape.
     *
     * @return array{
     *     metadata: array<string,mixed>,
     *     name: non-empty-string,
     *     payload: null,
     *     schemaVersion: int
     * }
     */
    public function toArray(): array
    {
        return [
            'metadata' => $this->metadata,
            'name' => $this->name,
            'payload' => null,
            'schemaVersion' => self::SCHEMA_VERSION,
        ];
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException('Invalid profile artifact name.');
        }

        if (!self::isSafeSingleLineString($name)) {
            throw new \InvalidArgumentException('Invalid profile artifact name.');
        }

        return $name;
    }

    /**
     * @param array<string,mixed> $metadata
     *
     * @return array<string,mixed>
     */
    private static function normalizeMetadata(array $metadata): array
    {
        if (array_is_list($metadata) && $metadata !== []) {
            throw new \InvalidArgumentException('Profile artifact metadata must be a map.');
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
                throw new \InvalidArgumentException('Invalid profile artifact metadata key at ' . $path);
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid profile artifact metadata key at ' . $path);
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid profile artifact metadata key at ' . $path);
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
                throw new \InvalidArgumentException('Invalid profile artifact metadata string at ' . $path);
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float profile artifact metadata at ' . $path);
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

        throw new \InvalidArgumentException('Invalid profile artifact metadata at ' . $path);
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
