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

namespace Coretsia\Platform\Errors\Tests\Contract;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Errors\Module\ErrorsModule;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use PHPUnit\Framework\TestCase;

final class ErrorsModuleComposerMetadataContractTest extends TestCase
{
    public function testComposerIdentityAutoloadAndRuntimeRequirementsMatchCanonicalPackage(): void
    {
        $root = \dirname(__DIR__, 2);
        $composer = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        $releaseLinePath = \dirname(__DIR__, 5) . '/tools/release/release-line.json';
        self::assertFileExists($releaseLinePath);

        $releaseLine = \json_decode(
            (string) \file_get_contents($releaseLinePath),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($releaseLine);

        $publicConstraint = $releaseLine['publicConstraint'] ?? null;
        self::assertIsString($publicConstraint);
        self::assertMatchesRegularExpression(
            '/\A\^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.0\z/D',
            $publicConstraint,
        );

        self::assertSame('coretsia/platform-errors', $composer['name']);
        self::assertSame('library', $composer['type']);
        self::assertSame('^8.4', $composer['require']['php']);
        self::assertSame(['Coretsia\\Platform\\Errors\\' => 'src/'], $composer['autoload']['psr-4']);
        self::assertSame(
            ['Coretsia\\Platform\\Errors\\Tests\\' => 'tests/'],
            $composer['autoload-dev']['psr-4'],
        );
        self::assertSame([
            'php' => '^8.4',
            'coretsia/core-contracts' => $publicConstraint,
            'coretsia/core-foundation' => $publicConstraint,
            'coretsia/platform-redaction' => $publicConstraint,
            'psr/container' => '^2.0',
            'psr/log' => '^3.0',
        ], $composer['require']);

        foreach (
            ['coretsia/core-kernel', 'coretsia/platform-http', 'coretsia/platform-problem-details'] as $forbidden
        ) {
            self::assertArrayNotHasKey($forbidden, $composer['require']);
        }

        self::assertFileExists($root . '/config/errors.php');
        self::assertFileExists($root . '/config/rules.php');
        self::assertFileExists($root . '/src/Module/ErrorsModule.php');
        self::assertFileExists($root . '/src/Provider/ErrorsServiceProvider.php');
    }

    public function testComposerModuleDeclarationMatchesCanonicalModuleMethodsAndProviderContract(): void
    {
        $root = \dirname(__DIR__, 2);
        $composer = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
        $extra = $composer['extra']['coretsia'];
        $module = new ErrorsModule();

        self::assertSame('platform.errors', $module->id());
        self::assertSame('platform/errors', $module->packageId());
        self::assertSame('coretsia/platform-errors', $module->composerPackage());
        self::assertSame('runtime', $module->kind());
        self::assertSame('errors', $module->configRoot());
        self::assertSame([ErrorsServiceProvider::class], $module->providers());

        self::assertSame(ErrorsModule::MODULE_ID, $module->id());
        self::assertSame(ErrorsModule::PACKAGE_ID, $module->packageId());
        self::assertSame(ErrorsModule::COMPOSER_PACKAGE, $module->composerPackage());
        self::assertSame(ErrorsModule::KIND, $module->kind());
        self::assertSame(ErrorsModule::CONFIG_ROOT, $module->configRoot());
        self::assertSame([
            'kind' => $module->kind(),
            'moduleId' => $module->id(),
            'moduleClass' => ErrorsModule::class,
            'providers' => $module->providers(),
            'requires' => ['core.foundation', 'platform.redaction'],
            'conflicts' => [],
            'defaultsConfigPath' => 'config/errors.php',
        ], $extra);
        self::assertSame($composer['name'], $module->composerPackage());
        self::assertInstanceOf(ServiceProviderInterface::class, new ErrorsServiceProvider());
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, new ErrorsServiceProvider());
    }
}
