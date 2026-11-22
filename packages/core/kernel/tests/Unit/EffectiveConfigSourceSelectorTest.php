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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Config\Source\EffectiveConfigSourceSelector;
use PHPUnit\Framework\TestCase;

final class EffectiveConfigSourceSelectorTest extends TestCase
{
    public function testExplicitPrecedenceWinsBeforeSourceTypeOrderAndSpecificity(): void
    {
        $package = self::source('package', ConfigSourceType::PackageDefault, 30);
        $application = self::source('application', ConfigSourceType::ApplicationConfig, 20, 'http.debug', 99);
        $env = self::source('environment', ConfigSourceType::Env, 10, 'http.debug.enabled', 100);

        self::assertSame($package, EffectiveConfigSourceSelector::select('http.debug.enabled', [
            $env,
            $application,
            $package,
        ]));

        $overlay = self::source('overlay', ConfigSourceType::Env, 40, 'http.debug.enabled');
        self::assertSame($overlay, EffectiveConfigSourceSelector::select('http.debug.enabled', [
            $package,
            $overlay,
            $application,
        ]));
    }

    public function testRootExactDottedAndListBoundaries(): void
    {
        $root = self::source('root', ConfigSourceType::PackageDefault, 0);
        $debug = self::source('debug', ConfigSourceType::ApplicationConfig, 1, 'http.debug');
        $list = self::source('list', ConfigSourceType::ApplicationConfig, 2, 'http.debug.ips');
        $item = self::source('item', ConfigSourceType::Env, 3, 'http.debug.ips[1]');
        $sources = [$item, $list, $debug, $root];

        foreach (
            [
                'http' => $root,
                'http.debug' => $debug,
                'http.debug.enabled' => $debug,
                'http.debugger' => $root,
                'http.debug.ips' => $list,
                'http.debug.ips[0]' => $list,
                'http.debug.ips[1]' => $item,
                'http.debug.ips[1].address' => $item,
                'http.debug.ips[10]' => $list,
                'http.debug.ips_extra' => $debug,
                'http_other.debug' => null,
                'missing' => null,
            ] as $path => $expected
        ) {
            self::assertSame($expected, EffectiveConfigSourceSelector::select($path, $sources), $path);
        }
        self::assertNull(EffectiveConfigSourceSelector::select('http.debugger', [$debug]));
        self::assertNull(EffectiveConfigSourceSelector::select('http.debug.ips[10]', [$item]));
        self::assertNull(EffectiveConfigSourceSelector::select('http', [$debug]));
        self::assertNull(EffectiveConfigSourceSelector::select('http.debug', []));
    }

    public function testRecordedOrderThenSpecificityThenStableIdentityResolveTies(): void
    {
        $root = self::source('root', ConfigSourceType::PackageDefault, 10, null, 4);
        $exact = self::source('exact', ConfigSourceType::Env, 10, 'http.debug.enabled', 3);
        self::assertSame($root, EffectiveConfigSourceSelector::select('http.debug.enabled', [$exact, $root]));

        $specific = self::source('specific', ConfigSourceType::Env, 10, 'http.debug.enabled', 4);
        self::assertSame($specific, EffectiveConfigSourceSelector::select('http.debug.enabled', [$root, $specific]));

        $alpha = self::source('alpha', ConfigSourceType::Env, 10, 'http.debug.enabled', 4);
        $zeta = self::source('zeta', ConfigSourceType::Env, 10, 'http.debug.enabled', 4);
        $sources = [$zeta, $root, $alpha, $specific];

        foreach ([$sources, \array_reverse($sources)] as $ordered) {
            self::assertSame($zeta, EffectiveConfigSourceSelector::select('http.debug.enabled', $ordered));
            self::assertSame(
                [$root, $alpha, $specific, $zeta],
                \array_column(
                    EffectiveConfigSourceSelector::normalizeSources(
                        EffectiveConfigSourceSelector::sourceListToMap($ordered),
                    ),
                    'source',
                ),
            );
        }

        $app = self::source('same', ConfigSourceType::ApplicationConfig, 10, 'http.debug.enabled', 4);
        $env = self::source('same', ConfigSourceType::Env, 10, 'http.debug.enabled', 4);
        self::assertSame($env, EffectiveConfigSourceSelector::select('http.debug.enabled', [$env, $app]));

        $append = self::source('same', ConfigSourceType::Env, 10, 'http.debug.enabled', 4, 'append');
        $replace = self::source('same', ConfigSourceType::Env, 10, 'http.debug.enabled', 4, 'replace');
        self::assertSame($replace, EffectiveConfigSourceSelector::select('http.debug.enabled', [$replace, $append]));
    }

    public function testEffectiveSourceIsIndependentOfConfigValues(): void
    {
        $source = self::source('application', ConfigSourceType::ApplicationConfig, 10, 'http.debug.enabled');
        $path = 'http.debug.enabled';

        foreach ([true, false, null, 'synthetic-value', []] as $value) {
            $repository = new ArrayConfigRepository(['http' => ['debug' => ['enabled' => $value]]], [$source]);

            self::assertSame($value, $repository->get($path));
            self::assertSame($source, $repository->sourceOf($path));
            self::assertSame($source, EffectiveConfigSourceSelector::select($path, [$source]));
        }
    }

    private static function source(
        string $id,
        ConfigSourceType $type,
        int $precedence,
        ?string $keyPath = null,
        int $order = 0,
        ?string $directive = null,
    ): ConfigValueSource {
        return new ConfigValueSource(
            type: $type,
            root: 'http',
            sourceId: $id,
            keyPath: $keyPath,
            directive: $directive,
            precedence: $precedence,
            meta: ['sourceOrder' => $order],
        );
    }
}
