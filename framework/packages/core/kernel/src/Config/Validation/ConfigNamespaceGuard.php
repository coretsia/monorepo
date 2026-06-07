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

namespace Coretsia\Kernel\Config\Validation;

use Coretsia\Contracts\Config\ConfigDirective;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveMixedLevelException;
use Coretsia\Kernel\Config\Exception\ConfigReservedNamespaceException;

/**
 * Kernel-owned reserved namespace guard for config input.
 *
 * This guard enforces global namespace safety before semantic config
 * validation:
 *
 * - forbidden top-level roots such as `coretsia` and `_internal`;
 * - `@*` directive namespace reservation;
 * - unsupported `@*` directive keys;
 * - directive exclusive-level rule.
 *
 * It intentionally does not validate framework-owned config schemas and does
 * not reject user-owned/custom top-level roots solely because they are unknown
 * to the framework. Semantic validation is owned by ConfigValidator and runs
 * only for roots with loaded declarative rulesets.
 *
 * Error precedence is deterministic:
 *
 * 1. unknown `@*` key fails as ConfigReservedNamespaceException;
 * 2. directive mixed-level or multiple-directive violation fails as
 *    ConfigDirectiveMixedLevelException;
 * 3. directive value type checks are left to DirectiveProcessor.
 *
 * Diagnostics MUST NOT expose raw config values, raw environment values,
 * payloads, secrets, tokens, DSNs, cookies, headers, raw SQL, object dumps, PHP
 * warnings, absolute local paths, stack traces, or previous throwable messages.
 *
 * @internal
 */
