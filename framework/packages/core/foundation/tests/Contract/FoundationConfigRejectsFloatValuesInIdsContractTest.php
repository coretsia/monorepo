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

final class FoundationConfigRejectsFloatValuesInIdsContractTest extends TestCase
{
    private const string DEFAULTS_PATH = __DIR__ . '/../../config/foundation.php';

    private const string RULES_PATH = __DIR__ . '/../../config/rules.php';

    public function testRulesDeclareIdsDefaultAsOnlyTimeIdsConfigKey(): void
    {
        $rules = self::rules();

        self::assertSame('foundation', $rules['configRoot'] ?? null);
        self::assertFalse($rules['additionalKeys'] ?? true);

        self::assertArrayHasKey('keys', $rules);
        self::assertIsArray($rules['keys']);

        self::assertArrayHasKey('ids', $rules['keys']);
        self::assertArrayNotHasKey('clock', $rules['keys']);

        $ids = $rules['keys']['ids'];

        self::assertIsArray($ids);
        self::assertSame('map', $ids['type'] ?? null);
        self::assertFalse($ids['additionalKeys'] ?? true);

        self::assertArrayHasKey('keys', $ids);
        self::assertIsArray($ids['keys']);
        self::assertSame(['default'], \array_keys($ids['keys']));

        $default = $ids['keys']['default'];

        self::assertIsArray($default);
        self::assertTrue($default['required'] ?? false);
        self::assertSame('string', $default['type'] ?? null);
        self::assertSame(['ulid', 'uuid'], $default['allowedValues'] ?? null);
    }

    public function testDefaultsDeclareIdsDefaultAndNoClockConfig(): void
    {
        $defaults = self::defaults();

        self::assertArrayHasKey('ids', $defaults);
        self::assertIsArray($defaults['ids']);
        self::assertSame('ulid', $defaults['ids']['default'] ?? null);

        self::assertArrayNotHasKey('clock', $defaults);
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
    public function testInvalidIdsAndClockConfigFailDeterministicallyWithSafeMessage(
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
        yield 'ids.default float is rejected' => [
            self::configWith([
                'ids' => [
                    'default' => 1.5,
                ],
            ]),
            ['1.5'],
        ];

        yield 'ids.default NaN is rejected' => [
            self::configWith([
                'ids' => [
                    'default' => \NAN,
                ],
            ]),
            ['NAN', 'nan'],
        ];

        yield 'ids.default INF is rejected' => [
            self::configWith([
                'ids' => [
                    'default' => \INF,
                ],
            ]),
            ['INF', 'inf'],
        ];

        yield 'ids.default negative INF is rejected' => [
            self::configWith([
                'ids' => [
                    'default' => -\INF,
                ],
            ]),
            ['-INF', '-inf'],
        ];

        yield 'unknown nested ids key is rejected even when value is float' => [
            self::configWith([
                'ids' => [
                    'nested' => [
                        'value' => 1.5,
                    ],
                ],
            ]),
            ['1.5'],
        ];

        yield 'foundation.clock config is rejected because clock config is not introduced' => [
            self::configWith([
                'clock' => [
                    'driver' => 'system',
                ],
            ]),
            ['system'],
        ];

        yield 'ids.default unsupported string is rejected without dumping value' => [
            self::configWith([
                'ids' => [
                    'default' => 'snowflake',
                ],
            ]),
            ['snowflake'],
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
