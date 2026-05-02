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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use PHPUnit\Framework\TestCase;

final class ConfigTraceOrderingIsDeterministicContractTest extends TestCase
{
    public function test_trace_entries_can_be_ordered_by_phase0_aligned_safe_trace_order(): void
    {
        $entries = [
            new ConfigValueSource(
                type: ConfigSourceType::Runtime,
                root: 'kernel',
                sourceId: 'runtime.kernel',
                path: 'runtime/override',
                keyPath: 'boot.providers',
                precedence: 40,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::AppConfig,
                root: 'foundation',
                sourceId: 'app.foundation',
                path: 'app.config/foundation',
                keyPath: 'container.bindings',
                precedence: 20,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'core.foundation',
                path: 'package.defaults/foundation',
                keyPath: 'container.bindings',
                precedence: 10,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::Env,
                root: 'foundation',
                sourceId: 'env.runtime',
                path: 'env/runtime',
                keyPath: 'container.cache',
                precedence: 30,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::GeneratedArtifact,
                root: 'kernel',
                sourceId: 'compiled.kernel',
                path: 'generated/config',
                keyPath: 'boot.providers',
                precedence: 50,
            ),
        ];

        $sorted = self::sortTraceEntries($entries);

        self::assertSame(
            [
                'foundation|container.bindings|10|package.defaults/foundation|core.foundation',
                'foundation|container.bindings|20|app.config/foundation|app.foundation',
                'foundation|container.cache|30|env/runtime|env.runtime',
                'kernel|boot.providers|40|runtime/override|runtime.kernel',
                'kernel|boot.providers|50|generated/config|compiled.kernel',
            ],
            array_map(
                static fn (ConfigValueSource $source): string => self::traceSortKey($source),
                $sorted,
            ),
        );
    }

    public function test_trace_ordering_is_independent_from_input_order(): void
    {
        $entries = [
            new ConfigValueSource(
                type: ConfigSourceType::GeneratedArtifact,
                root: 'kernel',
                sourceId: 'compiled.kernel',
                path: 'generated/config',
                keyPath: 'boot.providers',
                precedence: 50,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'core.foundation',
                path: 'package.defaults/foundation',
                keyPath: 'container.bindings',
                precedence: 10,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::Env,
                root: 'foundation',
                sourceId: 'env.runtime',
                path: 'env/runtime',
                keyPath: 'container.cache',
                precedence: 30,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::Runtime,
                root: 'kernel',
                sourceId: 'runtime.kernel',
                path: 'runtime/override',
                keyPath: 'boot.providers',
                precedence: 40,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::AppConfig,
                root: 'foundation',
                sourceId: 'app.foundation',
                path: 'app.config/foundation',
                keyPath: 'container.bindings',
                precedence: 20,
            ),
        ];

        $expected = array_map(
            static fn (ConfigValueSource $source): string => self::traceSortKey($source),
            self::sortTraceEntries($entries),
        );

        $reversed = array_reverse($entries);
        $rotated = [
            $entries[2],
            $entries[4],
            $entries[0],
            $entries[3],
            $entries[1],
        ];

        self::assertSame(
            $expected,
            array_map(
                static fn (ConfigValueSource $source): string => self::traceSortKey($source),
                self::sortTraceEntries($reversed),
            ),
        );

        self::assertSame(
            $expected,
            array_map(
                static fn (ConfigValueSource $source): string => self::traceSortKey($source),
                self::sortTraceEntries($rotated),
            ),
        );
    }

