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

/**
 * Resolves immutable Bootstrap Phase A configuration.
 *
 * Resolution order:
 *
 * 1. explicit BootstrapInput values;
 * 2. bootstrap-only overrides from skeleton/config/app.php;
 * 3. package defaults from kernel config.
 *
 * This resolver owns BootstrapConfig resolution only. It does not build the env
 * repository, parse dotenv files, read system env, scan skeleton/apps/*, or
 * require the derived app root to exist.
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

    private const string KEY_SOURCE_POLICY = 'source_policy';
    private const string KEY_DEFAULT_LOCAL = 'default_local';
    private const string KEY_DEFAULT_PRODUCTION = 'default_production';

    private const string OVERRIDE_APP_ENV = 'appEnv';
    private const string OVERRIDE_PRESET = 'preset';
    private const string OVERRIDE_DEBUG = 'debug';

    /**
     * @var array<string, true>
     */
    private const array PRODUCTION_LIKE_ENVS = [
        'prod' => true,
        'production' => true,
    ];

    public function __construct(
        private BootstrapOverridesLoader $overridesLoader,
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

        $preset = $input->preset()
            ?? $overrides[self::OVERRIDE_PRESET]
            ?? self::defaultPreset($kernelConfig);

        $debug = $input->debug()
            ?? $overrides[self::OVERRIDE_DEBUG]
            ?? self::defaultDebug($kernelConfig);

        $envSourcePolicy = $input->envSourcePolicy()
            ?? self::defaultEnvSourcePolicy($kernelConfig, $appEnv);

        return new BootstrapConfig(
            appEnv: $appEnv,
            preset: $preset,
            debug: $debug,
            envSourcePolicy: $envSourcePolicy,
            appTarget: $input->appTarget(),
            skeletonRoot: $input->skeletonRoot(),
        );
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
