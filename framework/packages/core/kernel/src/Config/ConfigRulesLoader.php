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

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Module\ModulePlan;

/**
 * Deterministic config ruleset loader.
 *
 * This loader owns only loading and normalizing declarative `config/rules.php`
 * files. It does not validate application config values and does not execute
 * package-provided validators.
 *
 * Rules files are data files:
 *
 * - they MUST return a plain PHP array;
 * - returned data MUST NOT contain closures, callable arrays, objects, or
 *   resources;
 * - returned data MUST be accepted by ConfigRuleset normalization;
 * - `configRoot` inside the rules file MUST match the source root supplied by
 *   the package/module config location authority.
 *
 * Package-owned loading is intentionally driven by explicit rule-file source
 * candidates. ModulePlanEntry does not expose package filesystem paths, so this
 * loader must not infer or scan arbitrary package directories.
 *
 * Source candidates are expected to be produced by a Kernel-owned package config
 * location/discovery layer from enabled modules and the config roots registry.
 *
 * The loader preserves root/package/module ownership metadata for validation
 * diagnostics and ConfigExplainer. It intentionally does not require every
 * user-owned/custom root to have a ruleset.
 *
 * Diagnostics MUST NOT expose raw rules payloads, raw config values, filesystem
 * absolute paths, PHP warnings, stack traces, Composer raw payloads, secrets,
 * tokens, DSNs, cookies, headers, raw SQL, or previous throwable messages.
 *
 * @internal
 */
