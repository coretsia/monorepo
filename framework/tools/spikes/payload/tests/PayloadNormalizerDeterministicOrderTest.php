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

namespace Coretsia\Tools\Spikes\payload\tests;

use Coretsia\Tools\Spikes\_support\FixtureRoot;
use Coretsia\Tools\Spikes\payload\PayloadNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.70.0 (MUST):
 * - maps: keys sorted ascending by byte-order (strcmp) at every nesting level
 * - lists: order preserved (array_is_list)
 * - empty array [] treated as list and kept as []
 *
 * Security/Redaction:
 * - Avoid assertions that dump full payload values.
 */
final class PayloadNormalizerDeterministicOrderTest extends TestCase
{
    public function test_normalize_is_deterministic_and_sorts_maps_preserves_lists(): void
    {
        $fixtures = self::fixtures();

        $http = $fixtures['ok_http_middleware_config'] ?? null;
        self::assertIsArray($http);

        $large = $fixtures['ok_large_nested_mixed'] ?? null;
        self::assertIsArray($large);

        $httpA = PayloadNormalizer::normalize($http);
        $httpB = PayloadNormalizer::normalize($http);

        // Deterministic across reruns.
        self::assertSame(self::shapeHash($httpA), self::shapeHash($httpB));

        $largeA = PayloadNormalizer::normalize($large);
        $largeB = PayloadNormalizer::normalize($large);

        self::assertSame(self::shapeHash($largeA), self::shapeHash($largeB));

        // Verify some cemented key-orders at selected paths (no raw values printed).

        // ok_http_middleware_config top-level keys should be sorted: meta, middleware, opt_in, schema_version
        self::assertSame(
            ['meta', 'middleware', 'opt_in', 'schema_version'],
            array_keys($httpA),
        );

        // middleware map key-order (strcmp)
        $middleware = $httpA['middleware'] ?? null;
        self::assertIsArray($middleware);
        self::assertFalse(array_is_list($middleware));

        self::assertSame(
            [
                'app',
                'app_post',
                'app_pre',
                'route',
                'route_post',
                'route_pre',
                'system',
                'system_post',
                'system_pre',
            ],
            array_keys($middleware),
        );

        // Preserve list order: system_pre first/last element stable.
        $systemPre = $middleware['system_pre'] ?? null;
        self::assertIsArray($systemPre);
        self::assertTrue(array_is_list($systemPre));
        self::assertNotSame([], $systemPre);

        self::assertSame('Coretsia\\Http\\Middleware\\CorrelationIdMiddleware', $systemPre[0] ?? null);
        self::assertSame('Coretsia\\Http\\Maintenance\\MaintenanceMiddleware', $systemPre[count($systemPre) - 1] ?? null);

        // Large payload top-level keys order (strcmp): a, empty, flags, matrix, strings, tree, z
        self::assertSame(
            ['a', 'empty', 'flags', 'matrix', 'strings', 'tree', 'z'],
            array_keys($largeA),
        );

        // tree keys order: node1, node2, node3
        $tree = $largeA['tree'] ?? null;
        self::assertIsArray($tree);
        self::assertFalse(array_is_list($tree));
        self::assertSame(['node1', 'node2', 'node3'], array_keys($tree));

        // matrix is list-of-lists and must preserve order.
        $matrix = $largeA['matrix'] ?? null;
        self::assertIsArray($matrix);
        self::assertTrue(array_is_list($matrix));
        self::assertSame([1, 2, 3, 4, 5], $matrix[0] ?? null);
        self::assertSame([5, 4, 3, 2, 1], $matrix[1] ?? null);

        // Cemented: empty array stays [] and is treated as list.
        $emptyAsList = $largeA['empty']['as_list'] ?? null;
        self::assertIsArray($emptyAsList);
        self::assertSame([], $emptyAsList);
        self::assertTrue(array_is_list($emptyAsList));
    }

    /**
     * @return array<string, array>
     */
    private static function fixtures(): array
    {
        $path = FixtureRoot::path('payloads_min/payloads.php');

        $fixtures = require $path;

        self::assertIsArray($fixtures);

        /** @var array<string, array> $fixtures */
        return $fixtures;
    }

    /**
     * Produce a stable "shape hash" that won't dump raw values on failure.
     *
     * @param array $payload
     * @return string
     * @throws \JsonException
     */
    private static function shapeHash(array $payload): string
    {
        $shape = self::shapeExtract($payload);

        $json = json_encode($shape, JSON_THROW_ON_ERROR);
        return hash('sha256', $json);
    }

    private static function shapeExtract(mixed $v): mixed
    {
        if (!is_array($v)) {
            // Don't include raw values; just type tags.
            return get_debug_type($v);
        }

        if ($v === []) {
            return ['@list' => []];
        }

        if (array_is_list($v)) {
            $out = [];
            foreach ($v as $item) {
                $out[] = self::shapeExtract($item);
            }
            return ['@list' => $out];
        }

        $keys = array_keys($v);
        // For maps, keep only keys and nested shapes; no raw values.
        $out = ['@map_keys' => $keys, '@map' => []];

        foreach ($v as $k => $item) {
            $out['@map'][(string)$k] = self::shapeExtract($item);
        }

        return $out;
    }
}
