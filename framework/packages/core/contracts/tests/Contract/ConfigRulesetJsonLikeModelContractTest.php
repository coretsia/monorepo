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

use Coretsia\Contracts\Config\ConfigRuleset;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigRulesetJsonLikeModelContractTest extends TestCase
{
    public function test_ruleset_accepts_json_like_declarative_rules_data(): void
    {
        $ruleset = new ConfigRuleset(
            'foundation',
            [
                'zeta' => [
                    'type' => 'list',
                    'required' => false,
                    'items' => [
                        'alpha',
                        'beta',
                    ],
                ],
                'alpha' => [
                    'type' => 'map',
                    'required' => true,
                    'nullable' => false,
                    'default' => null,
                    'limits' => [
                        'min' => 1,
                        'enabled' => true,
                    ],
                ],
            ],
        );

        self::assertSame('foundation', $ruleset->root());

        self::assertSame(
            [
                'alpha' => [
                    'default' => null,
                    'limits' => [
                        'enabled' => true,
                        'min' => 1,
                    ],
                    'nullable' => false,
                    'required' => true,
                    'type' => 'map',
                ],
                'zeta' => [
                    'items' => [
                        'alpha',
                        'beta',
                    ],
                    'required' => false,
                    'type' => 'list',
                ],
            ],
            $ruleset->rules(),
        );

        self::assertSame(
            [
                'root' => 'foundation',
                'rules' => $ruleset->rules(),
            ],
            $ruleset->toArray(),
        );
    }

    public function test_ruleset_map_keys_are_sorted_deterministically_at_every_map_level(): void
    {
        $ruleset = new ConfigRuleset(
            'foundation',
            [
                'z' => [
                    'z' => true,
                    'a' => true,
                    'm' => [
                        'z' => 3,
                        'a' => 1,
                    ],
                ],
                'a' => [
                    'z' => true,
                    'a' => true,
                ],
            ],
        );

        self::assertSame(['a', 'z'], array_keys($ruleset->rules()));
        self::assertSame(['a', 'z'], array_keys($ruleset->rules()['a']));
        self::assertSame(['a', 'm', 'z'], array_keys($ruleset->rules()['z']));
        self::assertSame(['a', 'z'], array_keys($ruleset->rules()['z']['m']));
    }

    public function test_ruleset_lists_preserve_declared_order(): void
    {
        $ruleset = new ConfigRuleset(
            'foundation',
            [
                'container' => [
                    'allowed' => [
                        'third',
                        'first',
                        'second',
                    ],
                ],
            ],
        );

        self::assertSame(
            [
                'third',
                'first',
                'second',
            ],
            $ruleset->rules()['container']['allowed'],
        );
    }

    public function test_empty_ruleset_is_valid_empty_declarative_map(): void
    {
        $ruleset = ConfigRuleset::fromArray('foundation', []);

        self::assertSame('foundation', $ruleset->root());
        self::assertSame([], $ruleset->rules());
        self::assertSame(
            [
                'root' => 'foundation',
                'rules' => [],
            ],
            $ruleset->toArray(),
        );
    }

    #[DataProvider('forbiddenFloatValues')]
    public function test_float_values_are_forbidden_at_contract_boundary(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid float config ruleset value at rules.invalid.');

        new ConfigRuleset(
            'foundation',
            [
                'invalid' => $value,
            ],
        );
    }

    #[DataProvider('forbiddenNestedFloatValues')]
    public function test_nested_float_values_are_forbidden_at_any_depth(array $rules, string $expectedPath): void
    {
        try {
            new ConfigRuleset('foundation', $rules);

            self::fail('Expected nested float value to be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString(
                'Invalid float config ruleset value at ' . $expectedPath . '.',
                $exception->getMessage(),
            );

            self::assertStringNotContainsString('1.25', $exception->getMessage());
            self::assertStringNotContainsString('INF', $exception->getMessage());
            self::assertStringNotContainsString('NAN', $exception->getMessage());
        }
    }

    #[DataProvider('forbiddenRuntimeValues')]
    public function test_runtime_or_executable_values_are_forbidden_in_ruleset_data(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid config ruleset value at rules.invalid.');

        new ConfigRuleset(
            'foundation',
            [
                'invalid' => $value,
            ],
        );
    }

    public function test_resource_values_are_forbidden_in_ruleset_data(): void
    {
        $resource = fopen('php://memory', 'rb');

        if ($resource === false) {
            self::fail('Unable to create in-memory resource for test.');
        }

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid config ruleset value at rules.invalid.');

            new ConfigRuleset(
                'foundation',
                [
                    'invalid' => $resource,
                ],
            );
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function test_root_rules_must_be_a_map_not_a_non_empty_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config ruleset root rules must be a map.');

        new ConfigRuleset('foundation', ['not-a-map']);
    }

    public function test_map_keys_must_be_non_empty_strings_without_control_bytes(): void
    {
        foreach (
            [
                [1 => 'value'],
                ['' => 'value'],
                ["line\nbreak" => 'value'],
                ["line\rbreak" => 'value'],
                ["null\0byte" => 'value'],
            ] as $rules
        ) {
            try {
                new ConfigRuleset('foundation', $rules);

                self::fail('Expected invalid config ruleset key to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('Invalid config ruleset key at rules.', $exception->getMessage());
            }
        }
    }

    public function test_root_must_be_lowercase_config_root_identifier(): void
    {
        foreach (['', ' ', 'Foundation', 'foundation-root', 'foundation.root', '1foundation'] as $root) {
            try {
                new ConfigRuleset($root, []);

                self::fail('Expected invalid ruleset root to be rejected: ' . var_export($root, true));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('config ruleset root', strtolower($exception->getMessage()));
            }
        }
    }

    public function test_exported_ruleset_shape_is_json_like_without_floats_objects_resources_or_callables(): void
    {
        $ruleset = new ConfigRuleset(
            'foundation',
            [
                'container' => [
                    'type' => 'map',
                    'required' => true,
                    'keys' => [
                        'bindings',
                        'aliases',
                    ],
                ],
            ],
        );

        self::assertJsonLikeWithoutForbiddenRuntimeValues($ruleset->toArray());
    }

    /**
     * @return iterable<string,array{0:mixed}>
     */
    public static function forbiddenFloatValues(): iterable
    {
        yield 'float' => [1.25];
        yield 'nan' => [\NAN];
        yield 'positive-infinity' => [\INF];
        yield 'negative-infinity' => [-\INF];
    }

    /**
     * @return iterable<string,array{0:array<string,mixed>,1:string}>
     */
    public static function forbiddenNestedFloatValues(): iterable
    {
        yield 'nested-map-float' => [
            [
                'container' => [
                    'limits' => [
                        'ratio' => 1.25,
                    ],
                ],
            ],
            'rules.container.limits.ratio',
        ];

        yield 'nested-list-float' => [
            [
                'container' => [
                    'allowed' => [
                        'safe',
                        1.25,
                    ],
                ],
            ],
            'rules.container.allowed[]',
        ];

        yield 'deep-nan' => [
            [
                'container' => [
                    'limits' => [
                        'ratio' => \NAN,
                    ],
                ],
            ],
            'rules.container.limits.ratio',
        ];

        yield 'deep-positive-infinity' => [
            [
                'container' => [
                    'limits' => [
                        'ratio' => \INF,
                    ],
                ],
            ],
            'rules.container.limits.ratio',
        ];

        yield 'deep-negative-infinity' => [
            [
                'container' => [
                    'limits' => [
                        'ratio' => -\INF,
                    ],
                ],
            ],
            'rules.container.limits.ratio',
        ];
    }

    /**
     * @return iterable<string,array{0:mixed}>
     */
    public static function forbiddenRuntimeValues(): iterable
    {
        yield 'object' => [new \stdClass()];
        yield 'closure' => [static fn (): null => null];
        yield 'callable-array' => [[self::class, 'forbiddenRuntimeValues']];
    }

    private static function assertJsonLikeWithoutForbiddenRuntimeValues(mixed $value): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }

        self::assertFalse(is_float($value), 'Float values are forbidden in exported config ruleset shapes.');
        self::assertFalse(is_object($value), 'Objects are forbidden in exported config ruleset shapes.');
        self::assertFalse(is_resource($value), 'Resources are forbidden in exported config ruleset shapes.');
        self::assertFalse(is_callable($value), 'Callables are forbidden in exported config ruleset shapes.');

        self::assertIsArray($value);

        if (array_is_list($value)) {
            foreach ($value as $item) {
                self::assertJsonLikeWithoutForbiddenRuntimeValues($item);
            }

            return;
        }

        $keys = array_keys($value);

        foreach ($keys as $key) {
            self::assertIsString($key);
        }

        $sortedKeys = $keys;
        sort($sortedKeys, \SORT_STRING);

        self::assertSame($sortedKeys, $keys);

        foreach ($value as $item) {
            self::assertJsonLikeWithoutForbiddenRuntimeValues($item);
        }
    }
}
