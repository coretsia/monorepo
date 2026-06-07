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

use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveMixedLevelException;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveTypeMismatchException;
use Coretsia\Kernel\Config\Exception\ConfigReservedNamespaceException;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpikeConfigMergeCompatibilityContractTest extends TestCase
{
    /**
     * @param array{
     *     title: string,
     *     inputs: array{defaults: array<string,mixed>, skeleton: array<string,mixed>, module: array<string,mixed>, app: array<string,mixed>},
     *     expect: array{merged?: array<string,mixed>, error_code?: string},
     *     notes?: string
     * } $scenario
     */
    #[DataProvider('spikeScenarioProvider')]
    public function testRuntimeMergeMatchesSpikeScenario(
        string $scenarioId,
        array $scenario,
    ): void {
        $expect = $scenario['expect'];

        try {
            $merged = self::mergeScenario($scenario);

            if (isset($expect['error_code'])) {
                self::fail(
                    \sprintf(
                        'Spike scenario "%s" expected error code "%s", but runtime merge succeeded.',
                        $scenarioId,
                        $expect['error_code'],
                    )
                );
            }

            self::assertSame(
                self::sortRecursively($expect['merged'] ?? []),
                self::sortRecursively($merged),
                'Spike scenario mismatch: ' . $scenarioId,
            );
        } catch (\Throwable $exception) {
            if (!isset($expect['error_code'])) {
                throw $exception;
            }

            self::assertSame(
                $expect['error_code'],
                self::errorCode($exception),
                'Spike scenario error-code mismatch: ' . $scenarioId,
            );

            self::assertSafeThrowable($exception);
        }
    }

    /**
     * @return iterable<string, array{0:string,1:array<string,mixed>}>
     */
    public static function spikeScenarioProvider(): iterable
    {
        $fixture = self::loadSpikeFixture();

        foreach ($fixture['scenarios'] as $scenarioId => $scenario) {
            self::assertIsString($scenarioId);
            self::assertIsArray($scenario);

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
        return self::sortRecursively(
            self::collapseToExpectedFlatShape(
                tree: $merged,
                expectedFlatPaths: \array_keys($scenario['expect']['merged'] ?? []),
            ),
        );
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

    private static function errorCode(\Throwable $exception): string
    {
        if ($exception instanceof ConfigReservedNamespaceException) {
            return $exception->errorCode();
        }

        if ($exception instanceof ConfigDirectiveMixedLevelException) {
            return $exception->errorCode();
        }

        if ($exception instanceof ConfigDirectiveTypeMismatchException) {
            return $exception->errorCode();
        }

        return $exception::class;
    }

    private static function assertSafeThrowable(\Throwable $exception): void
    {
        $message = $exception->getMessage();

        self::assertStringNotContainsString('Debugbar', $message);
        self::assertStringNotContainsString('Maintenance', $message);
        self::assertStringNotContainsString('ErrorHandling', $message);
        self::assertStringNotContainsString('RouteGuard', $message);
        self::assertStringNotContainsString('https://', $message);
        self::assertStringNotContainsString('default-src', $message);
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
     * Converts the runtime nested tree back to the spike fixture shape.
     *
     * Spike fixture keys are flat config paths, but values may still be nested
     * maps. For example:
     *
     * - `http.middleware.system_post` => list
     * - `http.features` => map
     * - `http.headers` => map
     *
     * Therefore this adapter must collapse by the expected fixture paths instead
     * of flattening every nested map to leaf paths.
     *
     * @param array<string,mixed> $tree
     * @param list<string|int> $expectedFlatPaths
     *
     * @return array<string,mixed>
     */
    private static function collapseToExpectedFlatShape(
        array $tree,
        array $expectedFlatPaths,
    ): array {
        if ($expectedFlatPaths === []) {
            return self::flattenDotPaths($tree);
        }

        $out = [];

        foreach ($expectedFlatPaths as $path) {
            self::assertIsString($path);

            $out[$path] = self::nodeAtDotPath(
                tree: $tree,
                path: $path,
            );
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    /**
     * @param array<string,mixed> $tree
     */
    private static function nodeAtDotPath(
        array $tree,
        string $path,
    ): mixed {
        $cursor = $tree;

        foreach (\explode('.', $path) as $segment) {
            if (!\is_array($cursor) || !\array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        if (\is_array($cursor)) {
            return self::sortRecursively($cursor);
        }

        return $cursor;
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
}
