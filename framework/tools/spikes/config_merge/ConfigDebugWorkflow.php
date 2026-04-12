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

namespace Coretsia\Tools\Spikes\config_merge;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

/**
 * Tools-only workflow for:
 * - loading config_merge scenario fixtures
 * - running merge
 * - building safe explain trace for a requested key
 *
 * Contract:
 * - returns structured result only
 * - deterministic failures are surfaced as DeterministicException
 * - no stdout/stderr
 * - no absolute paths
 */
final class ConfigDebugWorkflow
{
    private const int OUTPUT_SCHEMA_VERSION = 1;

    private function __construct()
    {
    }

    /**
     * @return array{
     *   schema_version:int,
     *   scenario:string,
     *   key:string,
     *   resolved: array{
     *     present: bool,
     *     kind: 'missing'|'null'|'scalar'|'list'|'map',
     *     meta: array<string, int|string|bool>
     *   },
     *   trace: list<array{sourceType: string, file: string, keyPath: string, directiveApplied: bool}>
     * }
     *
     * @throws DeterministicException
     */
    public static function run(string $scenarioId, string $key): array
    {
        try {
            $scenarios = self::loadScenariosFixture();

            $scenario = $scenarios['scenarios'][$scenarioId] ?? null;
            if (!\is_array($scenario)) {
                self::failFixture('unknown-scenario-id');
            }

            $inputs = $scenario['inputs'] ?? null;
            if (!\is_array($inputs)) {
                self::failFixture('scenario-inputs-missing');
            }

            foreach (ConfigMerger::PRECEDENCE as $layer) {
                if (!\array_key_exists($layer, $inputs) || !\is_array($inputs[$layer])) {
                    self::failFixture('scenario-inputs-shape-invalid');
                }
            }

            /** @var array{defaults: array, skeleton: array, module: array, app: array} $inputs */
            $merger = new ConfigMerger();
            $merged = $merger->mergeScenario($inputs);

            $sources = self::buildSourcesFromScenarioInputs($inputs, $scenarioId);

            $explainer = new ConfigExplainer();
            $traceAll = $explainer->explain($sources);
            $trace = self::filterTraceByKey($traceAll, $key);

            return [
                'schema_version' => self::OUTPUT_SCHEMA_VERSION,
                'scenario' => $scenarioId,
                'key' => $key,
                'resolved' => self::describeResolvedValue($merged, $key),
                'trace' => $trace,
            ];
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();

            if (\is_string($message) && $message !== '' && ErrorCodes::has($message)) {
                throw new DeterministicException($message, $message, $e);
            }

            throw $e;
        }
    }

    /**
     * @return array{schema_version:int, precedence:list<string>, scenarios: array<string, array>}
     *
     * @throws DeterministicException
     */
    private static function loadScenariosFixture(): array
    {
        $fixtureAbs = __DIR__ . '/tests/fixtures/scenarios.php';

        if (!\is_file($fixtureAbs) || !\is_readable($fixtureAbs)) {
            self::failFixture('config-merge-scenarios-missing');
        }

        $data = require $fixtureAbs;

        if (!\is_array($data)) {
            self::failFixture('config-merge-scenarios-invalid');
        }

        $schemaVersion = $data['schema_version'] ?? null;
        $precedence = $data['precedence'] ?? null;
        $scenarios = $data['scenarios'] ?? null;

        if (!\is_int($schemaVersion) || $schemaVersion < 1) {
            self::failFixture('config-merge-scenarios-invalid');
        }

        if (!\is_array($precedence) || !\is_array($scenarios)) {
            self::failFixture('config-merge-scenarios-invalid');
        }

        if ($precedence !== ConfigMerger::PRECEDENCE) {
            self::failFixture('config-merge-scenarios-invalid');
        }

        /** @var array{schema_version:int, precedence:list<string>, scenarios: array<string, array>} $data */
        return $data;
    }

