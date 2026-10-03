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
 * Resolution order:
 *
 * 1. explicit BootstrapInput values;
 * 2. bootstrap-only overrides from config/app.php;
 * 3. package defaults from kernel config.
 *
 * Preset resolution order is intentionally more specific:
 *
 * 1. explicit BootstrapInput::preset();
 * 2. config/app.php presets[BootstrapInput::appTarget()];
 * 3. config/app.php preset;
 * 4. kernel.boot.default_preset.
 *
 * The `presets` map is evaluated only for the already selected explicit app
 * target. It must not select, infer, or modify app target.
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
    private const string KEY_ENV = 'env';

    private const string KEY_DEFAULT_ENV = 'default_env';
    private const string KEY_DEFAULT_PRESET = 'default_preset';
    private const string KEY_DEFAULT_DEBUG = 'default_debug';
    private const string KEY_DEFAULT_ARTIFACTS_CACHE_DIR = 'default_artifacts_cache_dir';

    private const string KEY_SOURCE_POLICY = 'source_policy';
    private const string KEY_DEFAULT_LOCAL = 'default_local';
    private const string KEY_DEFAULT_PRODUCTION = 'default_production';

    private const string OVERRIDE_APP_ENV = 'appEnv';
    private const string OVERRIDE_PRESET = 'preset';
    private const string OVERRIDE_PRESETS = 'presets';
    private const string OVERRIDE_MODULE_OVERRIDES = 'moduleOverrides';
    private const string OVERRIDE_DEBUG = 'debug';
    private const string OVERRIDE_ARTIFACTS_CACHE_DIR = 'artifactsCacheDir';

    /**
     * @var array<string, true>
     */
    private const array PRODUCTION_LIKE_ENVS = [
        'prod' => true,
        'production' => true,
    ];

    public function __construct(
        private BootstrapOverridesLoader $overridesLoader,
        private ModuleIdSetNormalizer $moduleIdSetNormalizer,
    ) {
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
    ): BootstrapConfig {
        $overrides = $this->overridesLoader->load($input);

        $appEnv = $input->appEnv()
            ?? $overrides[self::OVERRIDE_APP_ENV]
            ?? self::defaultAppEnv($kernelConfig);

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

        $envSourcePolicy = $input->envSourcePolicy()
            ?? self::defaultEnvSourcePolicy($kernelConfig, $appEnv);

        return new BootstrapConfig(
            appEnv: $appEnv,
            preset: $preset,
            debug: $debug,
            artifactsCacheDir: $artifactsCacheDir,
            envSourcePolicy: $envSourcePolicy,
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
     * 2. config/app.php presets[BootstrapInput::appTarget()];
     * 3. config/app.php preset;
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
    private static function defaultAppEnv(array $kernelConfig): string
    {
        $value = $kernelConfig[self::KEY_BOOT][self::KEY_DEFAULT_ENV] ?? null;

        if (!\is_string($value) || !self::isNonEmptySafeSingleLineString($value)) {
            throw new \InvalidArgumentException('bootstrap-config-default-env-invalid');
        }

        return $value;
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

    /**
     * @param array<string,mixed> $kernelConfig
     */
    private static function defaultEnvSourcePolicy(
        array $kernelConfig,
        string $appEnv,
    ): BootstrapEnvSourcePolicy {
        $policy = self::isProductionLikeEnv($appEnv)
            ? ($kernelConfig[self::KEY_ENV][self::KEY_SOURCE_POLICY][self::KEY_DEFAULT_PRODUCTION] ?? null)
            : ($kernelConfig[self::KEY_ENV][self::KEY_SOURCE_POLICY][self::KEY_DEFAULT_LOCAL] ?? null);

        if (!\is_string($policy) || !self::isNonEmptySafeSingleLineString($policy)) {
            throw new \InvalidArgumentException('bootstrap-config-default-env-source-policy-invalid');
        }

        return BootstrapEnvSourcePolicy::fromString($policy);
    }

    private static function isProductionLikeEnv(string $appEnv): bool
    {
        return isset(self::PRODUCTION_LIKE_ENVS[\strtolower($appEnv)]);
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
