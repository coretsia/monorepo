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

namespace Coretsia\Kernel\Config\Loaders;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Module\ModulePlan;

/**
 * Deterministic package defaults config loader.
 *
 * This loader owns loading package-owned `config/<root>.php` default config
 * files from enabled ModulePlan modules.
 *
 * It intentionally does not discover package config locations by scanning
 * package directories. ModulePlanEntry intentionally does not expose package
 * filesystem paths, defaultsConfigPath, install paths, Composer raw payloads,
 * or provider metadata. Therefore this loader receives explicit source
 * candidates from a trusted Kernel config-location builder.
 *
 * Package defaults rules:
 *
 * - package defaults load only from enabled ModulePlan modules;
 * - package defaults use only `config/<root>.php`;
 * - package defaults MUST NOT use `config/roots.php`;
 * - package defaults files MUST return the subtree for `<root>`;
 * - package defaults are directive-processed per file before merge;
 * - package/root ownership metadata is preserved for ConfigExplainer and
 *   validation diagnostics;
 * - source loading order is deterministic;
 * - absolute filesystem paths are used only for require and are never returned
 *   in diagnostics or metadata.
 *
 * Diagnostics MUST NOT expose raw config values, raw filesystem absolute paths,
 * PHP warnings, stack traces, Composer raw payloads, secrets, tokens, DSNs,
 * cookies, headers, raw SQL, or previous throwable messages.
 *
 * @internal
 */
