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

use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use PHPUnit\Framework\TestCase;

final class ComposerManifestReaderDoesNotLeakPathsTest extends TestCase
{
    public function testInvalidPathLikeCoretsiaMetadataDoesNotLeakOffendingPath(): void
    {
        $reader = self::readerForInstalledData([
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    'coretsia/core-kernel' => [
                        'type' => 'library',
                        'install_path' => '/tmp/coretsia/core-kernel',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.kernel',
                                'kind' => 'runtime',
                                'defaultsConfigPath' => '/tmp/secret/config/kernel.php',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        try {
            $reader->read();

            self::fail('Expected invalid Coretsia metadata to fail deterministically.');
        } catch (ModuleManifestInvalidException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_MANIFEST_INVALID,
                $exception->errorCode(),
            );

            self::assertSame(
                ModuleManifestInvalidException::REASON_CORETSIA_METADATA_INVALID,
                $exception->reason(),
            );

            self::assertSame([], $exception->context());

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_MANIFEST_INVALID
                . ': '
                . ModuleManifestInvalidException::REASON_CORETSIA_METADATA_INVALID,
                $exception->getMessage(),
            );

            self::assertSafeDiagnostics([
                'message' => $exception->getMessage(),
                'reason' => $exception->reason(),
                'context' => $exception->context(),
            ]);
        }
    }

    public function testDuplicateModuleIdDiagnosticsExposeOnlySafeModuleId(): void
    {
        $reader = self::readerForInstalledData([
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    'coretsia/core-kernel' => [
                        'type' => 'library',
                        'install_path' => '/tmp/first-package-path',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.kernel',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                    'acme/duplicate-core-kernel' => [
                        'type' => 'library',
                        'install_path' => '/tmp/second-package-path',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.kernel',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        try {
            $reader->read();

            self::fail('Expected duplicate module id to fail deterministically.');
        } catch (ModuleManifestInvalidException $exception) {
            self::assertSame(
                ModuleManifestInvalidException::REASON_MODULE_ID_DUPLICATE,
                $exception->reason(),
            );

            self::assertSame(
                [
                    'moduleId' => 'core.kernel',
                ],
                $exception->context(),
            );

            self::assertSafeDiagnostics([
                'message' => $exception->getMessage(),
                'reason' => $exception->reason(),
                'context' => $exception->context(),
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $installedData
     */
    private static function readerForInstalledData(array $installedData): ComposerManifestReader
    {
        return new ComposerManifestReader(
            new ComposerInstalledMetadataProvider($installedData),
        );
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

        self::fail('Composer manifest diagnostics must be scalar/json-like.');
    }

    private static function assertSafeDiagnosticString(string $value): void
    {
        self::assertNotSame('', $value);
        self::assertStringNotContainsString('/tmp', $value);
        self::assertStringNotContainsString('/secret', $value);
        self::assertStringNotContainsString('/config', $value);
        self::assertStringNotContainsString('/kernel.php', $value);
        self::assertStringNotContainsString('coretsia/core-kernel', $value);
        self::assertStringNotContainsString('acme/duplicate-core-kernel', $value);
        self::assertStringNotContainsString('install_path', $value);
        self::assertStringNotContainsString('defaultsConfigPath', $value);
        self::assertStringNotContainsString('raw', $value);
        self::assertStringNotContainsString("\0", $value);
        self::assertStringNotContainsString("\r", $value);
        self::assertStringNotContainsString("\n", $value);
        self::assertStringNotContainsString("\t", $value);
        self::assertStringNotContainsString('://', $value);
        self::assertStringNotContainsString('..', $value);

        self::assertFalse(
            self::looksLikeAbsoluteUnixPath($value),
            'Composer manifest diagnostics must not contain absolute Unix paths.',
        );

        self::assertFalse(
            self::looksLikeWindowsDrivePath($value),
            'Composer manifest diagnostics must not contain Windows drive paths.',
        );
    }

    private static function looksLikeAbsoluteUnixPath(string $value): bool
    {
        return \str_starts_with($value, '/');
    }

    private static function looksLikeWindowsDrivePath(string $value): bool
    {
        return \strlen($value) >= 3
            && (($value[0] >= 'a' && $value[0] <= 'z') || ($value[0] >= 'A' && $value[0] <= 'Z'))
            && $value[1] === ':'
            && ($value[2] === '\\' || $value[2] === '/');
    }
}
