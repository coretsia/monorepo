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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ComposerManifestReaderRejectsInvalidCoretsiaMetadataTest extends TestCase
{
    /**
     * @param array<string, mixed> $coretsia
     * @param array<string, mixed> $expectedContext
     */
    #[DataProvider('invalidCoretsiaMetadataCases')]
    public function testRejectsInvalidCoretsiaMetadata(
        array $coretsia,
        string $expectedReason,
        array $expectedContext,
    ): void {
        $reader = self::readerForCoretsiaMetadata($coretsia);

        try {
            $reader->read();

            self::fail('Expected invalid Coretsia metadata to fail deterministically.');
        } catch (ModuleManifestInvalidException $exception) {
            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_MANIFEST_INVALID,
                $exception->errorCode(),
            );

            self::assertSame($expectedReason, $exception->reason());
            self::assertSame($expectedContext, $exception->context());

            self::assertSame(
                ModuleErrorCodes::CORETSIA_MODULE_MANIFEST_INVALID . ': ' . $expectedReason,
                $exception->getMessage(),
            );

            self::assertSafeDiagnostics([
                'message' => $exception->getMessage(),
                'reason' => $exception->reason(),
                'context' => $exception->context(),
            ]);
        }
    }

    public function testRejectsNonMapCoretsiaMetadata(): void
    {
        $reader = self::readerForRawCoretsiaMetadata([
            'not',
            'a',
            'map',
        ]);

        try {
            $reader->read();

            self::fail('Expected non-map Coretsia metadata to fail deterministically.');
        } catch (ModuleManifestInvalidException $exception) {
            self::assertSame(
                ModuleManifestInvalidException::REASON_CORETSIA_METADATA_INVALID,
                $exception->reason(),
            );

            self::assertSame([], $exception->context());
            self::assertSafeDiagnostics([
                'message' => $exception->getMessage(),
                'reason' => $exception->reason(),
                'context' => $exception->context(),
            ]);
        }
    }

    /**
     * @return iterable<string, array{
     *     coretsia: array<string, mixed>,
     *     expectedReason: string,
     *     expectedContext: array<string, mixed>
     * }>
     */
    public static function invalidCoretsiaMetadataCases(): iterable
    {
        yield 'runtime kind without moduleId is invalid' => [
            'coretsia' => [
                'kind' => 'runtime',
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_CORETSIA_METADATA_INVALID,
            'expectedContext' => [],
        ];

        yield 'invalid module id is invalid' => [
            'coretsia' => [
                'moduleId' => 'invalid-module-id',
                'kind' => 'runtime',
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_CORETSIA_METADATA_INVALID,
            'expectedContext' => [],
        ];

        yield 'missing kind is invalid' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_KIND_INVALID,
            'expectedContext' => [],
        ];

        yield 'non-runtime kind is invalid when moduleId is present' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'tooling',
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_KIND_INVALID,
            'expectedContext' => [],
        ];

        yield 'unsafe module class is invalid' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'moduleClass' => "Coretsia\\Kernel\\KernelModule\n",
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_MODULE_CLASS_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'providers must be a list' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'providers' => [
                    'main' => 'Coretsia\\Kernel\\Provider\\KernelServiceProvider',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_PROVIDERS_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'providers must contain safe strings' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'providers' => [
                    'Coretsia\\Kernel\\Provider\\KernelServiceProvider',
                    '',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_PROVIDERS_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'defaults config path must be relative safe path' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'defaultsConfigPath' => '/tmp/secret/config/kernel.php',
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_CORETSIA_METADATA_INVALID,
            'expectedContext' => [],
        ];

        yield 'requires must be a list' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'requires' => [
                    'foundation' => 'core.foundation',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_DEPENDENCY_METADATA_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'requires items must be valid module ids' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'requires' => [
                    'invalid-module-id',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_DEPENDENCY_METADATA_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'conflicts must be a list' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'conflicts' => [
                    'http' => 'platform.http',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_DEPENDENCY_METADATA_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'conflicts items must be valid module ids' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'conflicts' => [
                    'invalid-module-id',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_DEPENDENCY_METADATA_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'module must not require itself' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'requires' => [
                    'core.kernel',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_DEPENDENCY_METADATA_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'module must not conflict with itself' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'conflicts' => [
                    'core.kernel',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_DEPENDENCY_METADATA_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];

        yield 'requires and conflicts must be disjoint' => [
            'coretsia' => [
                'moduleId' => 'core.kernel',
                'kind' => 'runtime',
                'requires' => [
                    'platform.http',
                ],
                'conflicts' => [
                    'platform.http',
                ],
            ],
            'expectedReason' => ModuleManifestInvalidException::REASON_DEPENDENCY_METADATA_INVALID,
            'expectedContext' => [
                'moduleId' => 'core.kernel',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $coretsia
     */
    private static function readerForCoretsiaMetadata(array $coretsia): ComposerManifestReader
    {
        return self::readerForRawCoretsiaMetadata($coretsia);
    }

    private static function readerForRawCoretsiaMetadata(mixed $coretsia): ComposerManifestReader
    {
        return new ComposerManifestReader(
            new ComposerInstalledMetadataProvider([
                [
                    'root' => [
                        'name' => 'coretsia/test-app',
                        'type' => 'project',
                        'extra' => [],
                    ],
                    'versions' => [
                        'coretsia/core-kernel' => [
                            'type' => 'library',
                            'install_path' => '/tmp/must-not-leak/core-kernel',
                            'extra' => [
                                'coretsia' => $coretsia,
                            ],
                        ],
                    ],
                ],
            ]),
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
        self::assertStringNotContainsString('install_path', $value);
        self::assertStringNotContainsString('defaultsConfigPath', $value);
        self::assertStringNotContainsString('Coretsia\\Kernel\\KernelModule', $value);
        self::assertStringNotContainsString('Coretsia\\Kernel\\Provider\\KernelServiceProvider', $value);
        self::assertStringNotContainsString("\0", $value);
        self::assertStringNotContainsString("\r", $value);
        self::assertStringNotContainsString("\n", $value);
        self::assertStringNotContainsString("\t", $value);
        self::assertStringNotContainsString('://', $value);
        self::assertStringNotContainsString('..', $value);
    }
}
