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
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;

/**
 * Deterministic skeleton/application config loader.
 *
 * This loader owns loading declared Phase B skeleton/app config locations:
 *
 * - skeleton shared aggregate root-map config:
 *   skeleton/config/roots.php
 * - skeleton shared split root-subtree config:
 *   skeleton/config/<root>.php
 * - skeleton environment aggregate root-map config:
 *   skeleton/config/environments/<appEnv>/roots.php
 * - skeleton environment split root-subtree config:
 *   skeleton/config/environments/<appEnv>/<root>.php
 * - app shared aggregate root-map config:
 *   skeleton/apps/<appTarget>/config/roots.php
 * - app shared split root-subtree config:
 *   skeleton/apps/<appTarget>/config/<root>.php
 * - app environment aggregate root-map config:
 *   skeleton/apps/<appTarget>/config/environments/<appEnv>/roots.php
 * - app environment split root-subtree config:
 *   skeleton/apps/<appTarget>/config/environments/<appEnv>/<root>.php
 *
 * `roots.php` is the aggregate root-map file for one config layer.
 * `<root>.php` is the split root-subtree file for one config root.
 *
 * At the same layer, `roots.php` is loaded before split `<root>.php` files, so
 * root-specific files override aggregate root-map entries when ConfigKernel
 * folds returned entries in order through ConfigMerger.
 *
 * The loader is path-list based. It does not scan config directories. Split
 * root files are checked only for explicitly declared root names supplied by
 * the caller. Aggregate `roots.php` files may contain user-owned/custom roots.
 * Split-only custom roots are supported when their root names are supplied by
 * the Kernel config-location builder.
 *
 * The loader MUST NOT read `skeleton/config/app.php`; that file is Bootstrap
 * Phase A input only.
 *
 * Diagnostics MUST NOT expose raw config values, raw filesystem absolute paths,
 * PHP warnings, stack traces, secrets, tokens, DSNs, cookies, headers, raw SQL,
 * payloads, or previous throwable messages.
 *
 * @internal
 */
