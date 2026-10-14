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

namespace Coretsia\Platform\Cli\Bootstrap;

use Coretsia\Platform\Cli\Exception\CliBootstrapException;

/**
 * Resolves exactly one canonical application-root source after autoload.
 *
 * An explicit root belongs to canonical Coretsia wrappers. Otherwise the
 * Composer binary proxy supplies vendor/bin, from which the application root
 * is derived without recursive search, current-working-directory inference,
 * or reading application configuration.
 *
 * @internal
 */
final class CliEntrypointPathsResolver
{
    public function resolve(
        string $launcherFile,
        string $autoloadPath,
        ?string $composerBinDir,
        ?string $explicitApplicationRoot,
    ): CliEntrypointPaths {
        if ($composerBinDir !== null && $explicitApplicationRoot !== null) {
            throw self::invalid(CliBootstrapException::REASON_APPLICATION_ROOT_CONFLICT);
        }

        if (\basename(\str_replace('\\', '/', $launcherFile)) !== 'coretsia') {
            throw self::invalid(CliBootstrapException::REASON_LAUNCHER_INVALID);
        }

        $launcher = self::file($launcherFile, CliBootstrapException::REASON_LAUNCHER_INVALID);
        $autoload = self::file($autoloadPath, CliBootstrapException::REASON_AUTOLOAD_INVALID);

        if ($explicitApplicationRoot !== null) {
            $applicationRoot = self::directory(
                $explicitApplicationRoot,
                CliBootstrapException::REASON_APPLICATION_ROOT_UNRESOLVED,
            );

            if (!self::matchesLauncher($launcher, [
                $applicationRoot . '/coretsia',
                $applicationRoot . '/packages/platform/cli/bin/coretsia',
            ])) {
                throw self::invalid(CliBootstrapException::REASON_LAUNCHER_INVALID);
            }
        } elseif ($composerBinDir !== null) {
            self::directory(
                $composerBinDir,
                CliBootstrapException::REASON_APPLICATION_ROOT_UNRESOLVED,
            );
            $binDir = self::normalized($composerBinDir);

            if (\basename($binDir) !== 'bin' || \basename(\dirname($binDir)) !== 'vendor') {
                throw self::invalid(CliBootstrapException::REASON_APPLICATION_ROOT_UNRESOLVED);
            }

            $applicationRoot = self::directory(
                \dirname(\dirname($binDir)),
                CliBootstrapException::REASON_APPLICATION_ROOT_UNRESOLVED,
            );

            // Composer proxy must use the installed Coretsia executable.
            if (!self::matchesLauncher($launcher, [
                $binDir . '/coretsia',
                $applicationRoot . '/vendor/coretsia/platform-cli/bin/coretsia',
            ])) {
                throw self::invalid(CliBootstrapException::REASON_LAUNCHER_INVALID);
            }
        } else {
            throw self::invalid(CliBootstrapException::REASON_APPLICATION_ROOT_UNRESOLVED);
        }

        $expectedAutoload = self::file(
            \rtrim($applicationRoot, '/') . '/vendor/autoload.php',
            CliBootstrapException::REASON_AUTOLOAD_INVALID,
        );

        if ($autoload !== $expectedAutoload) {
            throw self::invalid(CliBootstrapException::REASON_APPLICATION_ROOT_CONFLICT);
        }

        return new CliEntrypointPaths(
            autoloadPath: $autoload,
            applicationRoot: $applicationRoot,
        );
    }

    private static function file(string $path, string $reason): string
    {
        if (!self::isAbsoluteSafePath($path) || !\is_file($path) || !\is_readable($path)) {
            throw self::invalid($reason);
        }

        $resolved = \realpath($path);

        if ($resolved === false) {
            throw self::invalid($reason);
        }

        return self::normalized($resolved);
    }

    private static function directory(string $path, string $reason): string
    {
        if (!self::isAbsoluteSafePath($path) || !\is_dir($path) || !\is_readable($path)) {
            throw self::invalid($reason);
        }

        $resolved = \realpath($path);

        if ($resolved === false) {
            throw self::invalid($reason);
        }

        return self::normalized($resolved);
    }

    /**
     * @param list<string> $candidates
     */
    private static function matchesLauncher(string $launcher, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            $resolved = \realpath($candidate);

            if ($resolved !== false && \is_file($resolved) && $launcher === self::normalized($resolved)) {
                return true;
            }
        }

        return false;
    }

    private static function isAbsoluteSafePath(string $path): bool
    {
        if ($path === '' || \trim($path) !== $path || \preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return false;
        }

        return \str_starts_with($path, '/')
            || \preg_match('~\A[A-Za-z]:[\\\\/]~', $path) === 1
            || \str_starts_with($path, '\\\\');
    }

    private static function normalized(string $path): string
    {
        $normalized = \str_replace('\\', '/', $path);

        if ($normalized === '/' || \preg_match('~\A[A-Za-z]:/\z~', $normalized) === 1) {
            return $normalized;
        }

        return \rtrim($normalized, '/');
    }

    private static function invalid(string $reason): CliBootstrapException
    {
        return CliBootstrapException::withReason($reason);
    }
}
