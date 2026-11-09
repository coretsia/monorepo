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

namespace Coretsia\Kernel\Boot;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ResolvedModuleOverrides;

/**
 * Resolves immutable Bootstrap Phase A configuration.
 *
 * The canonical BootstrapSourceResolver freezes appEnv, env source policy, and
 * source-layer overrides once per resolution attempt. This resolver applies
 * explicit target-local BootstrapInput overrides to that immutable snapshot.
 *
 * Preset resolution order:
 *
 * 1. explicit BootstrapInput::preset();
 * 2. merged presets[BootstrapInput::appTarget()];
 * 3. merged global preset;
 * 4. kernel.boot.default_preset.
 *
 * Project-wide activeTargets never selects a runtime AppTarget.
 *
 * Phase A validates the preset name and canonical per-target module overrides.
 * Preset file existence and schema validation belong to orchestration.
 *
 * This resolver owns BootstrapConfig resolution only. It does not build the env
 * repository, parse dotenv files, read system env, scan apps/<app>, read
 * kernel.modes.*, load resources/modes/*.php, load config/modes/*.php,
 * or require the derived app root to exist.
 *
 * Default env source policy selection:
 *
 * - prod and production are production-like and default to allow_system;
 * - every other appEnv is default-strict and defaults to strict_dotenv;
 * - staging is intentionally default-strict;
 * - non-production envs that need system env precedence must pass explicit
 *   BootstrapEnvSourcePolicy::AllowSystem through BootstrapInput.
 *
 * @internal
 */
