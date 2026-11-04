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

namespace Coretsia\Platform\Metrics\Tests\Contract;

use Coretsia\Platform\Metrics\Labels\AllowedLabelSet;
use PHPUnit\Framework\TestCase;

final class AllowedLabelSetMatchesObservabilitySsotContractTest extends TestCase
{
    public function testRuntimeLabelKeysMatchTheExactCanonicalSsotSection(): void
    {
        [, $section] = self::canonicalSourceAndLabelSection();
        \preg_match_all('/^- `([a-z_]+)`\h*$/m', $section, $matches);
        $canonical = ['method', 'status', 'driver', 'operation', 'table', 'outcome'];
        $reflection = new \ReflectionClass(AllowedLabelSet::class);
        $constant = $reflection->getReflectionConstant('ALLOWED_KEYS');

        self::assertInstanceOf(\ReflectionClassConstant::class, $constant);
        self::assertTrue($constant->isPrivate());
        self::assertSame($canonical, $matches[1]);
        self::assertSame($canonical, \array_keys($constant->getValue()));
        self::assertSame(\array_fill_keys($canonical, true), $constant->getValue());
    }

    public function testMetricsPolicyReferencesCanonicalAuthorityWithoutRecreatingIt(): void
    {
        $path = \dirname(__DIR__, 5) . '/docs/ssot/metrics-policy.md';
        self::assertFileExists($path);
        $policy = (string) \file_get_contents($path);

        self::assertStringContainsString('docs/ssot/observability.md', $policy);
        self::assertSame(
            0,
            \preg_match(
                '/^#{1,6}\h+(?:(?:Canonical|Global)\h+)?'
                . '(?:Metrics?\h+(?:Catalog|Registry)|Label(?:\h+Key)?\h+Allowlist'
                . '|Allowed\h+(?:Metric\h+)?Labels|Metric\h+Label\h+Keys)'
                . '(?:\h*\(MUST\))?\h*$/mi',
                $policy,
            ),
        );
        self::assertSame(
            0,
            \preg_match('/^\|\h*Metric name\h*\|\h*Owner\h*\|\h*Type\h*\|\h*Labels\h*\|/mi', $policy),
        );
    }

    public function testRuntimeConfigurationCannotExtendGlobalLabelAdmission(): void
    {
        $root = \dirname(__DIR__, 2);
        $defaults = require $root . '/config/metrics.php';
        $rules = require $root . '/config/rules.php';

        self::assertSame([], $defaults);
        self::assertSame([], $rules['keys']);
        self::assertFalse($rules['additionalKeys']);
        self::assertFalse(AllowedLabelSet::accepts(['trace_id' => 'synthetic']));
        self::assertFalse(AllowedLabelSet::accepts(['token' => 'synthetic']));
    }

    public function testCanonicalMetricSpecificCatalogIsSeparateAndMoreRestrictive(): void
    {
        [$source] = self::canonicalSourceAndLabelSection();
        $count = \preg_match_all(
            '/^\|\h*([a-z][a-z0-9_.]+)\h*\|\h*`[^`]+`\h*\|\h*(counter|observe)\h*\|\h*([^|]*?)\h*\|$/m',
            $source,
            $rows,
            \PREG_SET_ORDER,
        );
        self::assertGreaterThan(0, $count);
        $catalog = [];

        foreach ($rows as $row) {
            \preg_match_all('/`([a-z_]+)`/', $row[3], $labels);
            $catalog[$row[1]] = [
                'type' => $row[2],
                'labels' => $labels[1],
            ];

            foreach ($labels[1] as $label) {
                self::assertTrue(AllowedLabelSet::accepts([$label => 'synthetic']));
            }
        }

        self::assertSame(
            ['type' => 'counter', 'labels' => ['method', 'status', 'outcome']],
            $catalog['http.request_total'] ?? null,
        );
        self::assertSame(
            ['type' => 'observe', 'labels' => ['outcome']],
            $catalog['foundation.reset_duration_ms'] ?? null,
        );
        self::assertTrue(AllowedLabelSet::accepts(['table' => 'synthetic']));
        self::assertNotContains('table', $catalog['http.request_total']['labels']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function canonicalSourceAndLabelSection(): array
    {
        $repository = \dirname(__DIR__, 5);
        $source = (string) \file_get_contents($repository . '/docs/ssot/observability.md');
        $matched = \preg_match(
            '/^## Label Allowlist \(MUST\)\h*\R(.*?)^### Label Rules \(MUST\)\h*$/ms',
            $source,
            $sections,
        );

        self::assertSame(1, $matched);

        return [$source, $sections[1]];
    }
}
