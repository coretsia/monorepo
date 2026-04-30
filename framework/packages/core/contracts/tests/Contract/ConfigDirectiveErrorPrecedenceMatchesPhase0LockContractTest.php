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

use Coretsia\Contracts\Config\ConfigDirective;
use PHPUnit\Framework\TestCase;

final class ConfigDirectiveErrorPrecedenceMatchesPhase0LockContractTest extends TestCase
{
    private const string CONFIG_DIRECTIVE_UNKNOWN = 'CONFIG_DIRECTIVE_UNKNOWN';
    private const string CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION = 'CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION';
    private const string CONFIG_DIRECTIVE_INVALID_PAYLOAD = 'CONFIG_DIRECTIVE_INVALID_PAYLOAD';
    private const string CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE = 'CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE';

    /**
     * @var list<string>
     */
    private const array PHASE0_ALIGNED_ERROR_PRECEDENCE = [
        self::CONFIG_DIRECTIVE_UNKNOWN,
        self::CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION,
        self::CONFIG_DIRECTIVE_INVALID_PAYLOAD,
        self::CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE,
    ];

    /**
     * @var array<string,string>
     */
    private const array PHASE0_ERROR_CODE_MAPPING = [
        self::CONFIG_DIRECTIVE_UNKNOWN => 'CORETSIA_CONFIG_RESERVED_NAMESPACE_USED',
        self::CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION => 'CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL',
        self::CONFIG_DIRECTIVE_INVALID_PAYLOAD => 'CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH',
        self::CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE => 'CORETSIA_JSON_FLOAT_FORBIDDEN',
    ];

    public function test_phase0_aligned_directive_error_precedence_is_locked(): void
    {
        self::assertSame(
            [
                'CONFIG_DIRECTIVE_UNKNOWN',
                'CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION',
                'CONFIG_DIRECTIVE_INVALID_PAYLOAD',
                'CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE',
            ],
            self::PHASE0_ALIGNED_ERROR_PRECEDENCE,
        );
    }

    public function test_contract_codes_map_to_phase0_lock_source_codes(): void
    {
        self::assertSame(
            [
                'CONFIG_DIRECTIVE_UNKNOWN' => 'CORETSIA_CONFIG_RESERVED_NAMESPACE_USED',
                'CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION' => 'CORETSIA_CONFIG_DIRECTIVE_MIXED_LEVEL',
                'CONFIG_DIRECTIVE_INVALID_PAYLOAD' => 'CORETSIA_CONFIG_DIRECTIVE_TYPE_MISMATCH',
                'CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE' => 'CORETSIA_JSON_FLOAT_FORBIDDEN',
            ],
            self::PHASE0_ERROR_CODE_MAPPING,
        );
    }

    public function test_validation_codes_are_stable_ascii_strings(): void
    {
        foreach (self::PHASE0_ALIGNED_ERROR_PRECEDENCE as $code) {
            self::assertMatchesRegularExpression('/^[A-Z][A-Z0-9_]*$/', $code);
            self::assertStringNotContainsString('@', $code);
            self::assertStringNotContainsString('/', $code);
            self::assertStringNotContainsString('\\', $code);
            self::assertStringNotContainsString(':', $code);
        }
    }

    public function test_unknown_reserved_directive_key_is_reported_before_exclusive_level_violation(): void
    {
        $node = [
            '@custom' => true,
            '@merge' => [
                'a' => 1,
            ],
            'regular' => true,
        ];

        self::assertSame(self::CONFIG_DIRECTIVE_UNKNOWN, self::firstDirectiveProblemCode($node));
    }

    public function test_unknown_reserved_directive_key_is_the_single_reserved_namespace_guard_bucket(): void
    {
        foreach (['@set', '@delete', '@override', '@custom', '@env', '@'] as $key) {
            self::assertTrue(ConfigDirective::isReservedDirectiveKey($key));
            self::assertFalse(ConfigDirective::isAllowedKey($key));

            self::assertSame(
                self::CONFIG_DIRECTIVE_UNKNOWN,
                self::firstDirectiveProblemCode([
                    $key => true,
                ]),
            );
        }
    }

