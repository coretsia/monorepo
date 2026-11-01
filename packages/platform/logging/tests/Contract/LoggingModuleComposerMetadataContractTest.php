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

namespace Coretsia\Platform\Logging\Tests\Contract;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Logging\Module\LoggingModule;
use Coretsia\Platform\Logging\Provider\LoggingServiceProvider;
use PHPUnit\Framework\TestCase;

final class LoggingModuleComposerMetadataContractTest extends TestCase
{
    public function testComposerManifestHasExactRuntimeIdentityAndAllowedDependencies(): void
    {
        $root = \dirname(__DIR__, 2);
        $manifest = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $constraint = \json_decode(
            (string) \file_get_contents(\dirname(__DIR__, 5) . '/tools/release/release-line.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        )['publicConstraint'];

        self::assertSame('coretsia/platform-logging', $manifest['name']);
        self::assertSame('library', $manifest['type']);
        self::assertSame('Apache-2.0', $manifest['license']);
        self::assertSame([
            'php' => '^8.4',
            'coretsia/core-contracts' => $constraint,
            'coretsia/core-foundation' => $constraint,
            'coretsia/core-kernel' => $constraint,
            'coretsia/platform-redaction' => $constraint,
            'psr/clock' => '^1.0',
            'psr/log' => '^3.0',
        ], $manifest['require']);
        self::assertSame(['Coretsia\\Platform\\Logging\\' => 'src/'], $manifest['autoload']['psr-4']);
        self::assertSame(['Coretsia\\Platform\\Logging\\Tests\\' => 'tests/'], $manifest['autoload-dev']['psr-4']);
        self::assertArrayNotHasKey('provide', $manifest);
        self::assertArrayNotHasKey('replace', $manifest);
        foreach (\array_keys($manifest['require']) as $package) {
            self::assertFalse(\str_starts_with($package, 'integrations/'));
            self::assertFalse(\str_starts_with($package, 'devtools/'));
        }
    }

    public function testModuleClassAndManifestAgreeOnProviderConfigAndRuntimeRequirements(): void
    {
        $root = \dirname(__DIR__, 2);
        $extra = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        )['extra']['coretsia'];
        $module = new LoggingModule();

        self::assertSame('runtime', $extra['kind']);
        self::assertSame('platform.logging', $extra['moduleId']);
        self::assertSame(LoggingModule::class, $extra['moduleClass']);
        self::assertSame([LoggingServiceProvider::class], $extra['providers']);
        self::assertSame(['core.foundation', 'core.kernel', 'platform.redaction'], $extra['requires']);
        self::assertSame([], $extra['conflicts']);
        self::assertSame('config/logging.php', $extra['defaultsConfigPath']);
        self::assertArrayNotHasKey('tags', $extra);
        self::assertArrayNotHasKey('runtimeTags', $extra);
        self::assertSame('platform.logging', LoggingModule::MODULE_ID);
        self::assertSame('platform/logging', LoggingModule::PACKAGE_ID);
        self::assertSame('coretsia/platform-logging', LoggingModule::COMPOSER_PACKAGE);
        self::assertSame('runtime', LoggingModule::KIND);
        self::assertSame('logging', LoggingModule::CONFIG_ROOT);
        self::assertSame(LoggingModule::MODULE_ID, $module->id());
        self::assertSame(LoggingModule::PACKAGE_ID, $module->packageId());
        self::assertSame(LoggingModule::COMPOSER_PACKAGE, $module->composerPackage());
        self::assertSame(LoggingModule::KIND, $module->kind());
        self::assertSame(LoggingModule::CONFIG_ROOT, $module->configRoot());
        self::assertSame([LoggingServiceProvider::class], $module->providers());
        self::assertInstanceOf(ServiceProviderInterface::class, new LoggingServiceProvider());
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, new LoggingServiceProvider());
        self::assertFileExists($root . '/' . $extra['defaultsConfigPath']);
        self::assertFileExists($root . '/config/rules.php');
    }
}
