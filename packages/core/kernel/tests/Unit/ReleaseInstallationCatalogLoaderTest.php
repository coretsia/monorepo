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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\DependencySync\Catalog\ReleaseInstallationCatalogLoader;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use PHPUnit\Framework\TestCase;

final class ReleaseInstallationCatalogLoaderTest extends TestCase
{
    public function testLoadsCommittedCatalogAndHasNoExternalPathInjectionApi(): void
    {
        $catalog = new ReleaseInstallationCatalogLoader()->load();

        self::assertNotSame([], $catalog->entries());
        self::assertSame(
            '^' . $catalog->releaseLine() . '.0',
            $catalog->publicConstraint(),
        );

        $constructor = new \ReflectionClass(ReleaseInstallationCatalogLoader::class)->getConstructor();

        self::assertNull($constructor);
    }

    public function testLoaderOwnsExactPackageResourceAndHasNoExternalResolverFallback(): void
    {
        $path = new \ReflectionClass(ReleaseInstallationCatalogLoader::class)->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);
        self::assertStringContainsString('resources/packaging/installation-catalog.php', $source);
        self::assertStringNotContainsString('ComposerPackageInstallPathResolver', $source);
    }

    public function testRejectsMalformedCatalogShapesThroughSchemaBoundary(): void
    {
        $loader = new ReleaseInstallationCatalogLoader();
        $reflection = new \ReflectionMethod(
            ReleaseInstallationCatalogLoader::class,
            'fromRaw',
        );

        $valid = [
            'schemaVersion' => 1,
            'releaseLine' => '0.7',
            'publicConstraint' => '^0.7.0',
            'modules' => [
                'core.foundation' => [
                    'composerName' => 'coretsia/core-foundation',
                    'requires' => [],
                    'conflicts' => [],
                ],
                'core.kernel' => [
                    'composerName' => 'coretsia/core-kernel',
                    'requires' => ['core.foundation'],
                    'conflicts' => [],
                ],
            ],
        ];

        $cases = [];

        $case = $valid;
        $case['schemaVersion'] = 2;
        $cases[] = $case;

        $case = $valid;
        $case['publicConstraint'] = '^0.8.0';
        $cases[] = $case;

        $case = $valid;
        $case['modules']['core.kernel']['repositoryPath'] = 'packages/core/kernel';
        $cases[] = $case;

        $case = $valid;
        $case['modules'] = [
            'core.kernel' => $valid['modules']['core.kernel'],
            'core.foundation' => $valid['modules']['core.foundation'],
        ];
        $cases[] = $case;

        $case = $valid;
        $case['modules']['core.kernel']['requires'] = [
            'core.foundation',
            'core.foundation',
        ];
        $cases[] = $case;

        $case = $valid;
        $case['modules']['core.kernel']['requires'] = ['core.kernel'];
        $cases[] = $case;

        $case = $valid;
        $case['modules']['core.kernel']['requires'] = ['platform.worker'];
        $cases[] = $case;

        foreach ($cases as $raw) {
            try {
                $reflection->invoke($loader, $raw);
                self::fail('Expected malformed catalog rejection.');
            } catch (\ReflectionException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                self::assertInstanceOf(DependencySyncException::class, $exception);
                self::assertSame(
                    DependencySyncErrorCodes::CATALOG_INVALID,
                    $exception->errorCode(),
                );
            }
        }
    }
}