final readonly class PackageDefaultsConfigLoader
{
    private const string CONFIG_DIRECTORY = 'config';
    private const string AGGREGATE_ROOT_MAP_FILE = 'roots.php';
    private const string PHP_EXTENSION = '.php';

    private const string OWNER_MODULE_ID = 'moduleId';
    private const string OWNER_PACKAGE_ID = 'packageId';
    private const string OWNER_PATH = 'path';
    private const string OWNER_ROOT = 'root';
    private const string OWNER_SOURCE_ID = 'sourceId';

    public function __construct(
        private DirectiveProcessor $directiveProcessor,
    ) {
    }

    /**
     * Loads package defaults from explicit source candidates.
     *
     * Required source shape:
     *
     * ```php
     * [
     *     'root' => 'kernel',
     *     'packageId' => 'coretsia/core-kernel',
     *     'moduleId' => 'core.kernel',
     *     'path' => 'framework/packages/core/kernel/config/kernel.php',
     *     'filesystemPath' => '/absolute/runtime/path/to/config/kernel.php',
     * ]
     * ```
     *
     * `path` is safe repo-relative/logical metadata.
     * `filesystemPath` is used only for `require`; it is never returned.
     *
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $sources
     *
     * @return array{
     *     config: array<string, mixed>,
     *     sources: array<string, ConfigValueSource>,
     *     owners: array<string, array{
     *         moduleId: string,
     *         packageId: string,
     *         path: string,
     *         root: string,
     *         sourceId: string
     *     }>
     * }
     *
     * @throws ConfigInvalidException
     */
    public function load(
        ModulePlan $modulePlan,
        array $sources,
    ): array {
        if ($sources === []) {
            return [
                'config' => [],
                'owners' => [],
                'sources' => [],
            ];
        }

        if (!\array_is_list($sources)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $enabledModules = self::enabledModuleLookup($modulePlan);
        $moduleRanks = self::moduleRankLookup($modulePlan);

        $normalizedSources = self::normalizeSources(
            sources: $sources,
            enabledModules: $enabledModules,
            moduleRanks: $moduleRanks,
        );

        $config = [];
        $sourceMetadata = [];
        $ownerMetadata = [];

        foreach ($normalizedSources as $source) {
            $subtree = self::requireConfigSubtree($source['filesystemPath']);

            self::assertPlainConfigArray($subtree);
            self::assertRootSubtreeShape($subtree, $source['root']);

            $normalizedSubtree = $this->directiveProcessor->processRootSubtree(
                root: $source['root'],
                subtree: $subtree,
            );

            if (isset($config[$source['root']])) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            $config[$source['root']] = $normalizedSubtree;

            $sourceMetadata[$source['root']] = new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: $source['root'],
                sourceId: $source['sourceId'],
                path: $source['path'],
                keyPath: null,
                directive: null,
                precedence: $source['precedence'],
                redacted: false,
                meta: [
                    'kind' => 'package_default',
                    'moduleId' => $source['moduleId'],
                    'packageId' => $source['packageId'],
                ],
            );

            $ownerMetadata[$source['root']] = [
                self::OWNER_MODULE_ID => $source['moduleId'],
                self::OWNER_PACKAGE_ID => $source['packageId'],
                self::OWNER_PATH => $source['path'],
                self::OWNER_ROOT => $source['root'],
                self::OWNER_SOURCE_ID => $source['sourceId'],
            ];
        }

        \ksort($config, \SORT_STRING);
        \ksort($sourceMetadata, \SORT_STRING);
        \ksort($ownerMetadata, \SORT_STRING);

        return [
            'config' => $config,
            'owners' => $ownerMetadata,
            'sources' => $sourceMetadata,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @param array<string, true> $enabledModules
     * @param array<string, int> $moduleRanks
     *
     * @return list<array{
     *     filesystemPath: string,
     *     moduleId: string,
     *     moduleRank: int,
     *     packageId: string,
     *     path: string,
     *     precedence: int,
     *     root: string,
     *     sourceId: string
     * }>
     *
     * @throws ConfigInvalidException
     */
    private static function normalizeSources(
        array $sources,
        array $enabledModules,
        array $moduleRanks,
    ): array {
        $normalized = [];

        foreach ($sources as $source) {
            $root = self::requiredString($source, 'root');
            $packageId = self::requiredString($source, 'packageId');
            $moduleId = self::requiredString($source, 'moduleId');
            $path = self::requiredString($source, 'path');
            $filesystemPath = self::requiredString($source, 'filesystemPath');
            $sourceId = self::optionalString($source, 'sourceId');
            $precedence = self::optionalNonNegativeInt($source, 'precedence');

            self::assertValidRoot($root);
            self::assertSafePackageId($packageId);
            self::assertSafeModuleId($moduleId);
            self::assertSafeRelativePath($path);
            self::assertSafeFilesystemPathForRead($filesystemPath);
            self::assertPackageDefaultPathMatchesRoot($path, $root);

            if (!isset($enabledModules[$moduleId])) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            $moduleRank = $moduleRanks[$moduleId] ?? 0;

            $sourceId ??= self::defaultSourceId(
                packageId: $packageId,
                root: $root,
            );

            $normalized[\str_pad((string)$moduleRank, 10, '0', \STR_PAD_LEFT)
            . "\0" . $root
            . "\0" . $packageId
            . "\0" . $path] = [
                'filesystemPath' => $filesystemPath,
                'moduleId' => $moduleId,
                'moduleRank' => $moduleRank,
                'packageId' => $packageId,
                'path' => \str_replace('\\', '/', $path),
                'precedence' => $precedence,
                'root' => $root,
                'sourceId' => $sourceId,
            ];
        }

        \ksort($normalized, \SORT_STRING);

        return \array_values($normalized);
    }

    /**
     * @return array<string, true>
     */
    private static function enabledModuleLookup(ModulePlan $modulePlan): array
    {
        $lookup = [];

        foreach ($modulePlan->enabled() as $moduleId) {
            $lookup[$moduleId->value()] = true;
        }

        return $lookup;
    }

    /**
     * @return array<string, int>
     */
    private static function moduleRankLookup(ModulePlan $modulePlan): array
    {
        $ranks = [];

        foreach ($modulePlan->topologicalOrder() as $rank => $moduleId) {
            $ranks[$moduleId->value()] = $rank;
        }

        return $ranks;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigInvalidException
     */
    private static function requireConfigSubtree(string $filesystemPath): array
    {
        if (!\is_file($filesystemPath) || !\is_readable($filesystemPath)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        try {
            $config = self::requireFile($filesystemPath);
        } catch (\Throwable) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_FILE_RETURN_TYPE_INVALID,
            );
        }

        if (!\is_array($config)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_FILE_RETURN_TYPE_INVALID,
            );
        }

        return $config;
    }

    private static function requireFile(string $filesystemPath): mixed
    {
        return require $filesystemPath;
    }

    /**
     * @param array<array-key, mixed> $subtree
     *
     * @throws ConfigInvalidException
     */
    private static function assertRootSubtreeShape(array $subtree, string $root): void
    {
        if (\array_key_exists($root, $subtree)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_FILE_RETURN_TYPE_INVALID,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws ConfigInvalidException
     */
    private static function assertPlainConfigArray(array $config): void
    {
        self::assertPlainConfigValue($config);
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertPlainConfigValue(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        if (\is_float($value) || \is_resource($value) || $value instanceof \Closure || \is_object($value)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_FILE_RETURN_TYPE_INVALID,
            );
        }

        if (!\is_array($value)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_FILE_RETURN_TYPE_INVALID,
            );
        }

        foreach ($value as $item) {
            self::assertPlainConfigValue($item);
        }
    }

    /**
     * @param array<string, mixed> $source
     *
     * @throws ConfigInvalidException
     */
    private static function requiredString(array $source, string $key): string
    {
        if (!\array_key_exists($key, $source) || !\is_string($source[$key]) || $source[$key] === '') {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $source[$key];
    }

    /**
     * @param array<string, mixed> $source
     *
     * @throws ConfigInvalidException
     */
    private static function optionalString(array $source, string $key): ?string
    {
        if (!\array_key_exists($key, $source) || $source[$key] === null || $source[$key] === '') {
            return null;
        }

        if (!\is_string($source[$key])) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $source[$key];
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return int<0, max>
     *
     * @throws ConfigInvalidException
     */
    private static function optionalNonNegativeInt(array $source, string $key): int
    {
        if (!\array_key_exists($key, $source) || $source[$key] === null) {
            return 0;
        }

        if (!\is_int($source[$key]) || $source[$key] < 0) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $source[$key];
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertValidRoot(string $root): void
    {
        if (\preg_match('/\A[a-z][a-z0-9_]*\z/', $root) !== 1) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if ($root === self::rootNameFromAggregateFile()) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertSafePackageId(string $packageId): void
    {
        if (!self::isSafeSingleLineIdentifier($packageId)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (!\str_contains($packageId, '/')) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertSafeModuleId(string $moduleId): void
    {
        if (!self::isSafeSingleLineIdentifier($moduleId)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertSafeRelativePath(string $path): void
    {
        $path = \str_replace('\\', '/', $path);

        if ($path === '') {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (\preg_match('/^\s|\s$/', $path) === 1) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (\str_contains($path, "\0") || \str_contains($path, "\r") || \str_contains($path, "\n")) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (\preg_match('/\A[A-Za-z]:\//', $path) === 1) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (\str_starts_with($path, '/') || \str_starts_with($path, '\\')) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (\str_contains($path, ':') || \str_contains($path, '://')) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        foreach (\explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '..') {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }
        }
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertSafeFilesystemPathForRead(string $filesystemPath): void
    {
        if ($filesystemPath === '') {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        if (
            \str_contains($filesystemPath, "\0")
            || \str_contains($filesystemPath, "\r")
            || \str_contains($filesystemPath, "\n")
        ) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertPackageDefaultPathMatchesRoot(
        string $path,
        string $root,
    ): void {
        $path = \str_replace('\\', '/', $path);

        if (
            $path === self::CONFIG_DIRECTORY . '/' . self::AGGREGATE_ROOT_MAP_FILE
            || \str_ends_with($path, '/' . self::CONFIG_DIRECTORY . '/' . self::AGGREGATE_ROOT_MAP_FILE)
        ) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $expectedSuffix = '/' . self::CONFIG_DIRECTORY . '/' . $root . self::PHP_EXTENSION;

        if (
            $path !== self::CONFIG_DIRECTORY . '/' . $root . self::PHP_EXTENSION
            && !\str_ends_with($path, $expectedSuffix)
        ) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }
    }

    private static function isSafeSingleLineIdentifier(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (\preg_match('/^\s|\s$/', $value) === 1) {
            return false;
        }

        if (\str_contains($value, "\0") || \str_contains($value, "\r") || \str_contains($value, "\n")) {
            return false;
        }

        if (\preg_match('/\A[A-Za-z]:[\\\\\/]/', $value) === 1) {
            return false;
        }

        if (\str_starts_with($value, '/') || \str_starts_with($value, '\\')) {
            return false;
        }

        if (\str_contains($value, ':') || \str_contains($value, '://')) {
            return false;
        }

        $normalized = \str_replace('\\', '/', $value);

        return !(
            $normalized === '..'
            || \str_starts_with($normalized, '../')
            || \str_contains($normalized, '/../')
            || \str_ends_with($normalized, '/..')
        );
    }

    private static function defaultSourceId(
        string $packageId,
        string $root,
    ): string {
        return $packageId . '/config/defaults/' . $root;
    }

    private static function rootNameFromAggregateFile(): string
    {
        return \substr(
            self::AGGREGATE_ROOT_MAP_FILE,
            0,
            -\strlen(self::PHP_EXTENSION),
        );
    }
}
