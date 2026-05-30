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

namespace Coretsia\Kernel\Boot;

use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Env\EnvValue;

/**
 * Internal immutable env repository snapshot for Kernel Bootstrap Phase A.
 *
 * This repository stores already-resolved env values and optional safe source
 * metadata. It never reads from process env after construction.
 *
 * Raw env values are returned only through all() / get() for runtime owners.
 * Diagnostics, logs, validation errors, traces, and explain output MUST NOT
 * print these raw values directly.
 *
 * @internal
 */
final readonly class ArrayEnvRepository implements EnvRepositoryInterface
{
    /**
     * @var array<string, string>
     */
    private array $values;

    /**
     * @var array<string, ConfigValueSource>
     */
    private array $sources;

    /**
     * @param array<string, string> $values
     * @param array<string, ConfigValueSource> $sources
     */
    public function __construct(array $values, array $sources = [])
    {
        $this->values = self::normalizeValues($values);
        $this->sources = self::normalizeSources($sources, $this->values);
    }

    public function has(string $name): bool
    {
        self::assertEnvName($name);

        return \array_key_exists($name, $this->values);
    }

    public function get(string $name): EnvValue
    {
        self::assertEnvName($name);

        if (!\array_key_exists($name, $this->values)) {
            return EnvValue::missing();
        }

        return EnvValue::present($this->values[$name]);
    }

    public function all(): array
    {
        return $this->values;
    }

    public function sourceOf(string $name): ?ConfigValueSource
    {
        self::assertEnvName($name);

        return $this->sources[$name] ?? null;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, string>
     */
    private static function normalizeValues(array $values): array
    {
        if (\array_is_list($values) && $values !== []) {
            throw new \InvalidArgumentException('env-repository-values-map-required');
        }

        $normalized = [];

        foreach ($values as $name => $value) {
            if (!\is_string($name) || $name === '') {
                throw new \InvalidArgumentException('env-repository-name-invalid');
            }

            if (!\is_string($value)) {
                throw new \InvalidArgumentException('env-repository-value-invalid');
            }

            $normalized[$name] = $value;
        }

        \ksort($normalized, \SORT_STRING);

        /** @var array<string, string> $normalized */
        return $normalized;
    }

    /**
     * @param array<mixed> $sources
     * @param array<string, string> $values
     *
     * @return array<string, ConfigValueSource>
     */
    private static function normalizeSources(array $sources, array $values): array
    {
        if (\array_is_list($sources) && $sources !== []) {
            throw new \InvalidArgumentException('env-repository-sources-map-required');
        }

        $normalized = [];

        foreach ($sources as $name => $source) {
            if (!\is_string($name) || $name === '') {
                throw new \InvalidArgumentException('env-repository-source-name-invalid');
            }

            if (!\array_key_exists($name, $values)) {
                throw new \InvalidArgumentException('env-repository-source-without-value');
            }

            if (!$source instanceof ConfigValueSource) {
                throw new \InvalidArgumentException('env-repository-source-invalid');
            }

            $normalized[$name] = $source;
        }

        \ksort($normalized, \SORT_STRING);

        /** @var array<string, ConfigValueSource> $normalized */
        return $normalized;
    }

    private static function assertEnvName(string $name): void
    {
        if ($name === '') {
            throw new \InvalidArgumentException('env-repository-name-empty');
        }
    }
}
