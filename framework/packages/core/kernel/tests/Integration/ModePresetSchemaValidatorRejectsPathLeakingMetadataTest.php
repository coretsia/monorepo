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

final class ModePresetSchemaValidatorRejectsPathLeakingMetadataTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('pathLikePresetPayloads')]
    public function testRejectsPathLikeStringsWithoutLeakingOffendingValue(array $payload): void
    {
        $validator = new ModePresetSchemaValidator();

        try {
            $validator->validate('micro', $payload);

            self::fail('Expected path-like preset metadata to fail deterministically.');
        } catch (ModePresetInvalidException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID,
                $exception->errorCode(),
            );

            self::assertSame(
                ModePresetInvalidException::REASON_PATH_LIKE_VALUE_FORBIDDEN,
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
                . ModePresetInvalidException::REASON_PATH_LIKE_VALUE_FORBIDDEN,
                $exception->getMessage(),
            );

            self::assertDiagnosticsDoNotLeakPathLikeValues($exception);
        }
    }

    /**
     * @return iterable<string, array{payload: array<string, mixed>}>
     */
    public static function pathLikePresetPayloads(): iterable
    {
        yield 'metadata absolute unix path value' => [
            'payload' => self::presetPayload(
                metadata: [
                    'safeKey' => '/tmp/coretsia/secret/kernel.php',
                ],
            ),
        ];

        yield 'metadata absolute windows path value' => [
            'payload' => self::presetPayload(
                metadata: [
                    'safeKey' => 'C:\\Users\\Vladyslav\\secret\\kernel.php',
                ],
            ),
        ];

        yield 'metadata stream wrapper value' => [
            'payload' => self::presetPayload(
                metadata: [
                    'safeKey' => 'php://filter/resource=secret',
                ],
            ),
        ];

        yield 'metadata traversal value' => [
            'payload' => self::presetPayload(
                metadata: [
                    'safeKey' => '../secret/kernel.php',
                ],
            ),
        ];

        yield 'metadata nested path-like value' => [
            'payload' => self::presetPayload(
                metadata: [
                    'safeKey' => [
                        'nested' => '/var/app/skeleton/config/modes/micro.php',
                    ],
                ],
            ),
        ];

        yield 'feature bundle path-like value' => [
            'payload' => self::presetPayload(
                featureBundles: [
                    'observability' => [
                        'path' => '/var/app/skeleton/config/modes/micro.php',
                    ],
                ],
            ),
        ];

        yield 'feature bundle path-like key' => [
            'payload' => self::presetPayload(
                featureBundles: [
                    '/var/app/skeleton/config/modes/micro.php' => 'minimal',
                ],
            ),
        ];

        yield 'metadata path-like key' => [
            'payload' => self::presetPayload(
                metadata: [
                    '/tmp/coretsia/secret/kernel.php' => 'safe-value',
                ],
            ),
        ];
    }

    /**
     * @param array<string, mixed> $featureBundles
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function presetPayload(
        array $featureBundles = [],
        array $metadata = [],
    ): array {
        return [
            'schemaVersion' => 1,
            'name' => 'micro',
            'description' => 'Micro test mode.',
            'required' => [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            'optional' => [],
            'disabled' => [],
            'featureBundles' => $featureBundles,
            'metadata' => $metadata,
        ];
    }

    private static function assertDiagnosticsDoNotLeakPathLikeValues(ModePresetInvalidException $exception): void
    {
        $diagnostics = [
            'message' => $exception->getMessage(),
            'reason' => $exception->reason(),
            'context' => $exception->context(),
        ];

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
        self::assertStringNotContainsString('/tmp', $value);
        self::assertStringNotContainsString('/var', $value);
        self::assertStringNotContainsString('/app', $value);
        self::assertStringNotContainsString('/skeleton', $value);
        self::assertStringNotContainsString('/config', $value);
        self::assertStringNotContainsString('/modes', $value);
        self::assertStringNotContainsString('/micro.php', $value);
        self::assertStringNotContainsString('C:\\', $value);
        self::assertStringNotContainsString('Users\\', $value);
        self::assertStringNotContainsString('secret', $value);
        self::assertStringNotContainsString('kernel.php', $value);
        self::assertStringNotContainsString('php://', $value);
        self::assertStringNotContainsString('filter', $value);
        self::assertStringNotContainsString('resource', $value);
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
