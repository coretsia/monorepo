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
use Coretsia\Kernel\Module\Exception\CanonicalPresetOverrideException;
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Preset\CanonicalPresetNames;
use Coretsia\Kernel\Module\Preset\CanonicalPresetSource;
use Coretsia\Kernel\Module\Preset\CustomPresetSource;
use Coretsia\Kernel\Module\Preset\PresetNamespace;
use Coretsia\Kernel\Module\Preset\PresetSourceInterface;

/**
 * Creates per-resolution filesystem mode preset loaders.
 *
 * This factory is the boundary between:
 *
 * - Kernel mode config (`kernel.modes.*`);
 * - the core/kernel package root;
 * - the resolved Bootstrap Phase A application root;
 * - the filesystem-backed mode preset loader.
 *
 * It resolves:
 *
 * - Kernel package defaults path:
 *   package root + kernel.modes.defaults_path
 *
 * - application override path:
 *   BootstrapConfig::applicationRoot() + kernel.modes.overrides_path
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

    public function createFor(
        BootstrapConfig $bootstrapConfig,
        PresetNamespace $namespace,
    ): FilesystemModePresetLoader {
        return new FilesystemModePresetLoader($this->sourceFor($bootstrapConfig, $namespace), $this->schemaValidator);
    }

    /**
     * @return array{
     *     path:string,
     *     filesystemPath:string,
     *     sourceId:string,
     *     precedence:int
     * }
     */
    public function sourceCandidateFor(
        BootstrapConfig $bootstrapConfig,
        PresetNamespace $namespace,
    ): array {
        return $this->sourceFor($bootstrapConfig, $namespace)->sourceCandidate($bootstrapConfig->preset());
    }

    private function sourceFor(
        BootstrapConfig $config,
        PresetNamespace $namespace,
    ): PresetSourceInterface {
        $appRoot = self::validatedRoot($config->applicationRoot(), 'mode-preset-application-root-invalid');
        $kernelRoot = self::validatedRoot($this->packageRoot, 'mode-preset-kernel-root-invalid');
        $applicationDirectory = self::validatedDirectory($appRoot, $this->overridesPath, $config->preset());
        foreach (CanonicalPresetNames::all() as $canonicalName) {
            $reserved = \rtrim($applicationDirectory, '/\\') . \DIRECTORY_SEPARATOR . $canonicalName . '.php';
            if (\file_exists($reserved) || \is_link($reserved)) {
                throw CanonicalPresetOverrideException::forPreset($canonicalName);
            }
        }
        if ($namespace === PresetNamespace::Canonical) {
            return new CanonicalPresetSource(
                self::validatedDirectory($kernelRoot, $this->defaultsPath, $config->preset()),
                $this->defaultsPath,
                $kernelRoot,
            );
        }
        return new CustomPresetSource(
            $applicationDirectory,
            $this->overridesPath,
            $appRoot,
        );
    }

    private static function validatedRoot(string $root, string $reason): string
    {
        $resolved = \realpath($root);
        if ($resolved === false || !\is_dir($resolved)) {
            throw new \InvalidArgumentException($reason);
        }
        return $resolved;
    }

    /**
     * Resolve existing ancestors one segment at a time;
     * no configured directory may escape its owning root via symlinks.
     */
    private static function validatedDirectory(string $root, string $relativePath, string $name): string
    {
        $current = $root;
        $declared = $root;

        foreach (\explode('/', $relativePath) as $segment) {
            $declared = \rtrim($declared, '/\\') . \DIRECTORY_SEPARATOR . $segment;
            $next = \rtrim($current, '/\\') . \DIRECTORY_SEPARATOR . $segment;

            if (\file_exists($next) || \is_link($next)) {
                $resolved = \realpath($next);

                if ($resolved === false || !\is_dir($resolved) || !self::within($resolved, $root)) {
                    throw ModePresetInvalidException::forPreset(self::safeName($name));
                }

                $current = $resolved;
            } else {
                $current = $next;
            }
        }

        return $declared;
    }

    private static function safeName(string $name): string
    {
        return self::isSafePresetName($name) ? $name : 'invalid';
    }

    private static function within(string $path, string $root): bool
    {
        return $path === $root || \str_starts_with($path, \rtrim($root, '/\\') . \DIRECTORY_SEPARATOR);
    }

    private static function isSafePresetName(string $preset): bool
    {
        return \preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $preset) === 1;
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
