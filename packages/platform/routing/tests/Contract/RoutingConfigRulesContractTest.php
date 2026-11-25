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

namespace Coretsia\Platform\Routing\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Routing\Module\RoutingModule;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Provider\RoutingServiceFactory;
use PHPUnit\Framework\TestCase;

final class RoutingConfigRulesContractTest extends TestCase
{
    public function testDefaultsAndRulesExposeOnlyThreeIndependentBooleanFlags(): void
    {
        $root = \dirname(__DIR__, 2);
        $defaults = require $root . '/config/routing.php';
        $rules = require $root . '/config/rules.php';
        $flag = [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => ['enabled' => ['required' => true, 'type' => 'bool']],
        ];

        self::assertSame([
            'tracing' => ['enabled' => true],
            'metrics' => ['enabled' => true],
            'logging' => ['enabled' => true],
        ], $defaults);
        self::assertSame([
            'schemaVersion' => 1,
            'configRoot' => 'routing',
            'additionalKeys' => false,
            'keys' => ['tracing' => $flag, 'metrics' => $flag, 'logging' => $flag],
        ], $rules);
        self::assertSame('routing', RoutingModule::CONFIG_ROOT);
        self::assertSame('routing', new RoutingModule()->configRoot());
        self::assertTrue(
            new ConfigValidator()->validate(
                ['routing' => $defaults],
                [ConfigRuleset::fromArray('routing', $rules)],
            )->isSuccess(),
        );
    }

    public function testAllEightIndependentFlagCombinationsAreAccepted(): void
    {
        $validator = new ConfigValidator();
        $ruleset = self::ruleset();

        foreach ([false, true] as $tracing) {
            foreach ([false, true] as $metrics) {
                foreach ([false, true] as $logging) {
                    $config = [
                        'routing' => [
                            'tracing' => ['enabled' => $tracing],
                            'metrics' => ['enabled' => $metrics],
                            'logging' => ['enabled' => $logging],
                        ],
                    ];
                    $result = $validator->validate($config, [$ruleset]);
                    self::assertTrue($result->isSuccess());
                    self::assertSame([], $result->violations());

                    $observer = RoutingServiceFactory::matchObserver(
                        new NoopTracer(),
                        new NoopMeter(),
                        new NoopLogger(),
                        new Stopwatch(),
                        new ArrayConfigRepository($config, []),
                    );
                    foreach (
                        [
                            'tracingEnabled' => $tracing,
                            'metricsEnabled' => $metrics,
                            'loggingEnabled' => $logging,
                        ] as $property => $enabled
                    ) {
                        self::assertSame(
                            $enabled,
                            new \ReflectionProperty(RoutingMatchObserver::class, $property)->getValue($observer),
                        );
                    }
                }
            }
        }
    }

    public function testUnknownKeysIncludingReservedGuardBypassAreRejected(): void
    {
        $defaults = self::defaults();
        foreach (
            [
                'enabled' => false,
                'reserved_guard' => ['enabled' => false],
                'reserved_routes' => ['enabled' => false],
                'artifact_path' => 'routes.php',
                'readiness' => ['enabled' => false],
                'source_fallback' => true,
                '@override' => true,
            ] as $key => $value
        ) {
            self::assertRejected($defaults + [$key => $value], $key === '@override' ? '<key>' : $key, 'unknown-key');
        }
        foreach (['tracing', 'metrics', 'logging'] as $channel) {
            foreach (['unknown', '@override', 'reserved_guard'] as $key) {
                $invalid = $defaults;
                $invalid[$channel][$key] = false;
                self::assertRejected($invalid, $channel . '.' . ($key === '@override' ? '<key>' : $key), 'unknown-key');
            }
        }
    }

    public function testEveryChannelAndEnabledFlagAreRequiredAndStrictlyTyped(): void
    {
        $defaults = self::defaults();
        $validator = new ConfigValidator();
        self::assertTrue($validator->validate([], [self::ruleset()])->isFailure());

        foreach (['tracing', 'metrics', 'logging'] as $channel) {
            $invalid = $defaults;
            unset($invalid[$channel]);
            self::assertRejected($invalid, $channel, 'required');
            $invalid = $defaults;
            $invalid[$channel] = [];
            self::assertRejected($invalid, $channel . '.enabled', 'required');

            foreach ([false, 1, 'true', null, [true]] as $value) {
                $invalid = $defaults;
                $invalid[$channel] = $value;
                self::assertRejected($invalid, $channel, 'type');
            }
            foreach ([0, 1, 'true', 'false', '', null, [], ['value' => true], 1.5] as $value) {
                $invalid = $defaults;
                $invalid[$channel]['enabled'] = $value;
                self::assertRejected($invalid, $channel . '.enabled', 'type');
            }
        }
    }

    /** @return array<string, array<string, bool>> */
    private static function defaults(): array
    {
        return require \dirname(__DIR__, 2) . '/config/routing.php';
    }

    private static function ruleset(): ConfigRuleset
    {
        return ConfigRuleset::fromArray('routing', require \dirname(__DIR__, 2) . '/config/rules.php');
    }

    /** @param array<string, mixed> $config */
    private static function assertRejected(array $config, string $path, string $reason): void
    {
        $result = new ConfigValidator()->validate(['routing' => $config], [self::ruleset()]);
        self::assertTrue($result->isFailure());
        self::assertCount(1, $result->violations());
        self::assertSame('routing', $result->violations()[0]->root());
        self::assertSame($path, $result->violations()[0]->path());
        self::assertSame($reason, $result->violations()[0]->reason());
    }
}