final readonly class ConfigRulesLoader
{
    private const string RULES_FILE_BASENAME = 'rules.php';

    private const string OWNER_PACKAGE_ID = 'packageId';
    private const string OWNER_MODULE_ID = 'moduleId';
    private const string OWNER_ROOT = 'root';
    private const string OWNER_SOURCE_ID = 'sourceId';
    private const string OWNER_PATH = 'path';

    /**
     * Loads package-owned rulesets from enabled ModulePlan modules only.
     *
     * The `$sources` list is intentionally explicit. Each source describes one
     * package-owned `config/rules.php` file already discovered by a trusted
     * Kernel config-location layer.
     *
     * Required source shape:
     *
     * ```php
     * [
     *     'root' => 'kernel',
     *     'packageId' => 'core/kernel',
     *     'moduleId' => 'core.kernel',
     *     'path' => 'framework/packages/core/kernel/config/rules.php',
     *     'filesystemPath' => '/absolute/runtime/path/to/config/rules.php',
     * ]
     * ```
     *
     * `path` is safe repo-relative/logical metadata.
     * `filesystemPath` is used only for `require`; it is never exposed through
     * diagnostics or returned metadata.
     *
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $sources
     *
     * @return array{
     *     rulesets: list<ConfigRuleset>,
     *     sources: array<string, ConfigValueSource>,
     *     owners: array<string, array{
     *         moduleId: string|null,
     *         packageId: string,
     *         path: string,
     *         root: string,
     *         sourceId: string
     *     }>
     * }
     *
     * @throws ConfigInvalidException
     */
    public function loadPackageRulesets(
        ModulePlan $modulePlan,
        array $sources,
    ): array {
        return $this->load(
            sources: $sources,
            modulePlan: $modulePlan,
            requireEnabledModule: true,
        );
    }

    /**
     * Loads explicitly supplied user/module/application rulesets.
     *
     * This method exists for future explicit application rules mechanisms or
     * module-owned custom rulesets that are not represented as package-owned
     * ModulePlan entries.
     *
     * It loads only the supplied source candidates and never requires every
     * user-owned/custom root to have a ruleset.
     *
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId?: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $sources
     *
     * @return array{
     *     rulesets: list<ConfigRuleset>,
     *     sources: array<string, ConfigValueSource>,
     *     owners: array<string, array{
     *         moduleId: string|null,
     *         packageId: string,
     *         path: string,
     *         root: string,
     *         sourceId: string
     *     }>
     * }
     *
     * @throws ConfigInvalidException
     */
    public function loadRulesets(array $sources): array
    {
        return $this->load(
            sources: $sources,
            modulePlan: null,
            requireEnabledModule: false,
        );
    }

    /**
     * @param list<array<string, mixed>> $sources
     *
     * @return array{
     *     rulesets: list<ConfigRuleset>,
     *     sources: array<string, ConfigValueSource>,
     *     owners: array<string, array{
     *         moduleId: string|null,
     *         packageId: string,
     *         path: string,
     *         root: string,
     *         sourceId: string
     *     }>
     * }
     *
     * @throws ConfigInvalidException
     */
    private function load(
        array $sources,
        ?ModulePlan $modulePlan,
        bool $requireEnabledModule,
    ): array {
        if ($sources === []) {
            return [
                'owners' => [],
                'rulesets' => [],
                'sources' => [],
            ];
        }

        if (!\array_is_list($sources)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $enabledModuleIds = $modulePlan === null
            ? []
            : self::enabledModuleIdLookup($modulePlan);

        $normalizedSources = self::normalizeSources(
            sources: $sources,
            enabledModuleIds: $enabledModuleIds,
            requireEnabledModule: $requireEnabledModule,
        );

        $rulesets = [];
        $sourceMetadata = [];
        $ownerMetadata = [];

        foreach ($normalizedSources as $source) {
            $rules = self::requireRulesArray($source['filesystemPath']);

            self::assertPlainRulesArray($rules);
            self::assertConfigRootMatchesSource($rules, $source['root']);

            try {
                $ruleset = ConfigRuleset::fromArray($source['root'], $rules);
            } catch (\InvalidArgumentException) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_RULESET_INVALID,
                );
            }

            $root = $ruleset->root();

            if (isset($rulesets[$root])) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_RULESET_INVALID,
                );
            }

            $rulesets[$root] = $ruleset;
            $sourceMetadata[$root] = new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: $root,
                sourceId: $source['sourceId'],
                path: $source['path'],
                keyPath: null,
                directive: null,
                precedence: $source['precedence'],
                redacted: false,
                meta: [
                    'kind' => 'config_ruleset',
                    'moduleId' => $source['moduleId'],
                    'packageId' => $source['packageId'],
                ],
            );

            $ownerMetadata[$root] = [
                self::OWNER_MODULE_ID => $source['moduleId'],
                self::OWNER_PACKAGE_ID => $source['packageId'],
                self::OWNER_PATH => $source['path'],
                self::OWNER_ROOT => $root,
                self::OWNER_SOURCE_ID => $source['sourceId'],
            ];
        }

        \ksort($rulesets, \SORT_STRING);
        \ksort($sourceMetadata, \SORT_STRING);
        \ksort($ownerMetadata, \SORT_STRING);

        return [
            'owners' => $ownerMetadata,
            'rulesets' => \array_values($rulesets),
            'sources' => $sourceMetadata,
        ];
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @param array<string, true> $enabledModuleIds
     *
     * @return list<array{
     *     filesystemPath: string,
     *     moduleId: string|null,
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
        array $enabledModuleIds,
        bool $requireEnabledModule,
    ): array {
        $normalized = [];

        foreach ($sources as $source) {
            $root = self::requiredString($source, 'root');
            $packageId = self::requiredString($source, 'packageId');
            $moduleId = self::optionalString($source, 'moduleId');
            $path = self::requiredString($source, 'path');
            $filesystemPath = self::requiredString($source, 'filesystemPath');
            $sourceId = self::optionalString($source, 'sourceId');
            $precedence = self::optionalNonNegativeInt($source, 'precedence');

            self::assertValidRoot($root);
            self::assertSafePackageId($packageId);
            self::assertSafeOptionalModuleId($moduleId);
            self::assertSafeRelativePath($path);
            self::assertSafeFilesystemPathForRead($filesystemPath);

            if (!\str_ends_with(\str_replace('\\', '/', $path), '/config/' . self::RULES_FILE_BASENAME)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if ($requireEnabledModule) {
                if ($moduleId === null) {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }

                if (!isset($enabledModuleIds[$moduleId])) {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }
            }

            $sourceId ??= self::defaultSourceId(
                packageId: $packageId,
                root: $root,
            );

            $normalized[$root . "\0" . $packageId . "\0" . $path] = [
                'filesystemPath' => $filesystemPath,
                'moduleId' => $moduleId,
                'packageId' => $packageId,
                'path' => $path,
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
    private static function enabledModuleIdLookup(ModulePlan $modulePlan): array
    {
        $lookup = [];

        foreach ($modulePlan->enabled() as $moduleId) {
            $lookup[$moduleId->value()] = true;
        }

        return $lookup;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigInvalidException
     */
    private static function requireRulesArray(string $filesystemPath): array
    {
        if (!\is_file($filesystemPath) || !\is_readable($filesystemPath)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        try {
            $rules = self::requireFile($filesystemPath);
        } catch (\Throwable) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_RULES_FILE_RETURN_TYPE_INVALID,
            );
        }

        if (!\is_array($rules)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_RULES_FILE_RETURN_TYPE_INVALID,
            );
        }

        return $rules;
    }

    private static function requireFile(string $filesystemPath): mixed
    {
        return require $filesystemPath;
    }

    /**
     * @param array<array-key, mixed> $rules
     *
     * @throws ConfigInvalidException
     */
    private static function assertPlainRulesArray(array $rules): void
    {
        self::assertPlainRulesValue($rules);
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function assertPlainRulesValue(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        if (\is_float($value) || \is_resource($value) || $value instanceof \Closure || \is_object($value)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_RULESET_INVALID,
            );
        }

        if (!\is_array($value)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_RULESET_INVALID,
            );
        }

        if (\is_callable($value)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_RULESET_INVALID,
            );
        }

        foreach ($value as $item) {
            self::assertPlainRulesValue($item);
        }
    }

    /**
     * @param array<array-key, mixed> $rules
     *
     * @throws ConfigInvalidException
     */
    private static function assertConfigRootMatchesSource(array $rules, string $root): void
    {
        if (!isset($rules['configRoot']) || !\is_string($rules['configRoot']) || $rules['configRoot'] === '') {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_RULESET_INVALID,
            );
        }

        if ($rules['configRoot'] !== $root) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_RULESET_INVALID,
            );
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
    private static function assertSafeOptionalModuleId(?string $moduleId): void
    {
        if ($moduleId === null) {
            return;
        }

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

        if (\str_contains($filesystemPath, "\0") || \str_contains($filesystemPath, "\r") || \str_contains(
            $filesystemPath,
            "\n"
        )) {
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
        return $packageId . '/config/rules/' . $root;
    }
}
