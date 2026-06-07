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

use Coretsia\Contracts\Config\ConfigDirective;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveMixedLevelException;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveTypeMismatchException;
use Coretsia\Kernel\Config\Exception\ConfigReservedNamespaceException;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;

/**
 * Per-file config directive processor.
 *
 * This processor owns Phase A directive processing:
 *
 * - validate reserved `@*` namespace through ConfigNamespaceGuard;
 * - validate directive exclusive-level rule through ConfigNamespaceGuard;
 * - validate directive value type rules;
 * - return a deterministic normalized config tree.
 *
 * It does not apply directives to previous/base config values. Directive
 * application is Phase B and belongs to ConfigMerger, where the previous/base
 * value is known.
 *
 * Normalization keeps directive levels represented as canonical one-key maps:
 *
 *     ['@append' => [...]]
 *     ['@merge' => [...]]
 *     ['@replace' => ...]
 *
 * This preserves a simple runtime shape for ConfigMerger while guaranteeing
 * that every directive level has already passed namespace, exclusive-level, and
 * directive-type validation.
 *
 * Empty array values are intentionally accepted and interpreted by directive
 * context:
 *
 * - list directives treat [] as an empty list;
 * - @merge treats [] as an empty map;
 * - @replace accepts [] as an empty container.
 *
 * Directive classification is locale-independent. It uses only
 * ConfigDirective, string prefix checks, and PHP array shape checks.
 *
 * Diagnostics MUST NOT expose raw config values, raw environment values,
 * payloads, secrets, tokens, DSNs, cookies, headers, raw SQL, object dumps, PHP
 * warnings, absolute local paths, stack traces, or previous throwable messages.
 *
 * @internal
 */
