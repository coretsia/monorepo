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

namespace Coretsia\Foundation\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FoundationEnhancedResetConfigShapeContractTest extends TestCase
{
    private const string DEFAULTS_PATH = __DIR__ . '/../../config/foundation.php';

    private const string RULES_PATH = __DIR__ . '/../../config/rules.php';

    private const string RESET_GROUP_ID_PATTERN = '/\A[a-z0-9][a-z0-9._-]*\z/';

    public function testDefaultsReturnFoundationSubtreeWithoutRootWrapping(): void
    {
        $defaults = self::defaults();

        self::assertArrayNotHasKey('foundation', $defaults);
        self::assertArrayHasKey('reset', $defaults);
        self::assertIsArray($defaults['reset']);
    }

    public function testRulesDeclareEnhancedResetConfigShape(): void
    {
        $rules = self::rules();

        self::assertSame('foundation', $rules['configRoot'] ?? null);
        self::assertFalse($rules['additionalKeys'] ?? true);

        self::assertArrayHasKey('keys', $rules);
        self::assertIsArray($rules['keys']);
        self::assertArrayHasKey('reset', $rules['keys']);

        $reset = $rules['keys']['reset'];

        self::assertIsArray($reset);
        self::assertSame('map', $reset['type'] ?? null);
        self::assertTrue($reset['required'] ?? false);
        self::assertFalse($reset['additionalKeys'] ?? true);

        self::assertArrayHasKey('keys', $reset);
        self::assertIsArray($reset['keys']);

        self::assertArrayHasKey('tag', $reset['keys']);
        self::assertArrayHasKey('priority', $reset['keys']);
        self::assertArrayHasKey('group', $reset['keys']);

        $priority = $reset['keys']['priority'];

        self::assertIsArray($priority);
        self::assertSame('map', $priority['type'] ?? null);
        self::assertTrue($priority['required'] ?? false);
        self::assertFalse($priority['additionalKeys'] ?? true);

        self::assertArrayHasKey('keys', $priority);
        self::assertIsArray($priority['keys']);
        self::assertSame(['enabled'], \array_keys($priority['keys']));

        $priorityEnabled = $priority['keys']['enabled'];

        self::assertIsArray($priorityEnabled);
        self::assertTrue($priorityEnabled['required'] ?? false);
        self::assertSame('bool', $priorityEnabled['type'] ?? null);

        $group = $reset['keys']['group'];

        self::assertIsArray($group);
        self::assertSame('map', $group['type'] ?? null);
        self::assertTrue($group['required'] ?? false);
        self::assertFalse($group['additionalKeys'] ?? true);

        self::assertArrayHasKey('keys', $group);
        self::assertIsArray($group['keys']);
        self::assertSame(['default'], \array_keys($group['keys']));

        $groupDefault = $group['keys']['default'];

        self::assertIsArray($groupDefault);
        self::assertTrue($groupDefault['required'] ?? false);
        self::assertSame('reset-group-id', $groupDefault['type'] ?? null);
    }

    public function testDefaultsDeclareEnhancedResetValues(): void
    {
        $defaults = self::defaults();

        self::assertSame('kernel.reset', $defaults['reset']['tag'] ?? null);
        self::assertSame(true, $defaults['reset']['priority']['enabled'] ?? null);
        self::assertSame('default', $defaults['reset']['group']['default'] ?? null);

        self::assertMatchesRegularExpression(
            self::RESET_GROUP_ID_PATTERN,
            $defaults['reset']['group']['default'],
        );
    }

