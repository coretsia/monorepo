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

final class ComposerManifestReaderRejectsDuplicateModuleIdsTest extends TestCase
{
    public function testRejectsDuplicateModuleIdsAcrossDifferentInstalledComposerPackages(): void
    {
        $reader = self::readerForInstalledData([
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    'acme/first-kernel-package' => [
                        'type' => 'library',
                        'install_path' => '/tmp/first-kernel-package',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.kernel',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                    'acme/second-kernel-package' => [
                        'type' => 'library',
                        'install_path' => '/tmp/second-kernel-package',
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
                ModuleErrorCodes::CORETSIA_MODULE_MANIFEST_INVALID,
                $exception->errorCode(),
            );

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

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_MANIFEST_INVALID
                . ': '
                . ModuleManifestInvalidException::REASON_MODULE_ID_DUPLICATE,
                $exception->getMessage(),
            );

            self::assertSafeDiagnostics([
                'message' => $exception->getMessage(),
                'reason' => $exception->reason(),
                'context' => $exception->context(),
            ]);
        }
    }

    public function testDuplicateComposerPackageRecordsCollapseByComposerPackageNameBeforeModuleClassification(): void
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
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.old-kernel',
                                'kind' => 'runtime',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    'coretsia/core-kernel' => [
                        'type' => 'library',
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

        $manifest = $reader->read();

        self::assertSame(
            [
                'core.kernel',
            ],
            $manifest->ids(),
        );
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
        self::assertStringNotContainsString('install_path', $value);
        self::assertStringNotContainsString('acme/first-kernel-package', $value);
        self::assertStringNotContainsString('acme/second-kernel-package', $value);
        self::assertStringNotContainsString('coretsia/core-kernel', $value);
        self::assertStringNotContainsString("\0", $value);
        self::assertStringNotContainsString("\r", $value);
        self::assertStringNotContainsString("\n", $value);
        self::assertStringNotContainsString("\t", $value);
        self::assertStringNotContainsString('://', $value);
        self::assertStringNotContainsString('..', $value);
    }
}
