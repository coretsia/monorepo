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
 * Contracts-level module descriptor value object.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 */
final readonly class ModuleDescriptor
{
    public const int SCHEMA_VERSION = 1;

    /**
     * Runtime-relevant module layers.
     *
     * Tooling-only package layers are intentionally not included.
     *
     * @var list<non-empty-string>
     */
    private const array RUNTIME_LAYERS = [
        'core',
        'enterprise',
        'integrations',
        'platform',
        'presets',
    ];

    private ModuleId $id;
    private ?string $composerName;
    private ?string $packageKind;
    private ?string $moduleClass;

    /**
     * @var list<non-empty-string>
     */
    private array $capabilities;

    /**
     * Deterministic descriptor metadata map.
     *
     * Values are normalized recursively and may contain only:
     * null, bool, int, string, list values, or string-keyed maps.
     *
     * Floats, objects, closures, and resources are intentionally rejected.
     *
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * @param list<string> $capabilities
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        ModuleId $id,
        ?string $composerName = null,
        ?string $packageKind = null,
        ?string $moduleClass = null,
        array $capabilities = [],
        array $metadata = [],
    ) {
        self::assertRuntimeLayer($id->layer());

        $this->id = $id;
        $this->composerName = self::normalizeOptionalString($composerName, 'composerName');
        $this->packageKind = self::normalizeOptionalString($packageKind, 'packageKind');
        $this->moduleClass = self::normalizeOptionalString($moduleClass, 'moduleClass');
        $this->capabilities = self::normalizeStringList($capabilities, 'capabilities');
        $this->metadata = self::normalizeMetadata($metadata);
    }

    /**
     * @param list<string> $capabilities
     * @param array<string,mixed> $metadata
     */
    public static function fromLayerAndSlug(
        string $layer,
        string $slug,
        ?string $composerName = null,
        ?string $packageKind = null,
        ?string $moduleClass = null,
        array $capabilities = [],
        array $metadata = [],
    ): self {
        return new self(
            id: ModuleId::fromLayerAndSlug($layer, $slug),
            composerName: $composerName,
            packageKind: $packageKind,
            moduleClass: $moduleClass,
            capabilities: $capabilities,
            metadata: $metadata,
        );
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function id(): ModuleId
    {
        return $this->id;
    }

    /**
     * @return non-empty-string
     */
    public function moduleId(): string
    {
        return $this->id->value();
    }

    /**
     * @return non-empty-string
     */
    public function layer(): string
    {
        return $this->id->layer();
    }

    /**
     * @return non-empty-string
     */
    public function slug(): string
    {
        return $this->id->slug();
    }

    /**
     * @return non-empty-string|null
     */
    public function composerName(): ?string
    {
        return $this->composerName;
    }

    /**
     * @return non-empty-string|null
     */
    public function packageKind(): ?string
    {
        return $this->packageKind;
    }

    /**
     * @return non-empty-string|null
     */
    public function moduleClass(): ?string
    {
        return $this->moduleClass;
    }

    /**
     * @return list<non-empty-string>
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Returns deterministic descriptor metadata.
     *
     * Values are normalized recursively and may contain only:
     * null, bool, int, string, list values, or string-keyed maps.
     *
     * Floats, objects, closures, and resources are intentionally rejected.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array{
     *     schemaVersion: int,
     *     moduleId: non-empty-string,
     *     layer: non-empty-string,
     *     slug: non-empty-string,
     *     composerName: non-empty-string|null,
     *     packageKind: non-empty-string|null,
     *     moduleClass: non-empty-string|null,
     *     capabilities: list<non-empty-string>,
     *     metadata: array<string,mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'capabilities' => $this->capabilities,
            'composerName' => $this->composerName,
            'layer' => $this->layer(),
            'metadata' => $this->metadata,
            'moduleClass' => $this->moduleClass,
            'moduleId' => $this->moduleId(),
            'packageKind' => $this->packageKind,
            'schemaVersion' => self::SCHEMA_VERSION,
            'slug' => $this->slug(),
        ];
    }

    private static function assertRuntimeLayer(string $layer): void
    {
        if (!in_array($layer, self::RUNTIME_LAYERS, true)) {
            throw new \InvalidArgumentException('Invalid runtime module layer.');
        }
    }

    /**
     * @return non-empty-string|null
     */
    private static function normalizeOptionalString(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            throw new \InvalidArgumentException('Invalid module descriptor ' . $field . '.');
        }

        if (preg_match('/^\s|\s$/', $value) === 1) {
            throw new \InvalidArgumentException('Invalid module descriptor ' . $field . '.');
        }

        if (!self::isSafeSingleLineString($value)) {
            throw new \InvalidArgumentException('Invalid module descriptor ' . $field . '.');
        }

        return $value;
    }

    /**
     * @param list<string> $values
     *
     * @return list<non-empty-string>
     */
    private static function normalizeStringList(array $values, string $field): array
    {
        if (!array_is_list($values)) {
            throw new \InvalidArgumentException('Invalid module descriptor ' . $field . '.');
        }

        $out = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException('Invalid module descriptor ' . $field . '.');
            }

            if ($value === '') {
                throw new \InvalidArgumentException('Invalid module descriptor ' . $field . '.');
            }

            if (preg_match('/^\s|\s$/', $value) === 1) {
                throw new \InvalidArgumentException('Invalid module descriptor ' . $field . '.');
            }

            if (!self::isSafeSingleLineString($value)) {
                throw new \InvalidArgumentException('Invalid module descriptor ' . $field . '.');
            }

            $out[$value] = true;
        }

        $normalized = array_keys($out);
        usort($normalized, static fn (string $a, string $b): int => strcmp($a, $b));

        /** @var list<string> $normalized */
        return $normalized;
    }

    /**
     * @param array<string,mixed> $metadata
     *
     * @return array<string,mixed>
     */
    private static function normalizeMetadata(array $metadata): array
    {
        if (array_is_list($metadata) && $metadata !== []) {
            throw new \InvalidArgumentException('Module descriptor metadata must be a map.');
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
                throw new \InvalidArgumentException('Invalid module descriptor metadata key at ' . $path . '.');
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid module descriptor metadata key at ' . $path . '.');
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid module descriptor metadata key at ' . $path . '.');
            }

            $out[$key] = self::normalizeJsonLikeValue($value, $path . '.' . $key);
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
                throw new \InvalidArgumentException('Invalid module descriptor metadata string at ' . $path . '.');
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float module descriptor metadata at ' . $path . '.');
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

        throw new \InvalidArgumentException('Invalid module descriptor metadata at ' . $path . '.');
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
