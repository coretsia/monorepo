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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpikeConfigExplainTraceCompatibilityContractTest extends TestCase
{
    /**
     * @param array{
     *     title: string,
     *     inputs: array{defaults: array<string,mixed>, skeleton: array<string,mixed>, module: array<string,mixed>, app: array<string,mixed>},
     *     expect: array{merged: array<string,mixed>},
     *     notes?: string
     * } $scenario
     */
    #[DataProvider('successfulSpikeScenarioProvider')]
    public function testExplainTraceCoversMergedSpikeScenarioPaths(
        string $scenarioId,
        array $scenario,
    ): void {
        $flatMerged = self::mergeScenario($scenario);
        $config = self::expandDotPaths($flatMerged);
        $sources = self::sourcesForFlatMerged($flatMerged);

        $explain = new ConfigExplainer()->explain(
            config: $config,
            sources: $sources,
            validationSubjects: [
                'validated' => [],
                'unvalidated' => [
                    [
                        'ownership' => 'user_owned',
                        'root' => 'http',
                        'validation' => 'unvalidated',
                    ],
                ],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [],
        );

        self::assertSame(1, $explain['schemaVersion']);
        self::assertContains('package_default', $explain['sourceTypes']);
        self::assertNotContains('unknown', $explain['sourceTypes']);

        foreach ($explain['sourceTypes'] as $sourceType) {
            self::assertContains(
                $sourceType,
                [
                    'package_default',
                    'skeleton_config',
                    'app_config',
                ],
            );
        }
        self::assertSame(
            'split root-subtree sources have higher same-layer precedence than aggregate root-map sources',
            $explain['precedence']['aggregateVsRoot'],
        );
        self::assertSame(
            'env overlay wins only for config paths that have a ruleset-derived or explicit env overlay mapping and a present env value',
            $explain['precedence']['envOverlay'],
        );

        $explainedPaths = \array_column($explain['paths'], 'path');

        self::assertContains('http', $explainedPaths, $scenarioId);

        foreach (\array_keys($flatMerged) as $flatPath) {
            self::assertContains($flatPath, $explainedPaths, $scenarioId);
        }

        foreach ($explain['paths'] as $pathRow) {
            self::assertSame('http', $pathRow['root']);
            self::assertNotSame('unknown', $pathRow['sourceType']);
            self::assertNotSame('unknown/http', $pathRow['sourceId']);
            self::assertGreaterThanOrEqual(0, $pathRow['sourceOrder']);
            self::assertGreaterThanOrEqual(0, $pathRow['sourcePrecedence']);
            self::assertSame(
                [
                    'ownership' => 'user_owned',
                    'status' => 'unvalidated',
                ],
                $pathRow['validation'],
            );
        }

        self::assertSame(
            self::sorted($explainedPaths),
            $explainedPaths,
            'Explain paths must be deterministic for ' . $scenarioId,
        );

        self::assertSame(
            self::sorted(\array_column($explain['sourceRanks'], 'sourceId')),
            \array_column($explain['sourceRanks'], 'sourceId'),
            'Synthetic source ids are intentionally deterministic for ' . $scenarioId,
        );
    }

    /**
     * @return iterable<string, array{0:string,1:array<string,mixed>}>
     */
    public static function successfulSpikeScenarioProvider(): iterable
    {
        foreach (self::loadSpikeFixture()['scenarios'] as $scenarioId => $scenario) {
            self::assertIsString($scenarioId);
            self::assertIsArray($scenario);

            if (isset($scenario['expect']['error_code'])) {
                continue;
            }

            yield $scenarioId => [
                $scenarioId,
                $scenario,
            ];
        }
    }

    /**
     * @param array{
     *     inputs: array{defaults: array<string,mixed>, skeleton: array<string,mixed>, module: array<string,mixed>, app: array<string,mixed>}
     * } $scenario
     *
     * @return array<string,mixed>
     */
    private static function mergeScenario(array $scenario): array
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $merged = [];

        foreach (self::loadSpikeFixture()['precedence'] as $sourceName) {
            self::assertIsString($sourceName);
            self::assertArrayHasKey($sourceName, $scenario['inputs']);

            $sourcePayload = $scenario['inputs'][$sourceName];

            self::assertIsArray($sourcePayload);

            /*
             * In spike fixtures, an empty source payload means "this source has no
             * config file / no patch". It is not an explicit empty list replacement.
             *
             * Runtime loaders normally model absent files by not emitting a merge entry
             * at all, so the contract adapter must skip empty source payloads.
             */
            if ($sourcePayload === []) {
                continue;
            }

            $patch = $processor->processConfigTree(
                self::expandDotPaths($sourcePayload),
            );

            $merged = $merger->merge($merged, $patch);

            self::assertIsArray($merged);
        }

        /** @var array<string,mixed> $merged */
        return self::sortRecursively(self::flattenDotPaths($merged));
    }

    /**
     * @param array<string,mixed> $flatMerged
     *
     * @return list<ConfigValueSource>
     */
    private static function sourcesForFlatMerged(array $flatMerged): array
    {
        $sources = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'http',
                sourceId: 'spike/defaults/http',
                path: 'framework/tools/spikes/config_merge/defaults',
                keyPath: 'http',
                directive: null,
                precedence: 10,
                redacted: false,
                meta: [
                    'kind' => 'package_default',
                    'sourceOrder' => 0,
                ],
            ),
        ];

        $order = 1;

        foreach (\array_keys($flatMerged) as $path) {
            self::assertIsString($path);

            $directive = \str_contains($path, 'middleware')
                ? '@merge'
                : null;

            $sources[] = new ConfigValueSource(
                type: self::sourceTypeForOrder($order),
                root: 'http',
                sourceId: 'spike/effective/' . \str_replace('.', '_', $path),
                path: 'framework/tools/spikes/config_merge/scenarios.php',
                keyPath: $path,
                directive: $directive,
                precedence: 100 + $order,
                redacted: false,
                meta: [
                    'kind' => 'spike_scenario',
                    'sourceOrder' => $order,
                ],
            );

            $order++;
        }

        return $sources;
    }

    private static function sourceTypeForOrder(int $order): ConfigSourceType
    {
        return $order % 2 === 0
            ? ConfigSourceType::SkeletonConfig
            : ConfigSourceType::AppConfig;
    }

    private static function processor(): DirectiveProcessor
    {
        return new DirectiveProcessor(
            namespaceGuard: new ConfigNamespaceGuard([
                'coretsia',
                '_internal',
            ]),
        );
    }

    private static function merger(DirectiveProcessor $processor): ConfigMerger
    {
        return new ConfigMerger(
            directiveProcessor: $processor,
        );
    }

    /**
     * @return array{
     *     schema_version: int,
     *     precedence: list<string>,
     *     scenarios: array<string,array<string,mixed>>
     * }
     */
    private static function loadSpikeFixture(): array
    {
        $fixture = require self::spikeFixturePath();

        self::assertIsArray($fixture);
        self::assertSame(1, $fixture['schema_version'] ?? null);
        self::assertSame(['defaults', 'skeleton', 'module', 'app'], $fixture['precedence'] ?? null);
        self::assertIsArray($fixture['scenarios'] ?? null);

        /** @var array{schema_version:int,precedence:list<string>,scenarios:array<string,array<string,mixed>>} $fixture */
        return $fixture;
    }

    private static function spikeFixturePath(): string
    {
        return __DIR__ . '/../../../../../tools/spikes/config_merge/tests/fixtures/scenarios.php';
    }

    /**
     * @param array<string,mixed> $flat
     *
     * @return array<string,mixed>
     */
    private static function expandDotPaths(array $flat): array
    {
        $expanded = [];

        foreach ($flat as $path => $value) {
            self::assertIsString($path);

            $segments = \explode('.', $path);
            $cursor = &$expanded;

            foreach ($segments as $index => $segment) {
                if ($index === \count($segments) - 1) {
                    $cursor[$segment] = $value;

                    continue;
                }

                if (!isset($cursor[$segment]) || !\is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }

                $cursor = &$cursor[$segment];
            }

            unset($cursor);
        }

        return self::sortRecursively($expanded);
    }

    /**
     * @param array<string,mixed> $tree
     *
     * @return array<string,mixed>
     */
    private static function flattenDotPaths(array $tree): array
    {
        $flat = [];

        foreach ($tree as $key => $value) {
            self::assertIsString($key);

            self::flattenNode(
                value: $value,
                path: $key,
                out: $flat,
            );
        }

        \ksort($flat, \SORT_STRING);

        return $flat;
    }

    /**
     * @param array<string,mixed> $out
     */
    private static function flattenNode(
        mixed $value,
        string $path,
        array &$out,
    ): void {
        if (!\is_array($value)) {
            $out[$path] = $value;

            return;
        }

        if (\array_is_list($value)) {
            $out[$path] = $value;

            return;
        }

        if ($value === []) {
            $out[$path] = [];

            return;
        }

        foreach ($value as $key => $child) {
            self::assertIsString($key);

            self::flattenNode(
                value: $child,
                path: $path . '.' . $key,
                out: $out,
            );
        }
    }

    /**
     * @param array<array-key,mixed> $value
     *
     * @return array<array-key,mixed>
     */
    private static function sortRecursively(array $value): array
    {
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::sortRecursively($item);
            }
        }

        if (!\array_is_list($value)) {
            \ksort($value, \SORT_STRING);
        }

        return $value;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        \sort($values, \SORT_STRING);

        return $values;
    }
}
