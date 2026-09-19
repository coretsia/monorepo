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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use PHPUnit\Framework\TestCase;

final class ComposerManifestReaderPreservesProviderOrderContractTest extends TestCase
{
    public function testPreservesProviderOrderWithoutChangingDependencySetOrdering(): void
    {
        $descriptor = self::descriptor(
            self::reader(
                providers: [
                    'Coretsia\\Tests\\Provider\\ZuluProvider',
                    'Coretsia\\Tests\\Provider\\AlphaProvider',
                    'Coretsia\\Tests\\Provider\\MiddleProvider',
                ],
                requires: [
                    'platform.zeta',
                    'core.foundation',
                ],
                conflicts: [
                    'platform.gamma',
                    'platform.alpha',
                ],
            ),
        );

        self::assertSame(
            [
                'Coretsia\\Tests\\Provider\\ZuluProvider',
                'Coretsia\\Tests\\Provider\\AlphaProvider',
                'Coretsia\\Tests\\Provider\\MiddleProvider',
            ],
            $descriptor->metadata()['providers'] ?? null,
        );
        self::assertSame(
            [
                'core.foundation',
                'platform.zeta',
            ],
            $descriptor->metadata()['requires'] ?? null,
        );
        self::assertSame(
            [
                'platform.alpha',
                'platform.gamma',
            ],
            $descriptor->metadata()['conflicts'] ?? null,
        );
    }

    public function testRejectsCaseInsensitiveDuplicateProviderClasses(): void
    {
        $reader = self::reader(
            providers: [
                'Coretsia\\Tests\\Provider\\PrimaryProvider',
                'coretsia\\tests\\provider\\primaryprovider',
            ],
        );

        try {
            $reader->read();

            self::fail('Expected duplicate provider metadata to be rejected.');
        } catch (ModuleManifestInvalidException $exception) {
            self::assertSame(
                ModuleManifestInvalidException::REASON_PROVIDERS_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                [
                    'moduleId' => 'core.kernel',
                ],
                $exception->context(),
            );
        }
    }

    public function testOmitsEmptyProviderMetadataList(): void
    {
        $descriptor = self::descriptor(
            self::reader(
                providers: [],
            ),
        );

        self::assertArrayNotHasKey(
            'providers',
            $descriptor->metadata(),
        );
    }

    private static function descriptor(ComposerManifestReader $reader): ModuleDescriptor
    {
        $descriptor = $reader->read()->get('core.kernel');

        self::assertInstanceOf(
            ModuleDescriptor::class,
            $descriptor,
        );

        return $descriptor;
    }

    /**
     * @param list<string> $providers
     * @param list<string> $requires
     * @param list<string> $conflicts
     */
    private static function reader(
        array $providers,
        array $requires = [],
        array $conflicts = [],
    ): ComposerManifestReader {
        return new ComposerManifestReader(
            metadataProvider: new ComposerInstalledMetadataProvider(
                installedData: [
                    [
                        'root' => [],
                        'versions' => [
                            'coretsia/core-kernel' => [
                                'type' => 'library',
                                'extra' => [
                                    'coretsia' => [
                                        'conflicts' => $conflicts,
                                        'kind' => 'runtime',
                                        'moduleId' => 'core.kernel',
                                        'providers' => $providers,
                                        'requires' => $requires,
                                    ],
                                ],
                                'dev_requirement' => false,
                            ],
                        ],
                    ],
                ],
            ),
        );
    }
}