final readonly class ConfigNamespaceGuard
{
    /**
     * @var list<non-empty-string>
     */
    private array $forbiddenTopLevelRoots;

    /**
     * @var array<non-empty-string, true>
     */
    private array $forbiddenTopLevelRootLookup;

    /**
     * @param list<non-empty-string> $forbiddenTopLevelRoots
     */
    public function __construct(array $forbiddenTopLevelRoots)
    {
        [
            $this->forbiddenTopLevelRoots,
            $this->forbiddenTopLevelRootLookup,
        ] = self::normalizeForbiddenTopLevelRoots($forbiddenTopLevelRoots);
    }

    /**
     * Guards a global config map.
     *
     * This is intended for aggregate/global config payloads such as
     * `config/roots.php`, final merged root maps, or equivalent in-memory global
     * config structures.
     *
     * @param array<array-key, mixed> $config
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     */
    public function guardGlobalConfig(array $config): void
    {
        $this->guardArray(
            value: $config,
            path: '',
            enforceForbiddenTopLevelRoots: true,
        );
    }

    /**
     * Guards a root-specific config subtree.
     *
     * This is intended for files such as `config/<root>.php`, where the root is
     * known from the file name and the payload is the subtree for that root.
     *
     * @param array<array-key, mixed> $subtree
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     */
    public function guardRootSubtree(
        string $root,
        array $subtree,
    ): void {
        $this->guardTopLevelRootName($root);

        $this->guardArray(
            value: $subtree,
            path: $root,
            enforceForbiddenTopLevelRoots: false,
        );
    }

    /**
     * Guards an arbitrary config tree for directive namespace safety only.
     *
     * This method does not apply forbidden top-level root checks because the
     * caller may pass a subtree rather than a global root map.
     *
     * @param array<array-key, mixed> $tree
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     */
    public function guardConfigTree(
        array $tree,
        string $path = '',
    ): void {
        $this->guardArray(
            value: $tree,
            path: $path,
            enforceForbiddenTopLevelRoots: false,
        );
    }

    /**
     * @return list<non-empty-string>
     */
    public function forbiddenTopLevelRoots(): array
    {
        return $this->forbiddenTopLevelRoots;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     */
    private function guardArray(
        array $value,
        string $path,
        bool $enforceForbiddenTopLevelRoots,
    ): void {
        if (\array_is_list($value)) {
            $this->guardList(
                value: $value,
                path: $path,
            );

            return;
        }

        $this->guardMap(
            value: $value,
            path: $path,
            enforceForbiddenTopLevelRoots: $enforceForbiddenTopLevelRoots,
        );
    }

    /**
     * @param list<mixed> $value
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     */
    private function guardList(
        array $value,
        string $path,
    ): void {
        foreach ($value as $index => $item) {
            if (!\is_array($item)) {
                continue;
            }

            $this->guardArray(
                value: $item,
                path: self::appendPath($path, $index),
                enforceForbiddenTopLevelRoots: false,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @throws ConfigReservedNamespaceException
     * @throws ConfigDirectiveMixedLevelException
     */
    private function guardMap(
        array $value,
        string $path,
        bool $enforceForbiddenTopLevelRoots,
    ): void {
        $directiveCount = 0;
        $normalKeyCount = 0;
        $directiveValue = null;

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                $normalKeyCount++;

                continue;
            }

            if (!ConfigDirective::isReservedDirectiveKey($key)) {
                $normalKeyCount++;

                continue;
            }

            if (!ConfigDirective::isAllowedKey($key)) {
                throw ConfigReservedNamespaceException::forReservedDirectiveKey($path);
            }

            $directiveCount++;
            $directiveValue = $item;
        }

        if ($directiveCount > 1) {
            throw ConfigDirectiveMixedLevelException::multipleDirectives($path);
        }

        if ($directiveCount === 1 && $normalKeyCount > 0) {
            throw ConfigDirectiveMixedLevelException::mixedWithConfigKeys($path);
        }

        if ($directiveCount === 1) {
            if (\is_array($directiveValue)) {
                $this->guardArray(
                    value: $directiveValue,
                    path: $path,
                    enforceForbiddenTopLevelRoots: $enforceForbiddenTopLevelRoots,
                );
            }

            return;
        }

        foreach ($value as $key => $item) {
            if (
                $enforceForbiddenTopLevelRoots
                && $path === ''
                && \is_string($key)
                && isset($this->forbiddenTopLevelRootLookup[$key])
            ) {
                throw ConfigReservedNamespaceException::forReservedTopLevelRoot($key);
            }

            if (!\is_array($item)) {
                continue;
            }

            $this->guardArray(
                value: $item,
                path: self::appendPath($path, $key),
                enforceForbiddenTopLevelRoots: false,
            );
        }
    }

    /**
     * @throws ConfigReservedNamespaceException
     */
    private function guardTopLevelRootName(string $root): void
    {
        if ($root === '') {
            throw new \InvalidArgumentException('config-namespace-root-empty');
        }

        if (ConfigDirective::isReservedDirectiveKey($root)) {
            throw ConfigReservedNamespaceException::forReservedDirectiveKey($root);
        }

        if (isset($this->forbiddenTopLevelRootLookup[$root])) {
            throw ConfigReservedNamespaceException::forReservedTopLevelRoot($root);
        }
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

    /**
     * @param list<non-empty-string> $roots
     *
     * @return array{
     *     0: list<non-empty-string>,
     *     1: array<non-empty-string, true>
     * }
     */
    private static function normalizeForbiddenTopLevelRoots(array $roots): array
    {
        if ($roots === []) {
            throw new \InvalidArgumentException('config-namespace-forbidden-roots-empty');
        }

        if (!\array_is_list($roots)) {
            throw new \InvalidArgumentException('config-namespace-forbidden-roots-list-required');
        }

        $normalized = [];

        foreach ($roots as $root) {
            if (!\is_string($root) || $root === '') {
                throw new \InvalidArgumentException('config-namespace-forbidden-root-invalid');
            }

            if (ConfigDirective::isReservedDirectiveKey($root)) {
                throw new \InvalidArgumentException('config-namespace-forbidden-root-reserved-directive');
            }

            if (\trim($root) !== $root || \preg_match('/\s/u', $root) === 1) {
                throw new \InvalidArgumentException('config-namespace-forbidden-root-whitespace-forbidden');
            }

            if (\preg_match('/[\x00-\x1F\x7F]/', $root) === 1) {
                throw new \InvalidArgumentException('config-namespace-forbidden-root-control-character-forbidden');
            }

            $normalized[] = $root;
        }

        $normalized = \array_values(\array_unique($normalized));

        \usort(
            $normalized,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        $lookup = [];

        foreach ($normalized as $root) {
            $lookup[$root] = true;
        }

        return [
            $normalized,
            $lookup,
        ];
    }
}
