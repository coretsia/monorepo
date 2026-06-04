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

namespace Coretsia\Kernel\Module;

use Coretsia\Kernel\Boot\BootstrapConfig;

/**
 * Creates per-resolution filesystem mode preset loaders.
 *
 * This factory is the boundary between:
 *
 * - Kernel mode config (`kernel.modes.*`);
 * - the core/kernel package root;
 * - the resolved Bootstrap Phase A skeleton root;
 * - the filesystem-backed mode preset loader.
 *
 * It resolves:
 *
 * - framework defaults path:
 *   package root + kernel.modes.defaults_path
 *
 * - skeleton override path:
 *   BootstrapConfig::skeletonRoot() + kernel.modes.overrides_path
 *
 * Configured mode paths are relative path fragments only. Absolute configured
 * defaults/overrides paths, path traversal, stream wrappers, NUL bytes, empty
 * segments, and current/parent directory segments are rejected before a loader
 * is constructed.
 *
 * The factory must not cache loaders, loaded presets, or BootstrapConfig.
 *
 * @internal
 */
final readonly class ModePresetLoaderFactory
{
    private const int SUPPORTED_SCHEMA_VERSION = 1;
    private const int MAX_RELATIVE_PATH_BYTES = 256;

    private string $packageRoot;
    private string $defaultsPath;
    private string $overridesPath;

    /**
     * @param array<string, mixed> $modesConfig The `kernel.modes` config subtree.
     */
    public function __construct(
        string $packageRoot,
        array $modesConfig,
        private ModePresetSchemaValidator $schemaValidator,
    ) {
        $this->packageRoot = self::normalizeRootBoundary(
            root: $packageRoot,
            reason: 'mode-preset-loader-factory-package-root-invalid',
        );

        self::assertSupportedSchemaVersion($modesConfig);

        $this->defaultsPath = self::readRelativePath(
            config: $modesConfig,
            key: 'defaults_path',
            reason: 'mode-preset-loader-factory-defaults-path-invalid',
        );

        $this->overridesPath = self::readRelativePath(
            config: $modesConfig,
            key: 'overrides_path',
            reason: 'mode-preset-loader-factory-overrides-path-invalid',
        );
    }

    public function createFor(BootstrapConfig $bootstrapConfig): FilesystemModePresetLoader
    {
        $skeletonRoot = self::normalizeRootBoundary(
            root: $bootstrapConfig->skeletonRoot(),
            reason: 'mode-preset-loader-factory-skeleton-root-invalid',
        );

        $frameworkDefaultsPath = self::joinPath(
            root: $this->packageRoot,
            relativePath: $this->defaultsPath,
        );

        $skeletonOverridesPath = self::joinPath(
            root: $skeletonRoot,
            relativePath: $this->overridesPath,
        );

        return new FilesystemModePresetLoader(
            frameworkDefaultsPath: $frameworkDefaultsPath,
            skeletonOverridesPath: $skeletonOverridesPath,
            schemaValidator: $this->schemaValidator,
        );
    }

    /**
     * @param array<string, mixed> $modesConfig
     */
    private static function assertSupportedSchemaVersion(array $modesConfig): void
    {
        if (!\array_key_exists('schema_version', $modesConfig)) {
            throw new \InvalidArgumentException('mode-preset-loader-factory-schema-version-missing');
        }

        if ($modesConfig['schema_version'] !== self::SUPPORTED_SCHEMA_VERSION) {
            throw new \InvalidArgumentException('mode-preset-loader-factory-schema-version-invalid');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function readRelativePath(
        array $config,
        string $key,
        string $reason,
    ): string {
        if (!\array_key_exists($key, $config)) {
            throw new \InvalidArgumentException($reason);
        }

        $value = $config[$key];

        if (!\is_string($value)) {
            throw new \InvalidArgumentException($reason);
        }

        return self::normalizeRelativePath($value, $reason);
    }

    private static function normalizeRelativePath(string $path, string $reason): string
    {
        if ($path === '') {
            throw new \InvalidArgumentException($reason);
        }

        if (\strlen($path) > self::MAX_RELATIVE_PATH_BYTES) {
            throw new \InvalidArgumentException($reason);
        }

        if (\str_contains($path, "\0")) {
            throw new \InvalidArgumentException($reason);
        }

        if (\str_contains($path, '://')) {
            throw new \InvalidArgumentException($reason);
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            throw new \InvalidArgumentException($reason);
        }

        if (\strlen($path) >= 2 && $path[1] === ':' && self::isAsciiAlpha($path[0])) {
            throw new \InvalidArgumentException($reason);
        }

        $normalized = \str_replace('\\', '/', $path);

        if ($normalized !== \trim($normalized, '/')) {
            throw new \InvalidArgumentException($reason);
        }

        $segments = \explode('/', $normalized);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException($reason);
            }

            if (!self::isSafeRelativePathSegment($segment)) {
                throw new \InvalidArgumentException($reason);
            }
        }

        return \implode('/', $segments);
    }

    private static function normalizeRootBoundary(string $root, string $reason): string
    {
        if ($root === '') {
            throw new \InvalidArgumentException($reason);
        }

        if (\str_contains($root, "\0")) {
            throw new \InvalidArgumentException($reason);
        }

        if (\str_contains($root, "\r") || \str_contains($root, "\n")) {
            throw new \InvalidArgumentException($reason);
        }

        if (\str_contains($root, '://')) {
            throw new \InvalidArgumentException($reason);
        }

        return $root;
    }

    private static function joinPath(string $root, string $relativePath): string
    {
        $trimmedRoot = \rtrim($root, '/\\');
        $relativePath = \str_replace('/', \DIRECTORY_SEPARATOR, $relativePath);

        if ($trimmedRoot === '') {
            return \DIRECTORY_SEPARATOR . $relativePath;
        }

        return $trimmedRoot . \DIRECTORY_SEPARATOR . $relativePath;
    }

    private static function isSafeRelativePathSegment(string $segment): bool
    {
        $length = \strlen($segment);

        for ($i = 0; $i < $length; ++$i) {
            $char = $segment[$i];

            if (
                ($char >= 'a' && $char <= 'z')
                || ($char >= 'A' && $char <= 'Z')
                || ($char >= '0' && $char <= '9')
                || $char === '_'
                || $char === '-'
                || $char === '.'
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    private static function isAsciiAlpha(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z')
            || ($char >= 'A' && $char <= 'Z');
    }
}
