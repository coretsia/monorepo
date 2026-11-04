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

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Metrics\Module\MetricsModule;
use PHPUnit\Framework\TestCase;

final class MetricsConfigRulesContractTest extends TestCase
{
    public function testDefaultsAndRulesUseAnExactlyEmptyStrictMetricsRoot(): void
    {
        $root = \dirname(__DIR__, 2);
        $defaults = require $root . '/config/metrics.php';
        $rules = require $root . '/config/rules.php';

        self::assertSame([], $defaults);
        self::assertSame([
            'schemaVersion' => 1,
            'configRoot' => 'metrics',
            'additionalKeys' => false,
            'keys' => [],
        ], $rules);
        self::assertSame('metrics', MetricsModule::CONFIG_ROOT);
        self::assertSame('metrics', new MetricsModule()->configRoot());
    }

    public function testKernelRejectsUnknownSelectorsAndConfigurableLabelKeys(): void
    {
        $rules = require \dirname(__DIR__, 2) . '/config/rules.php';
        $ruleset = ConfigRuleset::fromArray('metrics', $rules);
        $validator = new ConfigValidator();

        self::assertTrue($validator->validate(['metrics' => []], [$ruleset])->isSuccess());
        self::assertTrue($validator->validate([], [$ruleset])->isFailure());

        foreach (
            [
                ['enabled' => true],
                ['renderer' => 'null'],
                ['collector' => 'noop'],
                ['exporter' => 'null'],
                ['backend' => 'noop'],
                ['sampling' => ['mode' => 'always_off']],
                ['labels' => ['allowed' => ['method']]],
                ['labels.allowed' => ['method']],
                ['catalog' => []],
                ['metric_catalog' => []],
                ['registry' => []],
                ['context_store' => true],
                ['@internal' => true],
            ] as $invalid
        ) {
            $result = $validator->validate(['metrics' => $invalid], [$ruleset]);

            self::assertTrue($result->isFailure());
            self::assertCount(1, $result->violations());
            self::assertSame('unknown-key', $result->violations()[0]->reason());
        }
    }

    public function testPackageConfigRootOwnerMatchesCanonicalRegistry(): void
    {
        $root = \dirname(__DIR__, 2);
        $repository = \dirname(__DIR__, 5);
        $manifest = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $registry = (string) \file_get_contents($repository . '/docs/ssot/config-roots.md');

        self::assertSame('config/metrics.php', $manifest['extra']['coretsia']['defaultsConfigPath']);
        self::assertFileExists($root . '/config/metrics.php');
        self::assertFileExists($root . '/config/rules.php');
        self::assertSame(
            1,
            \preg_match_all(
                '/^\|\s*`metrics`\s*\|\s*`platform\/metrics`\s*\|'
                . '\s*`packages\/platform\/metrics\/config\/metrics\.php`\s*\|'
                . '\s*`packages\/platform\/metrics\/config\/rules\.php`\s*\|/m',
                $registry,
            ),
        );
    }
}
