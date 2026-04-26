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

namespace Coretsia\Tools\Spikes\config_merge\tests;

use Coretsia\Tools\Spikes\config_merge\ConfigExplainer;
use Coretsia\Tools\Spikes\config_merge\ConfigMerger;
use PHPUnit\Framework\TestCase;

final class ExplainTraceMatchesMergerDirectiveApplicationTest extends TestCase
{
    public function testDirectiveAppliedTraceIsOneToOneWithMergerApplicationForSameSources(): void
    {
        // We intentionally use @replace with marker payloads so we can *prove*
        // the directive was applied by observing the merged output value.
        // Also: no directives inside lists, because explainer treats lists as leaf values.
        $sources = [
            [
                'sourceType' => 'defaults',
                'file' => 'defaults.php',
                'config' => [
                    'a' => [
                        'plain' => 'base',
                        'nested' => [
                            'keep' => true,
                        ],
                    ],
                    'list' => ['one', 'two'],
                ],
            ],
            [
                'sourceType' => 'module',
                'file' => 'module.php',
                'config' => [
                    // Map replacement semantics (non-directive map): nested directives MUST be resolved
                    // against missing base for that subtree, so markers MUST appear in merged output.
                    'a' => [
                        'plain' => 'module',
                        'directiveLeaf' => [
                            '@replace' => 'MARK:a.directiveLeaf',
                        ],
                        'nested' => [
                            'x' => [
                                '@replace' => 'MARK:a.nested.x',
                            ],
                            'keep' => true,
                        ],
                    ],
                    'rootDirective' => [
                        '@replace' => 'MARK:rootDirective',
                    ],
                ],
            ],
        ];

        $explainer = new ConfigExplainer();
        $merger = new ConfigMerger();

        $trace = $explainer->explain($sources);
        $merged = $merger->mergeSources($sources);

        // 1) Extract directive-applied keyPaths from trace.
        $traceDirectivePaths = [];
        foreach ($trace as $record) {
            if (($record['directiveApplied'] ?? false) === true) {
                $traceDirectivePaths[] = (string)$record['keyPath'];
            }
        }

        usort($traceDirectivePaths, static fn (string $a, string $b): int => strcmp($a, $b));

        // 2) Extract marker keyPaths from merged output (proof that merger applied the directives).
        $markerPaths = [];
        $this->collectMarkerPaths($merged, '', $markerPaths);

        usort($markerPaths, static fn (string $a, string $b): int => strcmp($a, $b));

        // 3) 1:1: exactly the same keyPaths.
        self::assertSame($traceDirectivePaths, $markerPaths);

        // 4) Stronger: each marker value MUST equal "MARK:<keyPath>".
        foreach ($markerPaths as $path) {
            $value = $this->getByDotPath($merged, $path);
            self::assertSame('MARK:' . $path, $value);
        }
    }

    /**
     * Collect dot-paths for leaf string values starting with "MARK:".
     *
     * @param mixed $node
     * @param string $prefix
     * @param list<string> $out
     */
    private function collectMarkerPaths(mixed $node, string $prefix, array &$out): void
    {
        if (!is_array($node) || $node === []) {
            return;
        }

        // Lists are leaf values in explainer; we also treat them as leaf here.
        if (array_is_list($node)) {
            return;
        }

        /** @var array<string|int, mixed> $node */
        $keys = array_keys($node);

        usort(
            $keys,
            static fn (string|int $a, string|int $b): int => strcmp((string)$a, (string)$b),
        );

        foreach ($keys as $key) {
            $k = (string)$key;
            $value = $node[$key];

            $path = $prefix === '' ? $k : ($prefix . '.' . $k);

            if (is_string($value) && str_starts_with($value, 'MARK:')) {
                $out[] = $path;
                continue;
            }

            if (is_array($value) && $value !== [] && !array_is_list($value)) {
                $this->collectMarkerPaths($value, $path, $out);
            }
        }
    }

    /**
     * Get value by dot-path (maps only; list indexes are intentionally unsupported here).
     *
     * @param array<string, mixed> $root
     */
    private function getByDotPath(array $root, string $path): mixed
    {
        $parts = explode('.', $path);

        $node = $root;
        foreach ($parts as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                throw new \LogicException('test-dot-path-not-found: ' . $path);
            }
            $node = $node[$part];
        }

        return $node;
    }
}
