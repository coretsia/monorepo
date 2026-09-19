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

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use PHPUnit\Framework\TestCase;

final class ComposerManifestReaderReadsOnlyComposerMetadataTest extends TestCase
{
    public function testReadsRuntimeModulesOnlyFromInjectedComposerInstalledMetadata(): void
    {
        $manifest = self::readerForInstalledData([
            [
                'root' => [
                    'name' => 'coretsia/test-app',
                    'type' => 'project',
                    'extra' => [],
                ],
                'versions' => [
                    /*
                     * This package intentionally looks like a normal installed
                     * Composer package but has no extra.coretsia metadata. It
                     * must not become a runtime module.
                     */
                    'acme/not-a-coretsia-module' => [
                        'type' => 'library',
                        'install_path' => '/tmp/must-not-be-read',
                        'extra' => [],
                    ],
                    'coretsia/core-kernel' => [
                        'type' => 'library',
                        'install_path' => '/tmp/must-not-be-exported',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.kernel',
                                'kind' => 'runtime',
                                'requires' => [
                                    'platform.cli',
                                    'core.foundation',
                                    'core.foundation',
                                ],
                                'conflicts' => [
                                    'platform.http',
                                    'platform.routing',
                                    'platform.http',
                                ],
                                'providers' => [
                                    'Coretsia\Kernel\Provider\KernelServiceProvider',
                                ],
                                'defaultsConfigPath' => 'config/kernel.php',
                            ],
                        ],
                        'dev_requirement' => false,
                    ],
                    'coretsia/core-foundation' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'core.foundation',
                                'kind' => 'runtime',
                            ],
                        ],
                        'dev_requirement' => false,
                    ],
                    'coretsia/platform-cli' => [
                        'type' => 'library',
                        'extra' => [
                            'coretsia' => [
                                'moduleId' => 'platform.cli',
                                'kind' => 'runtime',
                            ],
                        ],
                        'dev_requirement' => false,
                    ],
                ],
            ],
        ])->read();

        self::assertSame(
            [
                'core.foundation',
                'core.kernel',
                'platform.cli',
            ],
            $manifest->ids(),
        );

        self::assertFalse($manifest->has('acme.not-a-coretsia-module'));

        $kernel = $manifest->get('core.kernel');

        self::assertInstanceOf(ModuleDescriptor::class, $kernel);
        self::assertSame('core.kernel', $kernel->moduleId());
        self::assertSame('coretsia/core-kernel', $kernel->composerName());
        self::assertSame('runtime', $kernel->packageKind());

        self::assertSame(
            [
                'core.foundation',
                'platform.cli',
            ],
            $kernel->metadata()['requires'] ?? null,
        );

        self::assertSame(
            [
                'platform.http',
                'platform.routing',
            ],
            $kernel->metadata()['conflicts'] ?? null,
        );

        self::assertSame(
            [
                'Coretsia\Kernel\Provider\KernelServiceProvider',
            ],
            $kernel->metadata()['providers'] ?? null,
        );

        /*
         * defaultsConfigPath may be read as descriptor metadata, but it must
         * not be used as diagnostics or discovery identity.
         */
        self::assertSame(
            'config/kernel.php',
            $kernel->metadata()['defaultsConfigPath'] ?? null,
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
}
