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
use Coretsia\Contracts\Env\EnvRepositoryInterface;

/**
 * Builds immutable Kernel Bootstrap Phase A env repository snapshots.
 *
 * EnvRepositoryBuilder consumes already resolved BootstrapConfig. It does not
 * resolve BootstrapConfig, read BootstrapInput optional values, read
 * config/app.php, apply package boot defaults, or use
 * Coretsia\Contracts\Env\EnvPolicy.
 *
 * BootstrapEnvSourcePolicy controls dotenv/system source precedence:
 *
 * - strict_dotenv: dotenv values only; system env fallback is forbidden;
 * - allow_system: system env wins; dotenv fills only missing system keys.
 *
 * System env is snapshotted once before precedence is applied. After the
 * repository is constructed, no further reads from $_ENV, $_SERVER, or getenv()
 * are performed by the repository.
 *
 * Raw env values are never embedded in diagnostics.
 *
 * @internal
 */
final readonly class EnvRepositoryBuilder
{
    private const int SOURCE_PRECEDENCE_SYSTEM_ENV = 10;

    public function __construct(
        private DotenvLoader $dotenvLoader,
    ) {
    }

    /**
     * Builds an immutable env repository snapshot.
     *
     * The `$kernelConfig` argument is the `kernel` config subtree, not a
     * root-wrapped array.
     *
     * @param array<string,mixed> $kernelConfig
     */
    public function build(
        BootstrapConfig $config,
        array $kernelConfig,
    ): EnvRepositoryInterface {
        $dotenv = $this->dotenvLoader->load($config, $kernelConfig);
        $system = self::snapshotSystemEnv();

        return $this->buildFromSnapshots(
            dotenvValues: $dotenv['values'],
            dotenvSources: $dotenv['sources'],
            systemValues: $system['values'],
            systemSources: $system['sources'],
            policy: $config->envSourcePolicy(),
        );
    }

    /**
     * Builds one immutable repository from a previously captured source snapshot.
     * Callers retain this object in their resolution-local BootstrapSourceSnapshot.
     *
     * @param array<string,string> $dotenvValues
     * @param array<string,ConfigValueSource> $dotenvSources
     * @param array<string,string> $systemValues
     * @param array<string,ConfigValueSource> $systemSources
     */
    public function buildFromSnapshots(
        array $dotenvValues,
        array $dotenvSources,
        array $systemValues,
        array $systemSources,
        BootstrapEnvSourcePolicy $policy,
    ): EnvRepositoryInterface {
        if ($policy === BootstrapEnvSourcePolicy::StrictDotenv) {
            return new ArrayEnvRepository(
                values: $dotenvValues,
                sources: $dotenvSources,
            );
        }

        return new ArrayEnvRepository(
            values: self::mergeAllowSystemValues($dotenvValues, $systemValues),
            sources: self::mergeAllowSystemSources($dotenvSources, $systemSources),
        );
    }

    /**
     * Snapshots system/process env exactly once for this build operation.
     *
     * @param null|callable(string,string):void $validateEntry
     *
     * @return array{
     *     values: array<string,string>,
     *     sources: array<string,ConfigValueSource>
     * }
     */
    public static function snapshotSystemEnv(
        ?callable $onInvalidName = null,
        ?callable $validateEntry = null,
    ): array {
        $values = [];

        self::mergeSystemEnvMap($values, $_ENV, $onInvalidName, $validateEntry);
        self::mergeSystemEnvMap($values, $_SERVER, $onInvalidName, $validateEntry);

        $getenv = \getenv();

        if (\is_array($getenv)) {
            self::mergeSystemEnvMap($values, $getenv, $onInvalidName, $validateEntry);
        }

        \ksort($values, \SORT_STRING);

        $sources = [];

        foreach (\array_keys($values) as $name) {
            $sources[$name] = self::systemSourceFor($name);
        }

        \ksort($sources, \SORT_STRING);

        return [
            'values' => $values,
            'sources' => $sources,
        ];
    }

    /**
     * @param array<string,string> $target
     * @param array<mixed> $source
     * @param null|callable(string,string):void $validateEntry
     */
    private static function mergeSystemEnvMap(
        array &$target,
        array $source,
        ?callable $onInvalidName = null,
        ?callable $validateEntry = null,
    ): void {
        foreach ($source as $name => $value) {
            if (!\is_string($name)) {
                continue;
            }

            if (!self::isSafeEnvName($name)) {
                if ($onInvalidName !== null) {
                    $onInvalidName($name);
                }

                continue;
            }

            if (!\is_string($value)) {
                if ($onInvalidName !== null) {
                    $onInvalidName($name);
                }

                continue;
            }

            if ($validateEntry !== null) {
                $validateEntry($name, $value);
            }

            $target[$name] = $value;
        }
    }

    private static function isSafeEnvName(string $name): bool
    {
        return $name !== '' && \preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $name) === 1;
    }

    /**
     * @param array<string,string> $dotenvValues
     * @param array<string,string> $systemValues
     *
     * @return array<string,string>
     */
    private static function mergeAllowSystemValues(
        array $dotenvValues,
        array $systemValues,
    ): array {
        $values = $dotenvValues;

        foreach ($systemValues as $name => $value) {
            $values[$name] = $value;
        }

        \ksort($values, \SORT_STRING);

        return $values;
    }

    /**
     * @param array<string,ConfigValueSource> $dotenvSources
     * @param array<string,ConfigValueSource> $systemSources
     *
     * @return array<string,ConfigValueSource>
     */
    private static function mergeAllowSystemSources(
        array $dotenvSources,
        array $systemSources,
    ): array {
        $sources = $dotenvSources;

        foreach ($systemSources as $name => $source) {
            $sources[$name] = $source;
        }

        \ksort($sources, \SORT_STRING);

        return $sources;
    }

    private static function systemSourceFor(string $name): ConfigValueSource
    {
        return new ConfigValueSource(
            type: ConfigSourceType::Env,
            root: 'env',
            sourceId: 'system_env',
            keyPath: $name,
            precedence: self::SOURCE_PRECEDENCE_SYSTEM_ENV,
            redacted: true,
            meta: [
                'source' => 'system_env',
            ],
        );
    }
}