final readonly class BootstrapConfigResolver
{
    private const string KEY_BOOT = 'boot';

    private const string KEY_DEFAULT_PRESET = 'default_preset';
    private const string KEY_DEFAULT_DEBUG = 'default_debug';
    private const string KEY_DEFAULT_ARTIFACTS_CACHE_DIR = 'default_artifacts_cache_dir';

    private const string OVERRIDE_PRESET = 'preset';
    private const string OVERRIDE_PRESETS = 'presets';
    private const string OVERRIDE_MODULE_OVERRIDES = 'moduleOverrides';
    private const string OVERRIDE_DEBUG = 'debug';
    private const string OVERRIDE_ARTIFACTS_CACHE_DIR = 'artifactsCacheDir';

    private BootstrapSourceResolver $sourceResolver;

    public function __construct(
        private ModuleIdSetNormalizer $moduleIdSetNormalizer,
        BootstrapSourceResolver $sourceResolver,
    ) {
        $this->sourceResolver = $sourceResolver;
    }

    public function sourceResolver(): BootstrapSourceResolver
    {
        return $this->sourceResolver;
    }

    /**
     * Resolves BootstrapConfig from explicit input, bootstrap-only overrides,
     * and package defaults.
     *
     * The `$kernelConfig` argument is the `kernel` config subtree, not a
     * root-wrapped array.
     *
     * @param array<string,mixed> $kernelConfig
     */
    public function resolve(
        BootstrapInput $input,
        array $kernelConfig,
        ?BootstrapSourceSnapshot $snapshot = null,
    ): BootstrapConfig {
        $snapshot ??= $this->sourceResolver->resolve($input, $kernelConfig);
        $snapshot->assertApplicationRoot($input->applicationRoot());

        if ($input->appEnv() !== null && $input->appEnv() !== $snapshot->appEnv()) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        if (
            $input->envSourcePolicy() !== null
            && $input->envSourcePolicy() !== $snapshot->envSourcePolicy()
        ) {
            throw BootstrapException::withReason(BootstrapException::REASON_ENV_SOURCE_POLICY_INVALID);
        }

        $overrides = $snapshot->overrides();
        $preset = self::resolvePreset(
            input: $input,
            overrides: $overrides,
            kernelConfig: $kernelConfig,
        );

        $debug = $input->debug()
            ?? $overrides[self::OVERRIDE_DEBUG]
            ?? self::defaultDebug($kernelConfig);

        $artifactsCacheDir = $input->artifactsCacheDir()
            ?? $overrides[self::OVERRIDE_ARTIFACTS_CACHE_DIR]
            ?? self::defaultArtifactsCacheDir($kernelConfig);

        return new BootstrapConfig(
            appEnv: $snapshot->appEnv(),
            preset: $preset,
            debug: $debug,
            artifactsCacheDir: $artifactsCacheDir,
            envSourcePolicy: $snapshot->envSourcePolicy(),
            appTarget: $input->appTarget(),
            applicationRoot: $input->applicationRoot(),
            moduleOverrides: $this->resolveModuleOverrides(
                $overrides[self::OVERRIDE_MODULE_OVERRIDES][$input->appTarget()->value] ?? [],
            ),
        );
    }

    /**
     * @param array<string,mixed> $raw
     */
    private function resolveModuleOverrides(array $raw): ResolvedModuleOverrides
    {
        $include = $this->resolveModuleIdList($raw['include'] ?? []);
        $exclude = $this->resolveModuleIdList($raw['exclude'] ?? []);
        $includeIds = \array_fill_keys(\array_map(static fn (ModuleId $id): string => $id->value(), $include), true);
        foreach ($exclude as $id) {
            if (isset($includeIds[$id->value()])) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }
        }
        return new ResolvedModuleOverrides(
            $this->moduleIdSetNormalizer->normalize($include),
            $this->moduleIdSetNormalizer->normalize($exclude),
        );
    }

    /**
     * @param list<string> $raw
     * @return list<ModuleId>
     */
    private function resolveModuleIdList(array $raw): array
    {
        $ids = [];
        $seen = [];
        foreach ($raw as $value) {
            try {
                $id = ModuleId::fromString($value);
                if ($value !== $id->value() || isset($seen[$id->value()])) {
                    throw new \InvalidArgumentException('bootstrap-module-id-invalid');
                }
            } catch (\InvalidArgumentException) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }
            $seen[$id->value()] = true;
            $ids[] = $id;
        }
        return $ids;
    }

    /**
     * Preset resolution order:
     *
     * 1. explicit BootstrapInput::preset();
     * 2. merged presets[BootstrapInput::appTarget()];
     * 3. merged global preset;
     * 4. kernel.boot.default_preset.
     *
     * @param array{
     *     appEnv?: non-empty-string,
     *     preset?: non-empty-string,
     *     presets?: array<string, non-empty-string>,
     *     moduleOverrides?: array<string, array{include:list<string>, exclude:list<string>}>,
     *     debug?: bool,
     *     artifactsCacheDir?: non-empty-string
     * } $overrides
     * @param array<string,mixed> $kernelConfig
     *
     * @return non-empty-string
     */
    private static function resolvePreset(
        BootstrapInput $input,
        array $overrides,
        array $kernelConfig,
    ): string {
        $inputPreset = $input->preset();

        if ($inputPreset !== null) {
            return $inputPreset;
        }

        $appPreset = self::presetOverrideForAppTarget(
            overrides: $overrides,
            appTarget: $input->appTarget()->value,
        );

        if ($appPreset !== null) {
            return $appPreset;
        }

        $globalPreset = $overrides[self::OVERRIDE_PRESET] ?? null;

        if (\is_string($globalPreset) && self::isNonEmptySafeSingleLineString($globalPreset)) {
            return $globalPreset;
        }

        return self::defaultPreset($kernelConfig);
    }

    /**
     * @param array{
     *     appEnv?: non-empty-string,
     *     preset?: non-empty-string,
     *     presets?: array<string, non-empty-string>,
     *     moduleOverrides?: array<string, array{include:list<string>, exclude:list<string>}>,
     *     debug?: bool,
     *     artifactsCacheDir?: non-empty-string
     * } $overrides
     *
     * @return non-empty-string|null
     */
    private static function presetOverrideForAppTarget(
        array $overrides,
        string $appTarget,
    ): ?string {
        $presets = $overrides[self::OVERRIDE_PRESETS] ?? null;

        if ($presets === null) {
            return null;
        }

        if (!\is_array($presets)) {
            throw new \InvalidArgumentException('bootstrap-config-presets-override-invalid');
        }

        $preset = $presets[$appTarget] ?? null;

        if ($preset === null) {
            return null;
        }

        if (!\is_string($preset) || !self::isNonEmptySafeSingleLineString($preset)) {
            throw new \InvalidArgumentException('bootstrap-config-presets-override-invalid');
        }

        return $preset;
    }

    /**
     * @param array<string,mixed> $kernelConfig
     *
     * @return non-empty-string
     */
    private static function defaultPreset(array $kernelConfig): string
    {
        $value = $kernelConfig[self::KEY_BOOT][self::KEY_DEFAULT_PRESET] ?? null;

        if (!\is_string($value) || !self::isNonEmptySafeSingleLineString($value)) {
            throw new \InvalidArgumentException('bootstrap-config-default-preset-invalid');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $kernelConfig
     */
    private static function defaultDebug(array $kernelConfig): bool
    {
        $value = $kernelConfig[self::KEY_BOOT][self::KEY_DEFAULT_DEBUG] ?? null;

        if (!\is_bool($value)) {
            throw new \InvalidArgumentException('bootstrap-config-default-debug-invalid');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $kernelConfig
     *
     * @return non-empty-string
     */
    private static function defaultArtifactsCacheDir(array $kernelConfig): string
    {
        $value = $kernelConfig[self::KEY_BOOT][self::KEY_DEFAULT_ARTIFACTS_CACHE_DIR] ?? null;

        if (!BootstrapArtifactsCacheDir::isValid($value)) {
            throw new \InvalidArgumentException('bootstrap-config-default-artifacts-cache-dir-invalid');
        }

        /** @var non-empty-string $value */
        return $value;
    }

    private static function isNonEmptySafeSingleLineString(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n")
            && \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
