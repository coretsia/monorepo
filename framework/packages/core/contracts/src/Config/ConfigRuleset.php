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
 * Contracts-level wrapper for declarative config rules data.
 *
 * This shape represents rules data only. It must not contain executable
 * validators, closures, objects, resources, or runtime wiring objects.
 */
final readonly class ConfigRuleset
{
    private string $root;

    /**
     * @var array<string,mixed>
     */
    private array $rules;

    /**
     * @param array<string,mixed> $rules
     */
    public function __construct(string $root, array $rules)
    {
        $this->root = self::normalizeRoot($root);
        $this->rules = self::normalizeRules($rules);
    }

    /**
     * @param array<string,mixed> $rules
     */
    public static function fromArray(string $root, array $rules): self
    {
        return new self($root, $rules);
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * @return array{
     *     root: string,
     *     rules: array<string,mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'root' => $this->root,
            'rules' => $this->rules,
        ];
    }

    private static function normalizeRoot(string $root): string
    {
        $root = trim($root);

        if ($root === '') {
            throw new \InvalidArgumentException('Config ruleset root must be non-empty.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $root) !== 1) {
            throw new \InvalidArgumentException('Invalid config ruleset root.');
        }

        return $root;
    }

    /**
     * @param array<string,mixed> $rules
     *
     * @return array<string,mixed>
     */
    private static function normalizeRules(array $rules): array
    {
        if (array_is_list($rules) && $rules !== []) {
            throw new \InvalidArgumentException('Config ruleset root rules must be a map.');
        }

        /** @var array<string,mixed> $normalized */
        $normalized = self::normalizeJsonLikeMap($rules, 'rules');

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
                throw new \InvalidArgumentException('Invalid config ruleset key at ' . $path . '.');
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid config ruleset key at ' . $path . '.');
            }

            if (str_contains($key, "\0") || str_contains($key, "\r") || str_contains($key, "\n")) {
                throw new \InvalidArgumentException('Invalid config ruleset key at ' . $path . '.');
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
            throw new \InvalidArgumentException('Invalid float config ruleset value at ' . $path . '.');
        }

        if (is_array($value) && is_callable($value)) {
            throw new \InvalidArgumentException('Invalid config ruleset value at ' . $path . '.');
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

        throw new \InvalidArgumentException('Invalid config ruleset value at ' . $path . '.');
    }
}