final readonly class DirectiveProcessor
{
    public function __construct(
        private ConfigNamespaceGuard $namespaceGuard,
    ) {
    }

    /**
     * Processes a global root map from a per-file config payload.
     *
     * Use this for aggregate files such as `config/roots.php`, where the file
     * returns a global map of config roots.
     *
     * @param array<array-key, mixed> $config
     *
     * @return array<array-key, mixed>
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     * @throws ConfigDirectiveTypeMismatchException
     */
    public function processGlobalConfig(array $config): array
    {
        $this->namespaceGuard->guardGlobalConfig($config);

        return self::normalizeArray(
            value: $config,
            path: '',
        );
    }

    /**
     * Processes a root-specific config subtree from a per-file config payload.
     *
     * Use this for files such as `config/<root>.php`, where the root is known
     * from the file name and the file returns the subtree for that root.
     *
     * @param array<array-key, mixed> $subtree
     *
     * @return array<array-key, mixed>
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     * @throws ConfigDirectiveTypeMismatchException
     */
    public function processRootSubtree(
        string $root,
        array $subtree,
    ): array {
        $this->namespaceGuard->guardRootSubtree($root, $subtree);

        return self::normalizeArray(
            value: $subtree,
            path: $root,
        );
    }

    /**
     * Processes an arbitrary config tree for directive namespace and typing.
     *
     * This method does not enforce forbidden top-level roots because callers may
     * pass a subtree rather than a global root map.
     *
     * @param array<array-key, mixed> $tree
     *
     * @return array<array-key, mixed>
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     * @throws ConfigDirectiveTypeMismatchException
     */
    public function processConfigTree(
        array $tree,
        string $path = '',
    ): array {
        $this->namespaceGuard->guardConfigTree($tree, $path);

        return self::normalizeArray(
            value: $tree,
            path: $path,
        );
    }

    /**
     * Returns the directive used by a normalized directive level.
     *
     * Invalid or unprocessed input should not be passed here. This helper exists
     * for ConfigMerger so merge-time directive application does not need to
     * duplicate directive detection.
     *
     * @param array<array-key, mixed> $level
     */
    public function directiveOf(array $level): ?ConfigDirective
    {
        if (\array_is_list($level)) {
            return null;
        }

        $directive = null;
        $directiveCount = 0;
        $normalKeyCount = 0;

        foreach ($level as $key => $_value) {
            if (!\is_string($key) || !ConfigDirective::isReservedDirectiveKey($key)) {
                $normalKeyCount++;

                continue;
            }

            $resolvedDirective = ConfigDirective::tryFromKey($key);

            if ($resolvedDirective === null) {
                return null;
            }

            $directive = $resolvedDirective;
            $directiveCount++;
        }

        if ($directiveCount !== 1 || $normalKeyCount !== 0) {
            return null;
        }

        return $directive;
    }

    /**
     * Returns the payload for a normalized directive level.
     *
     * @param array<array-key, mixed> $level
     */
    public function directiveValue(array $level): mixed
    {
        $directive = $this->directiveOf($level);

        if ($directive === null) {
            throw new \InvalidArgumentException('config-directive-level-invalid');
        }

        return $level[$directive->key()];
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     *
     * @throws ConfigDirectiveTypeMismatchException
     */
    private static function normalizeArray(
        array $value,
        string $path,
    ): array {
        if (\array_is_list($value)) {
            return self::normalizeList(
                value: $value,
                path: $path,
            );
        }

        return self::normalizeMap(
            value: $value,
            path: $path,
        );
    }

    /**
     * @param list<mixed> $value
     *
     * @return list<mixed>
     *
     * @throws ConfigDirectiveTypeMismatchException
     */
    private static function normalizeList(
        array $value,
        string $path,
    ): array {
        $normalized = [];

        foreach ($value as $index => $item) {
            if (\is_array($item)) {
                $normalized[] = self::normalizeArray(
                    value: $item,
                    path: self::appendPath($path, $index),
                );

                continue;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     *
     * @throws ConfigDirectiveTypeMismatchException
     */
    private static function normalizeMap(
        array $value,
        string $path,
    ): array {
        $directive = null;
        $directiveValue = null;

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                continue;
            }

            $resolvedDirective = ConfigDirective::tryFromKey($key);

            if ($resolvedDirective === null) {
                continue;
            }

            $directive = $resolvedDirective;
            $directiveValue = $item;

            break;
        }

        if ($directive !== null) {
            return [
                $directive->key() => self::normalizeDirectiveValue(
                    directive: $directive,
                    value: $directiveValue,
                    path: $path,
                ),
            ];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $normalized[$key] = self::normalizeArray(
                    value: $item,
                    path: self::appendPath($path, $key),
                );

                continue;
            }

            $normalized[$key] = $item;
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    /**
     * @throws ConfigDirectiveTypeMismatchException
     */
    private static function normalizeDirectiveValue(
        ConfigDirective $directive,
        mixed $value,
        string $path,
    ): mixed {
        return match ($directive) {
            ConfigDirective::Append,
            ConfigDirective::Prepend,
            ConfigDirective::Remove => self::normalizeListDirectiveValue(
                directive: $directive,
                value: $value,
                path: $path,
            ),

            ConfigDirective::Merge => self::normalizeMergeDirectiveValue(
                value: $value,
                path: $path,
            ),

            ConfigDirective::Replace => self::normalizeReplaceDirectiveValue(
                value: $value,
                path: $path,
            ),
        };
    }

    /**
     * @return list<mixed>
     *
     * @throws ConfigDirectiveTypeMismatchException
     */
    private static function normalizeListDirectiveValue(
        ConfigDirective $directive,
        mixed $value,
        string $path,
    ): array {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw ConfigDirectiveTypeMismatchException::listDirectiveValueMustBeList(
                directive: $directive,
                path: $path,
            );
        }

        return self::normalizeList(
            value: $value,
            path: $path,
        );
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws ConfigDirectiveTypeMismatchException
     */
    private static function normalizeMergeDirectiveValue(
        mixed $value,
        string $path,
    ): array {
        if (!\is_array($value)) {
            throw ConfigDirectiveTypeMismatchException::mergeDirectiveValueMustBeMap(
                path: $path,
            );
        }

        if ($value === []) {
            return [];
        }

        if (\array_is_list($value)) {
            throw ConfigDirectiveTypeMismatchException::mergeDirectiveValueMustBeMap(
                path: $path,
            );
        }

        return self::normalizeMap(
            value: $value,
            path: $path,
        );
    }

    private static function normalizeReplaceDirectiveValue(
        mixed $value,
        string $path,
    ): mixed {
        if (!\is_array($value)) {
            return $value;
        }

        return self::normalizeArray(
            value: $value,
            path: $path,
        );
    }

    private static function appendPath(string $path, int|string $key): string
    {
        if (\is_int($key)) {
            if ($path === '') {
                return '[' . $key . ']';
            }

            return $path . '[' . $key . ']';
        }

        if ($path === '') {
            return $key;
        }

        return $path . '.' . $key;
    }
}