    public function testDefaultConfigMatchesRules(): void
    {
        $this->assertConfigAccepted(self::defaults());
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $forbiddenMessageFragments
     */
    #[DataProvider('invalidConfigProvider')]
    public function testInvalidEnhancedResetConfigFailsDeterministicallyWithSafeMessage(
        array $config,
        array $forbiddenMessageFragments,
    ): void {
        self::assertConfigRejected($config, $forbiddenMessageFragments);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function invalidConfigProvider(): iterable
    {
        yield 'root wrapping is rejected' => [
            [
                'foundation' => self::defaults(),
            ],
            ['ulid', 'kernel.reset', 'default'],
        ];

        yield 'unknown root key is rejected' => [
            self::configWith([
                'clock' => [
                    'driver' => 'system',
                ],
            ]),
            ['system'],
        ];

        yield 'unknown reset key is rejected' => [
            self::configWith([
                'reset' => [
                    'enabled' => false,
                ],
            ]),
            ['false'],
        ];

        yield 'unknown reset priority key is rejected' => [
            self::configWith([
                'reset' => [
                    'priority' => [
                        'mode' => 'enhanced',
                    ],
                ],
            ]),
            ['enhanced'],
        ];

        yield 'unknown reset group key is rejected' => [
            self::configWith([
                'reset' => [
                    'group' => [
                        'fallback' => 'default',
                    ],
                ],
            ]),
            ['default'],
        ];

        yield 'priority enabled string is rejected' => [
            self::configWith([
                'reset' => [
                    'priority' => [
                        'enabled' => 'true',
                    ],
                ],
            ]),
            ['true'],
        ];

        yield 'priority enabled int is rejected' => [
            self::configWith([
                'reset' => [
                    'priority' => [
                        'enabled' => 1,
                    ],
                ],
            ]),
            ['1'],
        ];

        yield 'group default empty string is rejected' => [
            self::configWith([
                'reset' => [
                    'group' => [
                        'default' => '',
                    ],
                ],
            ]),
            ['""'],
        ];

        yield 'group default ASCII whitespace is rejected' => [
            self::configWith([
                'reset' => [
                    'group' => [
                        'default' => " \t\n",
                    ],
                ],
            ]),
            [" \t\n"],
        ];

        yield 'group default uppercase is rejected' => [
            self::configWith([
                'reset' => [
                    'group' => [
                        'default' => 'Default',
                    ],
                ],
            ]),
            ['Default'],
        ];

        yield 'group default leading dash is rejected' => [
            self::configWith([
                'reset' => [
                    'group' => [
                        'default' => '-default',
                    ],
                ],
            ]),
            ['-default'],
        ];

        yield 'group default invalid characters are rejected' => [
            self::configWith([
                'reset' => [
                    'group' => [
                        'default' => 'default/group',
                    ],
                ],
            ]),
            ['default/group'],
        ];

        yield 'group default float is rejected' => [
            self::configWith([
                'reset' => [
                    'group' => [
                        'default' => 1.5,
                    ],
                ],
            ]),
            ['1.5'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function assertConfigAccepted(array $config): void
    {
        self::validateMap($config, self::rules(), 'foundation');

        $this->addToAssertionCount(1);
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $forbiddenMessageFragments
     */
    private static function assertConfigRejected(
        array $config,
        array $forbiddenMessageFragments,
    ): void {
        try {
            self::validateMap($config, self::rules(), 'foundation');
            self::fail('Expected Foundation config validation to fail.');
        } catch (\RuntimeException $exception) {
            $message = $exception->getMessage();

            self::assertStringStartsWith('foundation-config-invalid: ', $message);
            self::assertMatchesRegularExpression(
                '/\Afoundation-config-invalid: foundation(?:\.[a-z_][a-z0-9_]*)* [a-z0-9-]+\z/',
                $message,
            );

            foreach ($forbiddenMessageFragments as $fragment) {
                self::assertStringNotContainsString($fragment, $message);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        $defaults = require self::DEFAULTS_PATH;

        if (!\is_array($defaults)) {
            throw new \RuntimeException('foundation-defaults-not-array');
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    private static function rules(): array
    {
        $rules = require self::RULES_PATH;

        if (!\is_array($rules)) {
            throw new \RuntimeException('foundation-rules-not-array');
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function configWith(array $overrides): array
    {
        return \array_replace_recursive(self::defaults(), $overrides);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private static function validateValue(mixed $value, array $rule, string $path): void
    {
        if (\is_float($value)) {
            self::reject($path, 'float-forbidden');
        }

        $type = $rule['type'] ?? null;

        match ($type) {
            'map' => self::validateMap($value, $rule, $path),
            'bool' => self::validateBool($value, $path),
            'string' => self::validateString($value, $rule, $path),
            'non-empty-string-no-ws' => self::validateNonEmptyStringNoWhitespace($value, $path),
            'reset-group-id' => self::validateResetGroupId($value, $path),
            default => self::reject($path, 'rule-type-unknown'),
        };
    }

    /**
     * @param array<string, mixed> $rule
     */
    private static function validateMap(mixed $value, array $rule, string $path): void
    {
        if (!\is_array($value)) {
            self::reject($path, 'type-map');
        }

        $children = $rule['keys'] ?? [];

        if (!\is_array($children)) {
            self::reject($path, 'rule-keys-invalid');
        }

        if (($rule['additionalKeys'] ?? true) === false) {
            foreach (\array_keys($value) as $key) {
                if (!\is_string($key)) {
                    self::reject($path, 'map-key-invalid');
                }

                if (!\array_key_exists($key, $children)) {
                    self::reject($path . '.' . $key, 'unknown-key');
                }
            }
        }

        foreach ($children as $key => $childRule) {
            if (!\is_string($key) || !\is_array($childRule)) {
                self::reject($path, 'rule-child-invalid');
            }

            if (($childRule['required'] ?? false) === true && !\array_key_exists($key, $value)) {
                self::reject($path . '.' . $key, 'required');
            }

            if (\array_key_exists($key, $value)) {
                self::validateValue($value[$key], $childRule, $path . '.' . $key);
            }
        }
    }

    private static function validateBool(mixed $value, string $path): void
    {
        if (!\is_bool($value)) {
            self::reject($path, 'type-bool');
        }
    }

    /**
     * @param array<string, mixed> $rule
     */
    private static function validateString(mixed $value, array $rule, string $path): void
    {
        if (!\is_string($value)) {
            self::reject($path, 'type-string');
        }

        $allowedValues = $rule['allowedValues'] ?? null;

        if ($allowedValues !== null) {
            if (!\is_array($allowedValues)) {
                self::reject($path, 'rule-allowed-values-invalid');
            }

            if (!\in_array($value, $allowedValues, true)) {
                self::reject($path, 'allowed-value');
            }
        }
    }

    private static function validateNonEmptyStringNoWhitespace(mixed $value, string $path): void
    {
        if (!\is_string($value)) {
            self::reject($path, 'type-string');
        }

        if ($value === '') {
            self::reject($path, 'string-empty');
        }

        if (\preg_match('/\s/u', $value) === 1) {
            self::reject($path, 'string-whitespace');
        }
    }

    private static function validateResetGroupId(mixed $value, string $path): void
    {
        if (!\is_string($value)) {
            self::reject($path, 'type-string');
        }

        if (\preg_match(self::RESET_GROUP_ID_PATTERN, $value) !== 1) {
            self::reject($path, 'reset-group-id');
        }
    }

    private static function reject(string $path, string $reason): never
    {
        throw new \RuntimeException(
            \sprintf(
                'foundation-config-invalid: %s %s',
                $path,
                $reason,
            )
        );
    }
}
