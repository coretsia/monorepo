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

namespace Coretsia\Kernel\Config\Source;

use Composer\InstalledVersions;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;

/**
 * Resolves an exact installed package root without package-directory discovery.
 *
 * @internal
 */
final readonly class ComposerPackageInstallPathResolver
{
    private const int MAX_COMPOSER_NAME_BYTES = 128;
    private const string COMPOSER_NAME_PART_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_.-';

    /**
     * @param array<string,string>|null $installRoots
     */
    public function __construct(
        private ?array $installRoots = null,
    ) {
    }

    public function resolve(string $composerName): string
    {
        if (!self::isValidComposerName($composerName)) {
            throw self::sourceInvalid();
        }

        try {
            $installRoot = $this->installRoots === null
                ? InstalledVersions::getInstallPath($composerName)
                : ($this->installRoots[$composerName] ?? null);
        } catch (\Throwable) {
            throw self::sourceInvalid();
        }

        if (!\is_string($installRoot) || !self::isSafeInstallRoot($installRoot)) {
            throw self::sourceInvalid();
        }

        if (!\is_dir($installRoot)) {
            throw self::sourceInvalid();
        }

        return $installRoot;
    }

    private static function isValidComposerName(string $composerName): bool
    {
        if ($composerName === '' || \strlen($composerName) > self::MAX_COMPOSER_NAME_BYTES) {
            return false;
        }

        if (
            \str_contains($composerName, '..')
            || \str_contains($composerName, '\\')
            || \str_contains($composerName, ':')
            || \str_contains($composerName, "\0")
            || \str_contains($composerName, "\r")
            || \str_contains($composerName, "\n")
        ) {
            return false;
        }

        $parts = \explode('/', $composerName);

        if (\count($parts) !== 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (
                $part === ''
                || \strspn($part, self::COMPOSER_NAME_PART_CHARS) !== \strlen($part)
            ) {
                return false;
            }
        }

        return true;
    }

    private static function isSafeInstallRoot(string $installRoot): bool
    {
        return $installRoot !== ''
            && !\str_contains($installRoot, "\0")
            && !\str_contains($installRoot, "\r")
            && !\str_contains($installRoot, "\n")
            && !\str_contains($installRoot, '://');
    }

    private static function sourceInvalid(): ConfigInvalidException
    {
        return ConfigInvalidException::withReason(
            ConfigInvalidException::REASON_SOURCE_INVALID,
        );
    }
}