    /**
     * @param array{defaults: array, skeleton: array, module: array, app: array} $inputs
     * @return list<array{sourceType: string, file: string, config: array}>
     */
    private static function buildSourcesFromScenarioInputs(array $inputs, string $scenarioId): array
    {
        return [
            [
                'sourceType' => 'defaults',
                'file' => 'scenario:' . $scenarioId . ':defaults',
                'config' => $inputs['defaults'],
            ],
            [
                'sourceType' => 'skeleton',
                'file' => 'scenario:' . $scenarioId . ':skeleton',
                'config' => $inputs['skeleton'],
            ],
            [
                'sourceType' => 'module',
                'file' => 'scenario:' . $scenarioId . ':module',
                'config' => $inputs['module'],
            ],
            [
                'sourceType' => 'app',
                'file' => 'scenario:' . $scenarioId . ':app',
                'config' => $inputs['app'],
            ],
        ];
    }

    /**
     * @param list<array{sourceType: string, file: string, keyPath: string, directiveApplied: bool}> $records
     * @return list<array{sourceType: string, file: string, keyPath: string, directiveApplied: bool}>
     */
    private static function filterTraceByKey(array $records, string $key): array
    {
        $prefix = $key . '.';

        $out = [];
        foreach ($records as $record) {
            $path = $record['keyPath'] ?? null;
            if (!\is_string($path) || $path === '') {
                continue;
            }

            if ($path === $key || \str_starts_with($path, $prefix)) {
                $out[] = $record;
            }
        }

        /** @var list<array{sourceType: string, file: string, keyPath: string, directiveApplied: bool}> $out */
        return $out;
    }

    /**
     * @param array<string, mixed> $merged
     * @return array{
     *   present: bool,
     *   kind: 'missing'|'null'|'scalar'|'list'|'map',
     *   meta: array<string, int|string|bool>
     * }
     */
    private static function describeResolvedValue(array $merged, string $key): array
    {
        $present = false;
        $value = null;

        if (\array_key_exists($key, $merged)) {
            $present = true;
            $value = $merged[$key];
        } else {
            $resolved = self::tryResolveNestedDotPath($merged, $key);
            $present = $resolved['present'];
            $value = $resolved['value'];
        }

        if (!$present) {
            return [
                'present' => false,
                'kind' => 'missing',
                'meta' => [],
            ];
        }

        if ($value === null) {
            return [
                'present' => true,
                'kind' => 'null',
                'meta' => [],
            ];
        }

        if (!\is_array($value)) {
            $meta = [
                'type' => \gettype($value),
            ];

            if (\is_string($value)) {
                $meta['length'] = \strlen($value);
            }

            return [
                'present' => true,
                'kind' => 'scalar',
                'meta' => $meta,
            ];
        }

        if ($value === []) {
            return [
                'present' => true,
                'kind' => 'list',
                'meta' => [
                    'count' => 0,
                ],
            ];
        }

        if (\array_is_list($value)) {
            return [
                'present' => true,
                'kind' => 'list',
                'meta' => [
                    'count' => \count($value),
                ],
            ];
        }

        return [
            'present' => true,
            'kind' => 'map',
            'meta' => [
                'count' => \count($value),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $root
     * @return array{present: bool, value: mixed}
     */
    private static function tryResolveNestedDotPath(array $root, string $key): array
    {
        $parts = \explode('.', $key);
        if ($parts === []) {
            return ['present' => false, 'value' => null];
        }

        $node = $root;

        foreach ($parts as $part) {
            if ($part === '') {
                return ['present' => false, 'value' => null];
            }

            if (!\is_array($node) || $node === [] || \array_is_list($node)) {
                return ['present' => false, 'value' => null];
            }

            if (!\array_key_exists($part, $node)) {
                return ['present' => false, 'value' => null];
            }

            $node = $node[$part];
        }

        return ['present' => true, 'value' => $node];
    }

    /**
     * @throws DeterministicException
     */
    private static function failFixture(string $message): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID,
            $message
        );
    }
}