    public function test_nullable_key_path_sorts_deterministically_as_empty_string(): void
    {
        $entries = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'core.foundation',
                path: 'package.defaults/foundation',
                keyPath: 'container.bindings',
                precedence: 10,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'core.foundation',
                path: 'package.defaults/foundation',
                keyPath: null,
                precedence: 10,
            ),
        ];

        $sorted = self::sortTraceEntries($entries);

        self::assertNull($sorted[0]->keyPath());
        self::assertSame('container.bindings', $sorted[1]->keyPath());
    }

    public function test_nullable_path_sorts_deterministically_as_empty_string(): void
    {
        $entries = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'core.foundation',
                path: 'package.defaults/foundation',
                keyPath: 'container.bindings',
                precedence: 10,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'core.foundation',
                path: null,
                keyPath: 'container.bindings',
                precedence: 10,
            ),
        ];

        $sorted = self::sortTraceEntries($entries);

        self::assertNull($sorted[0]->path());
        self::assertSame('package.defaults/foundation', $sorted[1]->path());
    }

    public function test_precedence_is_exported_and_part_of_phase0_aligned_safe_trace_sort_key(): void
    {
        $lowerPrecedence = new ConfigValueSource(
            type: ConfigSourceType::PackageDefault,
            root: 'foundation',
            sourceId: 'z.source',
            path: 'package.defaults/foundation',
            keyPath: 'container.bindings',
            precedence: 10,
        );

        $higherPrecedence = new ConfigValueSource(
            type: ConfigSourceType::PackageDefault,
            root: 'foundation',
            sourceId: 'a.source',
            path: 'package.defaults/foundation',
            keyPath: 'container.bindings',
            precedence: 90,
        );

        $sorted = self::sortTraceEntries([$higherPrecedence, $lowerPrecedence]);

        self::assertSame('z.source', $sorted[0]->sourceId());
        self::assertSame(10, $sorted[0]->precedence());

        self::assertSame('a.source', $sorted[1]->sourceId());
        self::assertSame(90, $sorted[1]->precedence());
    }

    public function test_path_is_safe_source_file_equivalent_in_phase0_aligned_sort_key(): void
    {
        $entries = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'same.source',
                path: 'z.source',
                keyPath: 'container.bindings',
                precedence: 10,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'same.source',
                path: 'a.source',
                keyPath: 'container.bindings',
                precedence: 10,
            ),
        ];

        $sorted = self::sortTraceEntries($entries);

        self::assertSame('a.source', $sorted[0]->path());
        self::assertSame('z.source', $sorted[1]->path());
    }

    public function test_trace_export_key_order_is_stable_after_sorting(): void
    {
        $entries = self::sortTraceEntries([
            new ConfigValueSource(
                type: ConfigSourceType::Env,
                root: 'foundation',
                sourceId: 'env.runtime',
                path: 'env/runtime',
                keyPath: 'container.cache',
                precedence: 30,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'foundation',
                sourceId: 'core.foundation',
                path: 'package.defaults',
                keyPath: 'container.cache',
                precedence: 10,
            ),
        ]);

        foreach ($entries as $entry) {
            self::assertSame(
                [
                    'directive',
                    'keyPath',
                    'meta',
                    'path',
                    'precedence',
                    'redacted',
                    'root',
                    'schemaVersion',
                    'sourceId',
                    'type',
                ],
                array_keys($entry->toArray()),
            );
        }
    }

    /**
     * Test-only canonical ordering helper.
     *
     * The contracts package does not implement trace rendering. This helper
     * makes the Phase 0 aligned SSoT ordering rule executable as contract
     * evidence.
     *
     * @param list<ConfigValueSource> $entries
     *
     * @return list<ConfigValueSource>
     */
    private static function sortTraceEntries(array $entries): array
    {
        usort(
            $entries,
            static function (ConfigValueSource $a, ConfigValueSource $b): int {
                return strcmp($a->root(), $b->root())
                    ?: strcmp(self::nullableString($a->keyPath()), self::nullableString($b->keyPath()))
                        ?: ($a->precedence() <=> $b->precedence())
                            ?: strcmp(self::nullableString($a->path()), self::nullableString($b->path()))
                                ?: strcmp($a->sourceId(), $b->sourceId());
            },
        );

        /** @var list<ConfigValueSource> $entries */
        return $entries;
    }

    private static function traceSortKey(ConfigValueSource $source): string
    {
        return implode(
            '|',
            [
                $source->root(),
                self::nullableString($source->keyPath()),
                (string)$source->precedence(),
                self::nullableString($source->path()),
                $source->sourceId(),
            ],
        );
    }

    private static function nullableString(?string $value): string
    {
        return $value ?? '';
    }
}