    public function test_exclusive_level_violation_is_reported_before_payload_shape_violation(): void
    {
        $node = [
            '@merge' => new \stdClass(),
            'regular' => true,
        ];

        self::assertSame(self::CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION, self::firstDirectiveProblemCode($node));
    }

    public function test_payload_shape_violation_is_reported_before_json_like_value_violation(): void
    {
        $node = [
            '@append' => 'not-a-list-or-map-payload',
        ];

        self::assertSame(self::CONFIG_DIRECTIVE_INVALID_PAYLOAD, self::firstDirectiveProblemCode($node));
    }

    public function test_json_like_value_violation_is_reported_after_payload_shape_is_valid(): void
    {
        $node = [
            '@merge' => [
                'ratio' => 1.5,
            ],
        ];

        self::assertSame(self::CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE, self::firstDirectiveProblemCode($node));
    }

    public function test_replace_accepts_scalar_payload_shape_before_json_like_validation(): void
    {
        self::assertNull(self::firstDirectiveProblemCode([
            '@replace' => false,
        ]));

        self::assertNull(self::firstDirectiveProblemCode([
            '@replace' => 'disabled',
        ]));
    }

    public function test_merge_rejects_non_empty_list_payload_shape(): void
    {
        self::assertSame(
            self::CONFIG_DIRECTIVE_INVALID_PAYLOAD,
            self::firstDirectiveProblemCode([
                '@merge' => ['not-a-map'],
            ]),
        );
    }

    /**
     * Test-only classifier for the contract lock.
     *
     * It is intentionally not a merge implementation and does not define runtime
     * validation behavior. It exists only to make the Phase 0 precedence order
     * executable as contract evidence.
     *
     * @param array<mixed> $node
     */
    private static function firstDirectiveProblemCode(array $node): ?string
    {
        foreach ($node as $key => $_value) {
            if (!is_string($key)) {
                continue;
            }

            if (ConfigDirective::isReservedDirectiveKey($key) && !ConfigDirective::isAllowedKey($key)) {
                return self::CONFIG_DIRECTIVE_UNKNOWN;
            }
        }

        $directiveKeys = [];

        foreach ($node as $key => $_value) {
            if (!is_string($key)) {
                continue;
            }

            if (ConfigDirective::isAllowedKey($key)) {
                $directiveKeys[] = $key;
            }
        }

        if ($directiveKeys !== [] && count($node) !== 1) {
            return self::CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION;
        }

        if (count($directiveKeys) > 1) {
            return self::CONFIG_DIRECTIVE_EXCLUSIVE_LEVEL_VIOLATION;
        }

        if ($directiveKeys === []) {
            return null;
        }

        $directiveKey = $directiveKeys[0];
        $payload = $node[$directiveKey];

        if (!self::hasValidDirectivePayloadShape($directiveKey, $payload)) {
            return self::CONFIG_DIRECTIVE_INVALID_PAYLOAD;
        }

        if (!self::isJsonLikeValue($payload)) {
            return self::CONFIG_DIRECTIVE_INVALID_JSON_LIKE_VALUE;
        }

        return null;
    }

    private static function hasValidDirectivePayloadShape(string $directiveKey, mixed $payload): bool
    {
        if ($directiveKey === '@replace') {
            return true;
        }

        if (!is_array($payload)) {
            return false;
        }

        if ($directiveKey === '@append' || $directiveKey === '@prepend' || $directiveKey === '@remove') {
            return array_is_list($payload);
        }

        if ($directiveKey === '@merge') {
            return $payload === [] || !array_is_list($payload);
        }

        return false;
    }

    private static function isJsonLikeValue(mixed $value): bool
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return true;
        }

        if (is_float($value)) {
            return false;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (!is_int($key) && !is_string($key)) {
                return false;
            }

            if (!self::isJsonLikeValue($item)) {
                return false;
            }
        }

        return true;
    }
}
