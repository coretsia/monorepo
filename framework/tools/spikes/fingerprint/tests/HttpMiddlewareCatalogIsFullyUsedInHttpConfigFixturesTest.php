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

namespace Coretsia\Tools\Spikes\fingerprint\tests;

use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\FixtureRoot;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.60.0 (MUST):
 *  - fixtures/http_middleware_catalog.php is the single source of truth for Phase 0 slot taxonomy.
 *  - repo_min/skeleton/config/http.php MUST be derived from the catalog (no duplicated lists).
 *  - A test MUST fail if any slot is missing or diverges from the catalog.
 */
final class HttpMiddlewareCatalogIsFullyUsedInHttpConfigFixturesTest extends TestCase
{
    public function test_http_config_fixture_is_derived_from_catalog_and_includes_all_slots(): void
    {
        $catalogPath = FixtureRoot::path('http_middleware_catalog.php');
        $configPath = FixtureRoot::path('repo_min/skeleton/config/http.php');

        /** @var array $catalog */
        $catalog = require $catalogPath;
        self::assertIsArray($catalog);

        $slots = $catalog['slots'] ?? null;
        self::assertIsArray($slots);

        /** @var array $cfg */
        $cfg = require $configPath;
        self::assertIsArray($cfg);

        $middleware = $cfg['middleware'] ?? null;
        self::assertIsArray($middleware);

        $slotMap = [
            'system_pre' => 'http.middleware.system_pre',
            'system' => 'http.middleware.system',
            'system_post' => 'http.middleware.system_post',
            'app_pre' => 'http.middleware.app_pre',
            'app' => 'http.middleware.app',
            'app_post' => 'http.middleware.app_post',
            'route_pre' => 'http.middleware.route_pre',
            'route' => 'http.middleware.route',
            'route_post' => 'http.middleware.route_post',
        ];

        // MUST: include all slots and match the catalog exactly.
        foreach ($slotMap as $short => $full) {
            self::assertArrayHasKey($full, $slots, 'catalog missing slot: ' . $full);
            self::assertArrayHasKey($short, $middleware, 'http config missing slot: ' . $short);

            $slotRows = $slots[$full];
            self::assertIsArray($slotRows);

            $expectedClasses = [];
            foreach ($slotRows as $row) {
                self::assertIsArray($row);
                $class = $row['class'] ?? null;
                self::assertIsString($class);
                self::assertNotSame('', $class);
                $expectedClasses[] = $class;
            }

            $actualClasses = $middleware[$short];
            self::assertIsArray($actualClasses);
            self::assertSame($expectedClasses, array_values($actualClasses), 'slot diverged: ' . $short);
        }

        // MUST: no extra unknown slot keys in http.php fixture.
        $expectedKeys = array_keys($slotMap);
        sort($expectedKeys);
        $actualKeys = array_keys($middleware);
        sort($actualKeys);
        self::assertSame($expectedKeys, $actualKeys);

        // Source-level guard: http.php MUST require the catalog (no hardcoded lists).
        $src = DeterministicFile::readBytesExact($configPath);
        self::assertStringContainsString("require __DIR__ . '/../../../http_middleware_catalog.php'", $src);

        // If someone hardcodes lists, they will typically embed Middleware::class entries here.
        self::assertStringNotContainsString('Middleware::class', $src);
        self::assertStringNotContainsString('\\Coretsia\\', $src);
    }
}
