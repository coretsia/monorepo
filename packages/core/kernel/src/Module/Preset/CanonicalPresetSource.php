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

namespace Coretsia\Kernel\Module\Preset;

use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;

/**
 * Namespace-bound filesystem source.
 *
 * @internal
 */
final readonly class CanonicalPresetSource implements PresetSourceInterface
{
    /**
     * $directory is already validated and bound to its owning application/package root.
     */
    public function __construct(
        private string $directory,
        private string $logicalPath,
        private string $owningRoot,
    ) {
        if ($directory === '' || \str_contains($directory, "\0") || \str_contains($directory, '://')) {
            throw new \InvalidArgumentException('mode-preset-source-directory-invalid');
        }
    }

    private function owns(string $name): bool
    {
        return \preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $name) === 1 && CanonicalPresetNames::contains($name);
    }

    public function listNames(): array
    {
        $this->assertSourceBoundary('invalid');

        if (!\is_dir($this->directory)) {
            return [];
        }
        $entries = @\scandir($this->directory);
        if (!\is_array($entries)) {
            return [];
        }
        $names = [];
        foreach ($entries as $entry) {
            if (!\str_ends_with($entry, '.php')) {
                continue;
            }
            $name = \substr($entry, 0, -4);
            if (!$this->owns($name) || !$this->has($name)) {
                continue;
            }
            $names[$name] = true;
        }
        $names = \array_keys($names);
        \usort($names, static fn (string $a, string $b): int => \strcmp($a, $b));
        return $names;
    }

    public function has(string $name): bool
    {
        if (!$this->owns($name)) {
            return false;
        }
        try {
            return $this->resolveFile($name) !== null;
        } catch (ModePresetInvalidException) {
            return false;
        }
    }

    public function resolveFile(string $name): ?string
    {
        if (!$this->owns($name)) {
            return null;
        }
        $this->assertSourceBoundary($name);

        $file = \rtrim($this->directory, '/\\') . \DIRECTORY_SEPARATOR . $name . '.php';
        if (!\file_exists($file) && !\is_link($file)) {
            return null;
        }
        $root = \realpath($this->directory);
        $target = \realpath($file);
        if (
            $root === false
            || $target === false
            || !\is_file($target)
            || !\str_starts_with($target, \rtrim($root, '/\\') . \DIRECTORY_SEPARATOR)
        ) {
            throw ModePresetInvalidException::forPreset($name, ModePresetInvalidException::REASON_PRESET_INVALID);
        }
        return $target;
    }

    private function assertSourceBoundary(string $name): void
    {
        $root = $this->owningRoot;
        $prefix = \rtrim($root, '/\\') . \DIRECTORY_SEPARATOR;

        if (
            \realpath($root) !== $root
            || (
                $this->directory !== $root
                && !\str_starts_with($this->directory, $prefix)
            )
        ) {
            throw ModePresetInvalidException::forPreset(
                $name,
                ModePresetInvalidException::REASON_PRESET_INVALID,
            );
        }

        $relativePath = $this->directory === $root
            ? ''
            : \substr($this->directory, \strlen($prefix));

        $current = $root;

        if ($relativePath === '') {
            return;
        }

        foreach (\explode(\DIRECTORY_SEPARATOR, $relativePath) as $segment) {
            $current = \rtrim($current, '/\\') . \DIRECTORY_SEPARATOR . $segment;

            if (!\file_exists($current) && !\is_link($current)) {
                continue;
            }

            $resolved = \realpath($current);

            if (
                $resolved === false
                || !\is_dir($resolved)
                || (
                    $resolved !== $root
                    && !\str_starts_with($resolved, $prefix)
                )
            ) {
                throw ModePresetInvalidException::forPreset(
                    $name,
                    ModePresetInvalidException::REASON_PRESET_INVALID,
                );
            }

            $current = $resolved;
        }
    }

    public function sourceCandidate(string $name): array
    {
        if (!$this->owns($name)) {
            throw new \InvalidArgumentException('mode-preset-source-name-invalid');
        }
        // An existing invalid source must fail before fingerprinting reads it.
        $this->resolveFile($name);
        $path = $this->logicalPath . '/' . $name . '.php';
        return [
            'path' => $path,
            'filesystemPath' => \rtrim($this->directory, '/\\') . \DIRECTORY_SEPARATOR . $name . '.php',
            'sourceId' => 'core/kernel:' . $path,
            'precedence' => 10,
        ];
    }
}
