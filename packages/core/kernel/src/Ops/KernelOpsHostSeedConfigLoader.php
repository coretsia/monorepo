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

namespace Coretsia\Kernel\Ops;

use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use Coretsia\Kernel\Module\KernelModule;

/**
 * Loads the exact Foundation and Kernel package seed configuration required to
 * construct the Kernel source operations host.
 *
 * This helper deliberately does not load application, environment, preset, or
 * generated configuration and does not execute ConfigKernel Phase B.
 *
 * @internal Kernel source-operations-host bootstrap helper.
 */
final readonly class KernelOpsHostSeedConfigLoader
{
    private const string FOUNDATION_CONFIG = 'config/foundation.php';
    private const string KERNEL_CONFIG = 'config/kernel.php';

    public function __construct(
        private ComposerPackageInstallPathResolver $installPathResolver,
    ) {
    }

    /**
     * @return array{
     *     foundation: array<string,mixed>,
     *     kernel: array<string,mixed>
     * }
     */
    public function load(): array
    {
        try {
            $foundationRoot = $this->installPathResolver->resolve(FoundationModule::COMPOSER_PACKAGE);
            $kernelRoot = $this->installPathResolver->resolve(KernelModule::COMPOSER_PACKAGE);

            return [
                FoundationModule::CONFIG_ROOT => $this->loadRequiredMap(
                    packageRoot: $foundationRoot,
                    relativePath: self::FOUNDATION_CONFIG,
                ),
                KernelModule::CONFIG_ROOT => $this->loadRequiredMap(
                    packageRoot: $kernelRoot,
                    relativePath: self::KERNEL_CONFIG,
                ),
            ];
        } catch (\Throwable) {
            throw self::loadFailed();
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function loadRequiredMap(
        string $packageRoot,
        string $relativePath,
    ): array {
        if (
            $relativePath !== self::FOUNDATION_CONFIG
            && $relativePath !== self::KERNEL_CONFIG
        ) {
            throw self::loadFailed();
        }

        $configPath = $packageRoot
            . \DIRECTORY_SEPARATOR
            . \str_replace('/', \DIRECTORY_SEPARATOR, $relativePath);

        \set_error_handler(
            static function (
                int $severity,
                string $_message,
                string $_file,
                int $_line,
            ): never {
                throw new \ErrorException(
                    'kernel-ops-host-seed-config-read-failed',
                    0,
                    $severity,
                );
            },
        );

        try {
            if (!\is_file($configPath) || !\is_readable($configPath)) {
                throw self::loadFailed();
            }

            $config = require $configPath;

            if (!\is_array($config) || !self::isStringMap($config)) {
                throw self::loadFailed();
            }

            return $config;
        } catch (\Throwable) {
            throw self::loadFailed();
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private static function isStringMap(array $value): bool
    {
        if ($value !== [] && \array_is_list($value)) {
            return false;
        }

        foreach ($value as $key => $_item) {
            if (!\is_string($key)) {
                return false;
            }
        }

        return true;
    }

    private static function loadFailed(): \RuntimeException
    {
        return new \RuntimeException('kernel-ops-host-seed-config-load-failed');
    }
}
