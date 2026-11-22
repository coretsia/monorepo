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

namespace Coretsia\Kernel\Config;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\Source\EffectiveConfigSourceSelector;

/**
 * Read-only ConfigRepositoryInterface adapter over an already validated config tree.
 *
 * This adapter does not provide runtime defaults. Missing values are missing.
 * The get() default parameter exists only because ConfigRepositoryInterface owns
 * that method signature; production runtime guards must not pass fallback values.
 *
 * Canonical config source metadata is supplied explicitly from the completed
 * config compilation or validated artifact hydration boundary.
 * The repository does not synthesize provenance or execute config formation.
 *
 * @internal Kernel runtime config snapshot adapter. Runtime adapters should
 * receive ConfigRepositoryInterface and must not depend on this implementation.
 */
final readonly class ArrayConfigRepository implements ConfigRepositoryInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var list<ConfigValueSource>
     */
    private array $sources;

    /**
     * @param array<string, mixed> $config
     * @param list<ConfigValueSource> $sources
     */
    public function __construct(
        array $config,
        array $sources,
    ) {
        if (!self::isMapArray($config)) {
            throw new \InvalidArgumentException('array-config-root-invalid');
        }

        if (!\array_is_list($sources)) {
            throw new \InvalidArgumentException('array-config-sources-invalid');
        }

        foreach ($sources as $source) {
            if (!$source instanceof ConfigValueSource) {
                throw new \InvalidArgumentException('array-config-sources-invalid');
            }
        }

        $this->config = $config;
        $this->sources = \array_map(
            static fn (array $source): ConfigValueSource => $source['source'],
            EffectiveConfigSourceSelector::normalizeSources(
                EffectiveConfigSourceSelector::sourceListToMap($sources),
            ),
        );
    }

    public function has(string $keyPath): bool
    {
        return self::find($this->config, $keyPath)[0];
    }

    public function get(string $keyPath, mixed $default = null): mixed
    {
        [$exists, $value] = self::find($this->config, $keyPath);

        if (!$exists) {
            return $default;
        }

        return $value;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function sourceOf(string $keyPath): ?ConfigValueSource
    {
        if (!$this->has($keyPath)) {
            return null;
        }

        return EffectiveConfigSourceSelector::select(
            path: $keyPath,
            sources: $this->sources,
        );
    }

    public function explain(): array
    {
        return $this->sources;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{0: bool, 1: mixed}
     */
    private static function find(array $config, string $keyPath): array
    {
        $current = $config;

        foreach (self::segments($keyPath) as $segment) {
            if (!\is_array($current) || !self::isMapArray($current)) {
                return [false, null];
            }

            if (!\array_key_exists($segment, $current)) {
                return [false, null];
            }

            $current = $current[$segment];
        }

        return [true, $current];
    }

    /**
     * @return list<non-empty-string>
     */
    private static function segments(string $keyPath): array
    {
        if ($keyPath === '') {
            throw new \InvalidArgumentException('array-config-key-path-empty');
        }

        $segments = \explode('.', $keyPath);

        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new \InvalidArgumentException('array-config-key-path-invalid');
            }
        }

        /** @var list<non-empty-string> $segments */
        return $segments;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function isMapArray(array $value): bool
    {
        return $value === [] || !\array_is_list($value);
    }
}
