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

use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModuleResolution;

/**
 * Builds the canonical deterministic config-source set for one
 * already-resolved module snapshot.
 *
 * @internal
 */
final readonly class ConfigSourceLocationBuilder
{
    private const string DEFAULTS_PATH_PATTERN = '/\Aconfig\/([a-z][a-z0-9_]*)\.php\z/D';
    private const string RESERVED_AGGREGATE_ROOT = 'roots';

    public function __construct(
        private ComposerPackageInstallPathResolver $installPathResolver,
        private ModePresetLoaderFactory $modePresetLoaderFactory,
    ) {
    }

    public function build(
        BootstrapConfig $bootstrapConfig,
        ModuleResolution $moduleResolution,
    ): ConfigSourceSet {
        $manifest = $moduleResolution->manifest();
        $plan = $moduleResolution->plan();
        $planEntries = $plan->modules();

        $packageDefaultSources = [];
        $packageRuleSources = [];
        $splitRootSet = [];

        foreach ($plan->topologicalOrder() as $moduleId) {
            $moduleIdValue = $moduleId->value();
            $planEntry = $planEntries[$moduleIdValue] ?? null;
            $descriptor = $manifest->get($moduleIdValue);

            if ($planEntry === null || $descriptor === null) {
                throw self::sourceInvalid();
            }

            $composerName = $descriptor->composerName();

            if ($composerName === null || $composerName !== $planEntry->composerName()) {
                throw self::sourceInvalid();
            }

            $metadata = $descriptor->metadata();
            $defaultsConfigPath = $metadata['defaultsConfigPath'] ?? null;

            if ($defaultsConfigPath === null) {
                continue;
            }

            if (!\is_string($defaultsConfigPath)) {
                throw self::sourceInvalid();
            }

            $root = self::rootFromDefaultsConfigPath($defaultsConfigPath);

            if (isset($splitRootSet[$root])) {
                throw self::sourceInvalid();
            }

            $packageId = $moduleId->layer() . '/' . $moduleId->slug();
            $installRoot = $this->installPathResolver->resolve($composerName);

            $packageDefaultSources[] = [
                'root' => $root,
                'packageId' => $packageId,
                'moduleId' => $moduleIdValue,
                'path' => $defaultsConfigPath,
                'filesystemPath' => self::joinFilesystemPath(
                    $installRoot,
                    $defaultsConfigPath,
                ),
                'sourceId' => $packageId . '/config/defaults/' . $root,
            ];

            $packageRuleSources[] = [
                'root' => $root,
                'packageId' => $packageId,
                'moduleId' => $moduleIdValue,
                'path' => 'config/rules.php',
                'filesystemPath' => self::joinFilesystemPath(
                    $installRoot,
                    'config/rules.php',
                ),
                'sourceId' => $packageId . '/config/rules/' . $root,
            ];

            $splitRootSet[$root] = true;
        }

        $splitRoots = \array_keys($splitRootSet);
        \usort(
            $splitRoots,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return new ConfigSourceSet(
            packageDefaultSources: $packageDefaultSources,
            packageRuleSources: $packageRuleSources,
            splitRoots: $splitRoots,
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: $this->modePresetLoaderFactory->sourceCandidatesFor($bootstrapConfig),
        );
    }

    private static function rootFromDefaultsConfigPath(string $defaultsConfigPath): string
    {
        $matches = [];

        if (\preg_match(self::DEFAULTS_PATH_PATTERN, $defaultsConfigPath, $matches) !== 1) {
            throw self::sourceInvalid();
        }

        $root = $matches[1];

        if ($root === self::RESERVED_AGGREGATE_ROOT) {
            throw self::sourceInvalid();
        }

        return $root;
    }

    private static function joinFilesystemPath(
        string $root,
        string $relativePath,
    ): string {
        $trimmedRoot = \rtrim($root, '/\\');
        $relativePath = \str_replace(
            '/',
            \DIRECTORY_SEPARATOR,
            $relativePath,
        );

        if ($trimmedRoot === '') {
            return \DIRECTORY_SEPARATOR . $relativePath;
        }

        return $trimmedRoot
            . \DIRECTORY_SEPARATOR
            . $relativePath;
    }

    private static function sourceInvalid(): ConfigInvalidException
    {
        return ConfigInvalidException::withReason(
            ConfigInvalidException::REASON_SOURCE_INVALID,
        );
    }
}
