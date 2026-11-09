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

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Kernel\Boot\Exception\BootstrapException;

/**
 * Canonical, stateless Bootstrap Phase A source orchestration.
 *
 * Resolves and freezes appEnv and source policy exactly once, validates each
 * permitted source layer before merging, and retains a single environment
 * repository for all target-local consumers of the returned snapshot.
 *
 * @internal
 */
final readonly class BootstrapSourceResolver
{
    private const string ENV_PREFIX = 'CORETSIA_BOOT_';
    private const int MAX_SCALAR_BYTES = 4_096;
    private const int MAX_JSON_BYTES = 65_536;
    private const int MAX_JSON_DEPTH = 64;

    /** @var array<string, string> */
    private const array ENV_KEYS = [
        'CORETSIA_BOOT_APP_ENV' => 'appEnv',
        'CORETSIA_BOOT_ACTIVE_TARGETS' => 'activeTargets',
        'CORETSIA_BOOT_PRESET' => 'preset',
        'CORETSIA_BOOT_PRESETS' => 'presets',
        'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE' => 'include',
        'CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE' => 'exclude',
        'CORETSIA_BOOT_DEBUG' => 'debug',
        'CORETSIA_BOOT_ARTIFACTS_CACHE_DIR' => 'artifactsCacheDir',
    ];

    public function __construct(
        private BootstrapOverridesLoader $overridesLoader,
        private DotenvLoader $dotenvLoader,
        private EnvRepositoryBuilder $envRepositoryBuilder,
    ) {
    }

    /** @param array<string, mixed> $kernelConfig */
    public function resolve(BootstrapInput $input, array $kernelConfig): BootstrapSourceSnapshot
    {
        return $this->resolveApplicationRoot(
            $input->applicationRoot(),
            $kernelConfig,
            $input->appEnv(),
            $input->envSourcePolicy(),
        );
    }

    /**
     * Project-wide resolution without synthesizing an AppTarget or BootstrapInput.
     *
     * @param array<string, mixed> $kernelConfig Canonical kernel config subtree.
     */
    public function resolveApplicationRoot(
        string $applicationRoot,
        array $kernelConfig,
        ?string $explicitAppEnv = null,
        ?BootstrapEnvSourcePolicy $explicitPolicy = null,
    ): BootstrapSourceSnapshot {
        self::assertSafeRoot($applicationRoot);

        $hasInvalidSystemBootstrapEntry = false;

        $system = EnvRepositoryBuilder::snapshotSystemEnv(
            static function (string $name) use (&$hasInvalidSystemBootstrapEntry): void {
                if (\str_starts_with($name, self::ENV_PREFIX)) {
                    $hasInvalidSystemBootstrapEntry = true;
                }
            },
            static function (string $name, string $value) use (&$hasInvalidSystemBootstrapEntry): void {
                if (!\str_starts_with($name, self::ENV_PREFIX)) {
                    return;
                }

                try {
                    self::decodeEnvironmentLayer([$name => $value]);
                } catch (BootstrapException) {
                    $hasInvalidSystemBootstrapEntry = true;
                }
            },
        );
        $phpOverrides = $this->overridesLoader->loadPartialFromApplicationRoot($applicationRoot);
        // Legacy callers may supply a partial kernel config without dotenv file
        // templates. An omitted list means no dotenv layers, not a second set
        // of hard-coded filename defaults.
        $dotenvKernelConfig = $kernelConfig;

        $envConfig = $dotenvKernelConfig['env'] ?? [];

        if (!\is_array($envConfig)) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        $dotenvKernelConfig['env'] = $envConfig;

        if (!\array_key_exists('dotenv', $envConfig)) {
            $dotenvKernelConfig['env']['dotenv'] = ['files' => []];
        } elseif (
            \is_array($envConfig['dotenv'])
            && !\array_key_exists('files', $envConfig['dotenv'])
        ) {
            $dotenvKernelConfig['env']['dotenv']['files'] = [];
        }

        $validateBootstrapEntry = static function (string $name, string $value): void {
            if (\str_starts_with($name, self::ENV_PREFIX)) {
                self::decodeEnvironmentLayer([$name => $value]);
            }
        };

        $baseLayers = $this->dotenvLoader->loadBaseLayers(
            $applicationRoot,
            $dotenvKernelConfig,
            $validateBootstrapEntry,
        );
        $baseOverrides = [];
        $baseSources = [];
        $normalizedBaseLayers = [];

        foreach ($baseLayers as $index => $layer) {
            $normalized = self::decodeEnvironmentLayer($layer['values']);
            $normalizedBaseLayers[] = $normalized;
            self::mergeLayer(
                $baseOverrides,
                $baseSources,
                $normalized,
                self::layerSources($normalized, ConfigSourceType::Dotenv, $layer['fileName'], $index + 1),
            );
        }

        $selectedAppEnv = $explicitAppEnv;
        $appEnvSource = null;

        if ($selectedAppEnv !== null) {
            self::assertSafeAppEnv($selectedAppEnv);
        } elseif (
            $explicitPolicy === BootstrapEnvSourcePolicy::AllowSystem
            && \array_key_exists('CORETSIA_BOOT_APP_ENV', $system['values'])
        ) {
            $selectedAppEnv = self::validatedAppEnv($system['values']['CORETSIA_BOOT_APP_ENV']);
            $appEnvSource = self::sourceFor(ConfigSourceType::Env, 'system_env', 'appEnv', 10);
        } elseif (isset($baseOverrides['appEnv'])) {
            $selectedAppEnv = $baseOverrides['appEnv'];
            $appEnvSource = $baseSources['appEnv'] ?? null;
        } elseif (isset($phpOverrides['appEnv'])) {
            $selectedAppEnv = $phpOverrides['appEnv'];
            $appEnvSource = self::sourceFor(ConfigSourceType::AppConfig, 'config/app.php', 'appEnv', 0);
        } else {
            $selectedAppEnv = self::defaultAppEnv($kernelConfig);
            $appEnvSource = self::sourceFor(ConfigSourceType::PackageDefault, 'kernel/boot', 'appEnv', 0);
        }

        $policy = $explicitPolicy ?? self::defaultPolicy($kernelConfig, $selectedAppEnv);

        if ($policy === BootstrapEnvSourcePolicy::AllowSystem && $hasInvalidSystemBootstrapEntry) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        $baseLayersByFileName = [];

        foreach ($baseLayers as $layer) {
            $baseLayersByFileName[$layer['fileName']] = $layer;
        }

        $environmentLayers = $this->dotenvLoader->loadEnvironmentLayers(
            $applicationRoot,
            $selectedAppEnv,
            $dotenvKernelConfig,
            $baseLayersByFileName,
            $validateBootstrapEntry,
        );

        $overrides = [];
        $provenance = [];
        self::mergeLayer(
            $overrides,
            $provenance,
            $phpOverrides,
            self::layerSources($phpOverrides, ConfigSourceType::AppConfig, 'config/app.php', 0),
        );

        foreach ([...$baseLayers, ...$environmentLayers] as $index => $layer) {
            $normalized = $index < \count($baseLayers)
                ? $normalizedBaseLayers[$index]
                : self::decodeEnvironmentLayer($layer['values']);

            if ($index >= \count($baseLayers) && isset($normalized['appEnv'])) {
                if ($normalized['appEnv'] !== $selectedAppEnv) {
                    throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
                }
            }

            self::mergeLayer(
                $overrides,
                $provenance,
                $normalized,
                self::layerSources($normalized, ConfigSourceType::Dotenv, $layer['fileName'], $index + 1),
            );
        }

        if ($policy === BootstrapEnvSourcePolicy::AllowSystem) {
            $normalized = self::decodeEnvironmentLayer($system['values']);
            self::mergeLayer(
                $overrides,
                $provenance,
                $normalized,
                self::layerSources($normalized, ConfigSourceType::Env, 'system_env', 10),
            );
        }

        if (isset($overrides['presets'])) {
            \ksort($overrides['presets'], \SORT_STRING);
        }

        if (isset($overrides['moduleOverrides'])) {
            \ksort($overrides['moduleOverrides'], \SORT_STRING);
        }

        // appEnv is frozen by the selection stage, not by generic layer order.
        $overrides['appEnv'] = $selectedAppEnv;

        if ($explicitAppEnv !== null) {
            $appEnvSource = self::sourceFor(ConfigSourceType::Cli, 'bootstrap/input', 'appEnv', 11);
        }

        if ($appEnvSource !== null) {
            $provenance['appEnv'] = $appEnvSource;
        }

        $provenance['envSourcePolicy'] = $explicitPolicy !== null
            ? self::sourceFor(ConfigSourceType::Cli, 'bootstrap/input', 'envSourcePolicy', 11)
            : self::sourceFor(ConfigSourceType::PackageDefault, 'kernel/boot', 'envSourcePolicy', 0);

        $dotenv = DotenvLoader::flattenLayers([...$baseLayers, ...$environmentLayers]);
        $repository = $this->envRepositoryBuilder->buildFromSnapshots(
            dotenvValues: $dotenv['values'],
            dotenvSources: $dotenv['sources'],
            systemValues: $system['values'],
            systemSources: $system['sources'],
            policy: $policy,
        );

        \ksort($provenance, \SORT_STRING);

        return new BootstrapSourceSnapshot(
            applicationRoot: $applicationRoot,
            appEnv: $selectedAppEnv,
            envSourcePolicy: $policy,
            overrides: BootstrapOverridesLoader::completeModuleOverrides($overrides),
            sourceProvenance: $provenance,
            envRepository: $repository,
        );
    }

    /**
     * Normalizes one raw dotenv or permitted system environment layer.
     * Unknown keys in the reserved namespace are always rejected.
     *
     * @param array<string, string> $values
     * @return array<string, mixed>
     */
    private static function decodeEnvironmentLayer(array $values): array
    {
        $payload = [];
        $include = null;
        $exclude = null;

        foreach ($values as $name => $value) {
            if (!\str_starts_with($name, self::ENV_PREFIX)) {
                continue;
            }

            if (!isset(self::ENV_KEYS[$name]) || $value === '') {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            $key = self::ENV_KEYS[$name];
            $isJson = \in_array($key, ['activeTargets', 'presets', 'include', 'exclude'], true);

            if (\strlen($value) > ($isJson ? self::MAX_JSON_BYTES : self::MAX_SCALAR_BYTES)) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            try {
                $decoded = match ($key) {
                    'presets' => StableJsonDecoder::decodeStrictStringMap($value),
                    'include', 'exclude' => StableJsonDecoder::decodeStrictStringListMap($value),
                    'activeTargets' => self::decodeJsonList($value),
                    'debug' => self::decodeBoolean($value),
                    default => $value,
                };
            } catch (\InvalidArgumentException|\JsonException) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }

            if ($key === 'include') {
                $include = $decoded;
            } elseif ($key === 'exclude') {
                $exclude = $decoded;
            } else {
                $payload[$key] = $decoded;
            }
        }

        if ($include !== null || $exclude !== null) {
            $moduleOverrides = [];

            foreach ($include ?? [] as $target => $list) {
                $moduleOverrides[$target]['include'] = $list;
            }

            foreach ($exclude ?? [] as $target => $list) {
                $moduleOverrides[$target]['exclude'] = $list;
            }

            if ($moduleOverrides !== []) {
                $payload['moduleOverrides'] = $moduleOverrides;
            }
        }

        // The PHP loader owns all canonical AppTarget, ModuleId and scalar validation.
        return BootstrapOverridesLoader::normalizePartialOverrides($payload);
    }

    /** @return list<mixed> */
    private static function decodeJsonList(string $value): array
    {
        $decoded = \json_decode($value, false, self::MAX_JSON_DEPTH, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded) || !\array_is_list($decoded)) {
            throw new \InvalidArgumentException('bootstrap-active-targets-json-list-required');
        }

        return $decoded;
    }

    private static function decodeBoolean(string $value): bool
    {
        return match ($value) {
            'true', '1' => true,
            'false', '0' => false,
            default => throw new \InvalidArgumentException('bootstrap-debug-boolean-invalid'),
        };
    }

    /**
     * Merges only normalized logical keys; absent fields never erase values.
     * Empty presets and module maps are no-ops. An explicit empty ModuleId list
     * replaces only the corresponding target/field list.
     *
     * @param array<string, mixed> $merged
     * @param array<string, ConfigValueSource> $mergedSources
     * @param array<string, mixed> $layer
     * @param array<string, ConfigValueSource> $layerSources
     */
    private static function mergeLayer(
        array &$merged,
        array &$mergedSources,
        array $layer,
        array $layerSources,
    ): void {
        foreach ($layer as $key => $value) {
            if ($key === 'presets') {
                foreach ($value as $target => $preset) {
                    $merged['presets'][$target] = $preset;
                    $logical = 'presets.' . $target;
                    $mergedSources[$logical] = $layerSources[$logical];
                }
            } elseif ($key === 'moduleOverrides') {
                foreach ($value as $target => $fields) {
                    foreach ($fields as $field => $list) {
                        $merged['moduleOverrides'][$target][$field] = $list;
                        $logical = 'moduleOverrides.' . $target . '.' . $field;
                        $mergedSources[$logical] = $layerSources[$logical];
                    }
                }
            } else {
                $merged[$key] = $value;
                $mergedSources[$key] = $layerSources[$key];
            }
        }
    }

    /**
     * @param array<string, mixed> $layer
     * @return array<string, ConfigValueSource>
     */
    private static function layerSources(
        array $layer,
        ConfigSourceType $type,
        string $name,
        int $precedence,
    ): array {
        $sources = [];

        foreach ($layer as $key => $value) {
            if ($key === 'presets') {
                foreach ($value as $target => $_) {
                    $logical = 'presets.' . $target;
                    $sources[$logical] = self::sourceFor($type, $name, $logical, $precedence);
                }
            } elseif ($key === 'moduleOverrides') {
                foreach ($value as $target => $fields) {
                    foreach ($fields as $field => $_) {
                        $logical = 'moduleOverrides.' . $target . '.' . $field;
                        $sources[$logical] = self::sourceFor($type, $name, $logical, $precedence);
                    }
                }
            } else {
                $sources[$key] = self::sourceFor($type, $name, $key, $precedence);
            }
        }

        return $sources;
    }

    private static function sourceFor(
        ConfigSourceType $type,
        string $name,
        string $logical,
        int $precedence,
    ): ConfigValueSource {
        return new ConfigValueSource(
            type: $type,
            root: $type === ConfigSourceType::AppConfig || $type === ConfigSourceType::PackageDefault ? 'kernel' : 'env',
            sourceId: match ($type) {
                ConfigSourceType::Dotenv => 'dotenv/' . $name,
                ConfigSourceType::AppConfig => 'bootstrap/config_app',
                ConfigSourceType::PackageDefault => 'kernel/boot',
                ConfigSourceType::Cli => 'bootstrap/input',
                default => 'system_env',
            },
            path: $type === ConfigSourceType::AppConfig || $type === ConfigSourceType::Dotenv ? $name : null,
            keyPath: $logical,
            precedence: $precedence,
            redacted: true,
        );
    }

    /** @param array<string, mixed> $kernelConfig */
    private static function defaultAppEnv(array $kernelConfig): string
    {
        $value = $kernelConfig['boot']['default_env'] ?? null;

        if (!\is_string($value)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        self::assertSafeAppEnv($value);

        return $value;
    }

    /** @param array<string, mixed> $kernelConfig */
    private static function defaultPolicy(array $kernelConfig, string $appEnv): BootstrapEnvSourcePolicy
    {
        $kind = \in_array(\strtolower($appEnv), ['prod', 'production'], true)
            ? 'default_production'
            : 'default_local';
        $value = $kernelConfig['env']['source_policy'][$kind] ?? null;

        if (!\is_string($value)) {
            throw BootstrapException::withReason(BootstrapException::REASON_ENV_SOURCE_POLICY_INVALID);
        }

        return BootstrapEnvSourcePolicy::fromString($value);
    }

    private static function validatedAppEnv(string $value): string
    {
        self::assertSafeAppEnv($value);

        return $value;
    }

    private static function assertSafeAppEnv(string $value): void
    {
        if (\strlen($value) > self::MAX_SCALAR_BYTES) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        BootstrapOverridesLoader::normalizePartialOverrides(['appEnv' => $value]);
    }

    private static function assertSafeRoot(string $root): void
    {
        if (
            $root === '' || \trim($root) !== $root || \str_contains($root, "\r")
            || \str_contains($root, "\n")
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $root) === 1
        ) {
            throw BootstrapException::withReason(BootstrapException::REASON_INVALID_APPLICATION_ROOT);
        }
    }
}
