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

use Coretsia\Contracts\Module\ModePresetInterface;
use Coretsia\Contracts\Module\ModePresetLoaderInterface;
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;

/**
 * Filesystem-backed mode preset loader.
 *
 * This loader is created per ModulePlan resolution with already resolved
 * skeleton override and framework default directories.
 *
 * Lookup order is single-choice:
 *
 * 1. skeleton override preset file;
 * 2. framework default preset file.
 *
 * The first existing preset file wins. The loader must not merge skeleton and
 * framework presets.
 *
 * Diagnostics intentionally do not expose filesystem paths, skeleton root,
 * defaults path, overrides path, raw preset payloads, PHP warning messages, or
 * filesystem layout.
 *
 * @internal
 */
final readonly class FilesystemModePresetLoader implements ModePresetLoaderInterface
{
    private const int MAX_PRESET_NAME_BYTES = 64;

    private const string SAFE_PRESET_NAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789-';

    public function __construct(
        private string $frameworkDefaultsPath,
        private string $skeletonOverridesPath,
        private ModePresetSchemaValidator $schemaValidator,
    ) {
        if (!$this->isSafeInternalDirectoryPath($frameworkDefaultsPath)) {
            throw new \InvalidArgumentException('filesystem-mode-preset-loader-defaults-path-invalid');
        }

        if (!$this->isSafeInternalDirectoryPath($skeletonOverridesPath)) {
            throw new \InvalidArgumentException('filesystem-mode-preset-loader-overrides-path-invalid');
        }
    }

    /**
     * @return list<non-empty-string>
     */
    public function listNames(): array
    {
        $names = [];

        foreach ($this->discoverPresetNames($this->frameworkDefaultsPath) as $name) {
            $names[$name] = true;
        }

        foreach ($this->discoverPresetNames($this->skeletonOverridesPath) as $name) {
            /*
             * Same visible name, but skeleton wins during load/tryLoad/has.
             * listNames() exposes names only, so overriding is represented by
             * a single deterministic entry.
             */
            $names[$name] = true;
        }

        $list = \array_keys($names);

        \usort(
            $list,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $list;
    }

    /**
     * @param non-empty-string $name
     */
    public function has(string $name): bool
    {
        if (!$this->isSafePresetName($name)) {
            return false;
        }

        return $this->findExistingPresetFile($name) !== null;
    }

    /**
     * @param non-empty-string $name
     */
    public function load(string $name): ModePresetInterface
    {
        if (!$this->isSafePresetName($name)) {
            throw ModePresetNotFoundException::invalidPresetName();
        }

        $file = $this->findExistingPresetFile($name);

        if ($file === null) {
            throw ModePresetNotFoundException::forPreset($name);
        }

        return $this->loadExistingPresetFile($name, $file);
    }

    /**
     * @param non-empty-string $name
     */
    public function tryLoad(string $name): ?ModePresetInterface
    {
        if (!$this->isSafePresetName($name)) {
            return null;
        }

        $file = $this->findExistingPresetFile($name);

        if ($file === null) {
            return null;
        }

        return $this->loadExistingPresetFile($name, $file);
    }

    private function findExistingPresetFile(string $name): ?string
    {
        $overrideFile = $this->presetFilePath($this->skeletonOverridesPath, $name);

        if (\is_file($overrideFile)) {
            return $overrideFile;
        }

        $defaultFile = $this->presetFilePath($this->frameworkDefaultsPath, $name);

        if (\is_file($defaultFile)) {
            return $defaultFile;
        }

        return null;
    }

    private function loadExistingPresetFile(string $presetName, string $file): ModePresetInterface
    {
        if (!\is_readable($file)) {
            throw ModePresetInvalidException::forPreset(
                $presetName,
                ModePresetInvalidException::REASON_PRESET_INVALID,
            );
        }

        try {
            $payload = $this->requirePresetFile($file);
        } catch (ModePresetInvalidException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw ModePresetInvalidException::forPreset(
                $presetName,
                ModePresetInvalidException::REASON_PRESET_INVALID,
                $exception,
            );
        }

        return $this->schemaValidator->validate($presetName, $payload);
    }

    private function requirePresetFile(string $file): mixed
    {
        return (static function (string $presetFile): mixed {
            return require $presetFile;
        })(
            $file
        );
    }

    private function presetFilePath(string $directory, string $name): string
    {
        return \rtrim($directory, '/\\') . \DIRECTORY_SEPARATOR . $name . '.php';
    }

    /**
     * @return list<non-empty-string>
     */
    private function discoverPresetNames(string $directory): array
    {
        if (!\is_dir($directory) || !\is_readable($directory)) {
            return [];
        }

        $entries = @\scandir($directory);

        if (!\is_array($entries)) {
            return [];
        }

        $names = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!\str_ends_with($entry, '.php')) {
                continue;
            }

            $name = \substr($entry, 0, -4);

            if (!$this->isSafePresetName($name)) {
                continue;
            }

            $path = $this->presetFilePath($directory, $name);

            if (!\is_file($path)) {
                continue;
            }

            $names[$name] = true;
        }

        $list = \array_keys($names);

        \usort(
            $list,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $list;
    }

    private function isSafePresetName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        if (\strlen($name) > self::MAX_PRESET_NAME_BYTES) {
            return false;
        }

        if (!$this->isAsciiLowerAlpha($name[0])) {
            return false;
        }

        if (\str_contains($name, '..')) {
            return false;
        }

        return \strspn($name, self::SAFE_PRESET_NAME_CHARS) === \strlen($name);
    }

    private function isSafeInternalDirectoryPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (\str_contains($path, "\0")) {
            return false;
        }

        return true;
    }

    private function isAsciiLowerAlpha(string $char): bool
    {
        return $char >= 'a' && $char <= 'z';
    }
}
