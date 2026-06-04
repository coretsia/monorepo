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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModePresetSchemaValidatorRejectsOverlappingRequiredOptionalDisabledTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('overlappingPresetPayloads')]
    public function testRejectsOverlappingRequiredOptionalDisabledSets(array $payload): void
    {
        $validator = new ModePresetSchemaValidator();

        try {
            $validator->validate('micro', $payload);

            self::fail('Expected overlapping required/optional/disabled sets to fail deterministically.');
        } catch (ModePresetInvalidException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID,
                $exception->errorCode(),
            );

            self::assertSame(
                ModePresetInvalidException::REASON_SETS_OVERLAP,
                $exception->reason(),
            );

            self::assertSame(
                [
                    'preset' => 'micro',
                ],
                $exception->context(),
            );

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID
                . ': '
                . ModePresetInvalidException::REASON_SETS_OVERLAP,
                $exception->getMessage(),
            );

            self::assertSafeDiagnostics([
                'message' => $exception->getMessage(),
                'reason' => $exception->reason(),
                'context' => $exception->context(),
            ]);
        }
    }

    /**
     * @return iterable<string, array{payload: array<string, mixed>}>
     */
    public static function overlappingPresetPayloads(): iterable
    {
        yield 'required overlaps optional' => [
            'payload' => self::presetPayload(
                required: [
                    'core.foundation',
                    'core.kernel',
                ],
                optional: [
                    'core.kernel',
                    'platform.logging',
                ],
                disabled: [],
            ),
        ];

        yield 'required overlaps disabled' => [
            'payload' => self::presetPayload(
                required: [
                    'core.foundation',
                    'core.kernel',
                ],
                optional: [
                    'platform.logging',
                ],
                disabled: [
                    'core.foundation',
                ],
            ),
        ];

        yield 'optional overlaps disabled' => [
            'payload' => self::presetPayload(
                required: [
                    'core.foundation',
                    'core.kernel',
                ],
                optional: [
                    'platform.logging',
                    'platform.metrics',
                ],
                disabled: [
                    'platform.metrics',
                ],
            ),
        ];
    }

    /**
     * @param list<string> $required
     * @param list<string> $optional
     * @param list<string> $disabled
     *
     * @return array<string, mixed>
     */
    private static function presetPayload(
        array $required,
        array $optional,
        array $disabled,
    ): array {
        return [
            'schemaVersion' => 1,
            'name' => 'micro',
            'description' => 'Micro test mode.',
            'required' => $required,
            'optional' => $optional,
            'disabled' => $disabled,
            'featureBundles' => [],
            'metadata' => [],
        ];
    }

    /**
     * @param array<string, mixed> $diagnostics
     */
    private static function assertSafeDiagnostics(array $diagnostics): void
    {
        self::assertSafeDiagnosticValue($diagnostics);
    }

    private static function assertSafeDiagnosticValue(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return;
        }

        if (\is_string($value)) {
            self::assertSafeDiagnosticString($value);

            return;
        }

        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                if (!\array_is_list($value)) {
                    self::assertIsString($key);
                    self::assertSafeDiagnosticString($key);
                }

                self::assertSafeDiagnosticValue($item);
            }

            return;
        }

        self::fail('Mode preset diagnostics must be scalar/json-like.');
    }

    private static function assertSafeDiagnosticString(string $value): void
    {
        self::assertNotSame('', $value);
        self::assertStringNotContainsString('/', $value);
        self::assertStringNotContainsString('\\', $value);
        self::assertStringNotContainsString("\0", $value);
        self::assertStringNotContainsString("\r", $value);
        self::assertStringNotContainsString("\n", $value);
        self::assertStringNotContainsString("\t", $value);
        self::assertStringNotContainsString('://', $value);
        self::assertStringNotContainsString('..', $value);
    }
}