final readonly class SkeletonConfigLoader
{
    private const string CONFIG_DIRECTORY = 'config';
    private const string APPS_DIRECTORY = 'apps';
    private const string ENVIRONMENTS_DIRECTORY = 'environments';
    private const string AGGREGATE_ROOT_MAP_FILE = 'roots.php';
    private const string PHP_EXTENSION = '.php';

    private const string LAYER_SKELETON_SHARED = 'skeleton_shared';
    private const string LAYER_SKELETON_ENVIRONMENT = 'skeleton_environment';
    private const string LAYER_APP_SHARED = 'app_shared';
    private const string LAYER_APP_ENVIRONMENT = 'app_environment';

    private const string KIND_AGGREGATE_ROOT_MAP = 'aggregate_root_map';
    private const string KIND_SPLIT_ROOT_SUBTREE = 'split_root_subtree';

    private const string OWNER_APP_ENV = 'appEnv';
    private const string OWNER_APP_TARGET = 'appTarget';
    private const string OWNER_KIND = 'kind';
    private const string OWNER_LAYER = 'layer';
    private const string OWNER_PATH = 'path';
    private const string OWNER_ROOT = 'root';
    private const string OWNER_SOURCE_ID = 'sourceId';
    private const string OWNER_TYPE = 'type';

    private const int PRECEDENCE_SKELETON_SHARED_AGGREGATE = 100;
    private const int PRECEDENCE_SKELETON_SHARED_ROOT = 101;
    private const int PRECEDENCE_SKELETON_ENVIRONMENT_AGGREGATE = 200;
    private const int PRECEDENCE_SKELETON_ENVIRONMENT_ROOT = 201;
    private const int PRECEDENCE_APP_SHARED_AGGREGATE = 300;
    private const int PRECEDENCE_APP_SHARED_ROOT = 301;
    private const int PRECEDENCE_APP_ENVIRONMENT_AGGREGATE = 400;
    private const int PRECEDENCE_APP_ENVIRONMENT_ROOT = 401;

    public function __construct(
        private DirectiveProcessor $directiveProcessor,
    ) {
    }

    /**
     * Loads declared skeleton/app config entries in deterministic Phase B order.
     *
     * Returned `entries` are intentionally not merged here. ConfigKernel owns
     * folding entries through ConfigMerger in the active Phase B order.
     *
     * `$splitRoots` is the deterministic path list for `<root>.php` candidates.
     * The loader also adds roots discovered inside loaded aggregate `roots.php`
     * files to the checked root-specific candidates for that same layer.
     *
     * @param list<non-empty-string> $splitRoots
     *
     * @return array{
     *     entries: list<array{
     *         config: array<string, mixed>,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         path: non-empty-string,
     *         precedence: int,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>,
     *     sources: list<ConfigValueSource>,
     *     owners: list<array{
     *         appEnv: non-empty-string,
     *         appTarget: non-empty-string,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         path: non-empty-string,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>,
     *     sourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         len?: int,
     *         path: non-empty-string,
     *         readable: bool,
     *         root?: non-empty-string,
     *         sourceId: non-empty-string
     *     }>
     * }
     *
     * @throws ConfigInvalidException
     */
    public function load(
        BootstrapConfig $bootstrapConfig,
        array $splitRoots = [],
    ): array {
        $appEnv = self::safePathToken($bootstrapConfig->appEnv());
        $appTarget = $bootstrapConfig->appTarget()->value;
        $skeletonRoot = $bootstrapConfig->skeletonRoot();

        $declaredSplitRoots = self::normalizeSplitRoots($splitRoots);

        $collector = [
            'entries' => [],
            'owners' => [],
            'sources' => [],
            'sourceFiles' => [],
        ];

        $this->loadLayer(
            collector: $collector,
            filesystemBase: $skeletonRoot,
            logicalBase: 'skeleton',
            configRelativeDirectory: self::CONFIG_DIRECTORY,
            layer: self::LAYER_SKELETON_SHARED,
            type: ConfigSourceType::SkeletonConfig,
            appEnv: $appEnv,
            appTarget: $appTarget,
            declaredSplitRoots: $declaredSplitRoots,
            aggregatePrecedence: self::PRECEDENCE_SKELETON_SHARED_AGGREGATE,
            rootPrecedence: self::PRECEDENCE_SKELETON_SHARED_ROOT,
        );

        $this->loadLayer(
            collector: $collector,
            filesystemBase: $skeletonRoot,
            logicalBase: 'skeleton',
            configRelativeDirectory: self::CONFIG_DIRECTORY . '/' . self::ENVIRONMENTS_DIRECTORY . '/' . $appEnv,
            layer: self::LAYER_SKELETON_ENVIRONMENT,
            type: ConfigSourceType::SkeletonConfig,
            appEnv: $appEnv,
            appTarget: $appTarget,
            declaredSplitRoots: $declaredSplitRoots,
            aggregatePrecedence: self::PRECEDENCE_SKELETON_ENVIRONMENT_AGGREGATE,
            rootPrecedence: self::PRECEDENCE_SKELETON_ENVIRONMENT_ROOT,
        );

        $this->loadLayer(
            collector: $collector,
            filesystemBase: $skeletonRoot,
            logicalBase: 'skeleton',
            configRelativeDirectory: self::APPS_DIRECTORY . '/' . $appTarget . '/' . self::CONFIG_DIRECTORY,
            layer: self::LAYER_APP_SHARED,
            type: ConfigSourceType::AppConfig,
            appEnv: $appEnv,
            appTarget: $appTarget,
            declaredSplitRoots: $declaredSplitRoots,
            aggregatePrecedence: self::PRECEDENCE_APP_SHARED_AGGREGATE,
            rootPrecedence: self::PRECEDENCE_APP_SHARED_ROOT,
        );

        $this->loadLayer(
            collector: $collector,
            filesystemBase: $skeletonRoot,
            logicalBase: 'skeleton',
            configRelativeDirectory: self::APPS_DIRECTORY . '/' . $appTarget . '/' . self::CONFIG_DIRECTORY . '/' . self::ENVIRONMENTS_DIRECTORY . '/' . $appEnv,
            layer: self::LAYER_APP_ENVIRONMENT,
            type: ConfigSourceType::AppConfig,
            appEnv: $appEnv,
            appTarget: $appTarget,
            declaredSplitRoots: $declaredSplitRoots,
            aggregatePrecedence: self::PRECEDENCE_APP_ENVIRONMENT_AGGREGATE,
            rootPrecedence: self::PRECEDENCE_APP_ENVIRONMENT_ROOT,
        );

        return self::normalizeCollectorForReturn($collector);
    }

    /**
     * @param array{
     *     entries: list<array{
     *         config: array<string, mixed>,
     *         kind: string,
     *         layer: string,
     *         path: string,
     *         precedence: int,
     *         sourceId: string,
     *         type: string
     *     }>,
     *     sources: list<ConfigValueSource>,
     *     owners: list<array{
     *         appEnv: string,
     *         appTarget: string,
     *         kind: string,
     *         layer: string,
     *         path: string,
     *         root: string,
     *         sourceId: string,
     *         type: string
     *     }>,
     *     sourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: string,
     *         layer: string,
     *         len?: int,
     *         path: string,
     *         readable: bool,
     *         root?: string,
     *         sourceId: string
     *     }>
     * } $collector
     *
     * @return array{
     *     entries: list<array{
     *         config: array<string, mixed>,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         path: non-empty-string,
     *         precedence: int,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>,
     *     sources: list<ConfigValueSource>,
     *     owners: list<array{
     *         appEnv: non-empty-string,
     *         appTarget: non-empty-string,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         path: non-empty-string,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>,
     *     sourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         len?: int,
     *         path: non-empty-string,
     *         readable: bool,
     *         root?: non-empty-string,
     *         sourceId: non-empty-string
     *     }>
     * }
     *
     * @throws ConfigInvalidException
     */
    private static function normalizeCollectorForReturn(array $collector): array
    {
        foreach ($collector['entries'] as $entry) {
            foreach (['kind', 'layer', 'path', 'sourceId', 'type'] as $key) {
                if (!isset($entry[$key]) || !\is_string($entry[$key]) || $entry[$key] === '') {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }
            }
        }

        foreach ($collector['owners'] as $owner) {
            foreach (['appEnv', 'appTarget', 'kind', 'layer', 'path', 'root', 'sourceId', 'type'] as $key) {
                if (!isset($owner[$key]) || !\is_string($owner[$key]) || $owner[$key] === '') {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }
            }
        }

        foreach ($collector['sourceFiles'] as $sourceFile) {
            foreach (['kind', 'layer', 'path', 'sourceId'] as $key) {
                if (!isset($sourceFile[$key]) || !\is_string($sourceFile[$key]) || $sourceFile[$key] === '') {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }
            }

            if (
                !self::isSafeMetadataToken($sourceFile['kind'])
                || !self::isSafeMetadataToken($sourceFile['layer'])
                || !self::isSafeRelativeMetadataPath($sourceFile['path'])
                || !self::isSafeSourceId($sourceFile['sourceId'])
            ) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if (!isset($sourceFile['exists']) || !\is_bool($sourceFile['exists'])) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if (!isset($sourceFile['readable']) || !\is_bool($sourceFile['readable'])) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if (isset($sourceFile['hash']) && (
                !\is_string($sourceFile['hash'])
                    || \preg_match('/\A[a-f0-9]{64}\z/', $sourceFile['hash']) !== 1
            )) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if (isset($sourceFile['len']) && (!\is_int($sourceFile['len']) || $sourceFile['len'] < 0)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if (isset($sourceFile['root']) && (
                !\is_string($sourceFile['root'])
                    || !self::isValidRoot($sourceFile['root'])
            )) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }
        }

        /** @var array{
         *     entries: list<array{
         *         config: array<string, mixed>,
         *         kind: non-empty-string,
         *         layer: non-empty-string,
         *         path: non-empty-string,
         *         precedence: int,
         *         sourceId: non-empty-string,
         *         type: non-empty-string
         *     }>,
         *     sources: list<ConfigValueSource>,
         *     owners: list<array{
         *         appEnv: non-empty-string,
         *         appTarget: non-empty-string,
         *         kind: non-empty-string,
         *         layer: non-empty-string,
         *         path: non-empty-string,
         *         root: non-empty-string,
         *         sourceId: non-empty-string,
         *         type: non-empty-string
         *     }>,
         *     sourceFiles: list<array{
         *         exists: bool,
         *         hash?: non-empty-string,
         *         kind: non-empty-string,
         *         layer: non-empty-string,
         *         len?: int,
         *         path: non-empty-string,
         *         readable: bool,
         *         root?: non-empty-string,
         *         sourceId: non-empty-string
         *     }>
         * } $collector
         */

        return $collector;
    }

    /**
     * @param array{
     *     entries: list<array{
     *         config: array<string, mixed>,
     *         kind: string,
     *         layer: string,
     *         path: string,
     *         precedence: int,
     *         sourceId: string,
     *         type: string
     *     }>,
     *     sources: list<ConfigValueSource>,
     *     owners: list<array{
     *         appEnv: string,
     *         appTarget: string,
     *         kind: string,
     *         layer: string,
     *         path: string,
     *         root: string,
     *         sourceId: string,
     *         type: string
     *     }>,
     *     sourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: string,
     *         layer: string,
     *         len?: int,
     *         path: string,
     *         readable: bool,
     *         root?: string,
     *         sourceId: string
     *     }>
     * } $collector
     * @param array<string, true> $declaredSplitRoots
     *
     * @throws ConfigInvalidException
     */
    private function loadLayer(
        array &$collector,
        string $filesystemBase,
        string $logicalBase,
        string $configRelativeDirectory,
        string $layer,
        ConfigSourceType $type,
        string $appEnv,
        string $appTarget,
        array $declaredSplitRoots,
        int $aggregatePrecedence,
        int $rootPrecedence,
    ): void {
        $logicalDirectory = $logicalBase . '/' . $configRelativeDirectory;
        $aggregatePath = $logicalDirectory . '/' . self::AGGREGATE_ROOT_MAP_FILE;
        $aggregateFilesystemPath = self::joinFilesystemPath(
            $filesystemBase,
            $configRelativeDirectory . '/' . self::AGGREGATE_ROOT_MAP_FILE,
        );

        $layerRoots = $declaredSplitRoots;

        $aggregateSourceId = self::sourceId(
            layer: $layer,
            kind: self::KIND_AGGREGATE_ROOT_MAP,
            path: $aggregatePath,
        );

        $collector['sourceFiles'][] = self::sourceFileMetadata(
            layer: $layer,
            kind: self::KIND_AGGREGATE_ROOT_MAP,
            path: $aggregatePath,
            sourceId: $aggregateSourceId,
            filesystemPath: $aggregateFilesystemPath,
            root: null,
        );

        if (self::isReadableFile($aggregateFilesystemPath)) {
            $rootMap = self::requireConfigArray($aggregateFilesystemPath);

            self::assertPlainConfigArray($rootMap);
            self::assertGlobalRootMapShape($rootMap);

            $normalizedRootMap = $this->directiveProcessor->processGlobalConfig($rootMap);

            $this->appendEntry(
                collector: $collector,
                config: $normalizedRootMap,
                type: $type,
                layer: $layer,
                kind: self::KIND_AGGREGATE_ROOT_MAP,
                path: $aggregatePath,
                sourceId: $aggregateSourceId,
                precedence: $aggregatePrecedence,
                appEnv: $appEnv,
                appTarget: $appTarget,
            );

            foreach (\array_keys($normalizedRootMap) as $root) {
                if (\is_string($root) && self::isValidRoot($root)) {
                    $layerRoots[$root] = true;
                }
            }
        }

        $rootNames = \array_keys($layerRoots);

        \usort(
            $rootNames,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        foreach ($rootNames as $root) {
            $rootPath = $logicalDirectory . '/' . $root . self::PHP_EXTENSION;
            $rootFilesystemPath = self::joinFilesystemPath(
                $filesystemBase,
                $configRelativeDirectory . '/' . $root . self::PHP_EXTENSION,
            );

            $sourceId = self::sourceId(
                layer: $layer,
                kind: self::KIND_SPLIT_ROOT_SUBTREE,
                path: $rootPath,
            );

            $collector['sourceFiles'][] = self::sourceFileMetadata(
                layer: $layer,
                kind: self::KIND_SPLIT_ROOT_SUBTREE,
                path: $rootPath,
                sourceId: $sourceId,
                filesystemPath: $rootFilesystemPath,
                root: $root,
            );

            if (!self::isReadableFile($rootFilesystemPath)) {
                continue;
            }

            $subtree = self::requireConfigArray($rootFilesystemPath);

            self::assertPlainConfigArray($subtree);
            self::assertRootSubtreeShape($subtree, $root);

            $normalizedSubtree = $this->directiveProcessor->processRootSubtree(
                root: $root,
                subtree: $subtree,
            );

            $this->appendEntry(
                collector: $collector,
                config: [
                    $root => $normalizedSubtree,
                ],
                type: $type,
                layer: $layer,
                kind: self::KIND_SPLIT_ROOT_SUBTREE,
                path: $rootPath,
                sourceId: $sourceId,
                precedence: $rootPrecedence,
                appEnv: $appEnv,
                appTarget: $appTarget,
            );
        }
    }

    /**
     * @param array{
     *     entries: list<array{
     *         config: array<string, mixed>,
     *         kind: string,
     *         layer: string,
     *         path: string,
     *         precedence: int,
     *         sourceId: string,
     *         type: string
     *     }>,
     *     sources: list<ConfigValueSource>,
     *     owners: list<array{
     *         appEnv: string,
     *         appTarget: string,
     *         kind: string,
     *         layer: string,
     *         path: string,
     *         root: string,
     *         sourceId: string,
     *         type: string
     *     }>,
     *     sourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: string,
     *         layer: string,
     *         len?: int,
     *         path: string,
     *         readable: bool,
     *         root?: string,
     *         sourceId: string
     *     }>
     * } $collector
     * @param array<string, mixed> $config
     */
    private function appendEntry(
        array &$collector,
        array $config,
        ConfigSourceType $type,
        string $layer,
        string $kind,
        string $path,
        string $sourceId,
        int $precedence,
        string $appEnv,
        string $appTarget,
    ): void {
        $collector['entries'][] = [
            'config' => $config,
            'kind' => $kind,
            'layer' => $layer,
            'path' => $path,
            'precedence' => $precedence,
            'sourceId' => $sourceId,
            'type' => $type->value,
        ];

        foreach (\array_keys($config) as $root) {
            if (!\is_string($root) || !self::isValidRoot($root)) {
                continue;
            }

            $collector['sources'][] = new ConfigValueSource(
                type: $type,
                root: $root,
                sourceId: $sourceId . '/' . $root,
                path: $path,
                keyPath: null,
                directive: null,
                precedence: $precedence,
                redacted: false,
                meta: [
                    'appEnv' => $appEnv,
                    'appTarget' => $appTarget,
                    'kind' => $kind,
                    'layer' => $layer,
                ],
            );

            $collector['owners'][] = [
                self::OWNER_APP_ENV => $appEnv,
                self::OWNER_APP_TARGET => $appTarget,
                self::OWNER_KIND => $kind,
                self::OWNER_LAYER => $layer,
                self::OWNER_PATH => $path,
                self::OWNER_ROOT => $root,
                self::OWNER_SOURCE_ID => $sourceId . '/' . $root,
                self::OWNER_TYPE => $type->value,
            ];
        }
    }

    /**
     * @return array{
     *     exists: bool,
     *     hash?: non-empty-string,
     *     kind: non-empty-string,
     *     layer: non-empty-string,
     *     len?: int,
     *     path: non-empty-string,
     *     readable: bool,
     *     root?: non-empty-string,
     *     sourceId: non-empty-string
     * }
     */
    private static function sourceFileMetadata(
        string $layer,
        string $kind,
        string $path,
        string $sourceId,
        string $filesystemPath,
        ?string $root,
    ): array {
        $metadata = [
            'exists' => false,
            'kind' => $kind,
            'layer' => $layer,
            'path' => $path,
            'readable' => false,
            'sourceId' => $sourceId,
        ];

        if ($root !== null && self::isValidRoot($root)) {
            $metadata['root'] = $root;
        }

        if (!@\file_exists($filesystemPath)) {
            return $metadata;
        }

        $metadata['exists'] = true;

        if (!self::isReadableFile($filesystemPath)) {
            return $metadata;
        }

        $contents = @\file_get_contents($filesystemPath);

        if (!\is_string($contents)) {
            return $metadata;
        }

        $bytes = self::normalizeLf($contents);

        $metadata['readable'] = true;
        $metadata['hash'] = \hash('sha256', $bytes);
        $metadata['len'] = \strlen($bytes);

        return $metadata;
    }

    private static function normalizeLf(string $bytes): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $bytes);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigInvalidException
     */
    private static function requireConfigArray(string $filesystemPath): array
    {
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
     * @param array<array-key, mixed> $rootMap
     *
     * @throws ConfigInvalidException
     */
    private static function assertGlobalRootMapShape(array $rootMap): void
    {
        if (\array_is_list($rootMap) && $rootMap !== []) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_FILE_RETURN_TYPE_INVALID,
            );
        }

        foreach ($rootMap as $root => $_subtree) {
            if (!\is_string($root) || !self::isValidRoot($root)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_FILE_RETURN_TYPE_INVALID,
                );
            }
        }
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
     * @param list<non-empty-string> $splitRoots
     *
     * @return array<string, true>
     *
     * @throws ConfigInvalidException
     */
    private static function normalizeSplitRoots(array $splitRoots): array
    {
        if ($splitRoots === []) {
            return [];
        }

        if (!\array_is_list($splitRoots)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        $roots = [];

        foreach ($splitRoots as $root) {
            if (!\is_string($root) || !self::isValidRoot($root)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if ($root === self::rootNameFromAggregateFile()) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            $roots[$root] = true;
        }

        \ksort($roots, \SORT_STRING);

        return $roots;
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function safePathToken(string $value): string
    {
        if (!self::isSafePathToken($value)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $value;
    }

    private static function isSafePathToken(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (\strlen($value) > 64) {
            return false;
        }

        if (\preg_match('/^\s|\s$/', $value) === 1) {
            return false;
        }

        if (\str_contains($value, "\0") || \str_contains($value, "\r") || \str_contains($value, "\n")) {
            return false;
        }

        if (\str_contains($value, '/') || \str_contains($value, '\\') || \str_contains($value, ':')) {
            return false;
        }

        if ($value === '.' || $value === '..' || \str_contains($value, '..')) {
            return false;
        }

        return \strspn($value, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.-') === \strlen($value);
    }

    private static function isSafeRelativeMetadataPath(string $path): bool
    {
        if ($path === '' || \strlen($path) > 256) {
            return false;
        }

        if (
            \str_contains($path, "\0")
            || \str_contains($path, "\r")
            || \str_contains($path, "\n")
            || \str_contains($path, '://')
            || \str_starts_with($path, '/')
            || \str_starts_with($path, '\\')
            || \preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) === 1
        ) {
            return false;
        }

        if (
            $path === '.'
            || $path === '..'
            || \str_starts_with($path, './')
            || \str_starts_with($path, '../')
            || \str_contains($path, '/./')
            || \str_contains($path, '/../')
            || \str_ends_with($path, '/.')
            || \str_ends_with($path, '/..')
        ) {
            return false;
        }

        return \preg_match('/\A[A-Za-z0-9_.\/-]+\z/', $path) === 1;
    }

    private static function isSafeMetadataToken(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= 128
            && \preg_match('/\A[A-Za-z0-9_.-]+\z/', $value) === 1;
    }

    private static function isSafeSourceId(string $value): bool
    {
        return self::isSafeRelativeMetadataPath($value);
    }

    private static function isReadableFile(string $filesystemPath): bool
    {
        return \is_file($filesystemPath) && \is_readable($filesystemPath);
    }

    private static function joinFilesystemPath(string $base, string $relativePath): string
    {
        $base = \rtrim($base, '/\\');

        if ($base === '') {
            return \str_replace('\\', '/', $relativePath);
        }

        return $base . '/' . \str_replace('\\', '/', $relativePath);
    }

    private static function sourceId(
        string $layer,
        string $kind,
        string $path,
    ): string {
        return 'skeleton-config/' . $layer . '/' . $kind . '/' . \str_replace('/', '_', $path);
    }

    private static function isValidRoot(string $root): bool
    {
        return \preg_match('/\A[a-z][a-z0-9_]*\z/', $root) === 1;
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
