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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Platform\Cli\Module\CliModule;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use PHPUnit\Framework\TestCase;

final class CliModuleMetadataContractTest extends TestCase
{
    public function testCanonicalModuleIdentityAndInstanceMetadataRemainAligned(): void
    {
        self::assertSame('platform.cli', CliModule::MODULE_ID);
        self::assertSame('platform/cli', CliModule::PACKAGE_ID);
        self::assertSame('coretsia/platform-cli', CliModule::COMPOSER_PACKAGE);
        self::assertSame('runtime', CliModule::KIND);
        self::assertSame('cli', CliModule::CONFIG_ROOT);

        $module = new CliModule();
        self::assertSame(CliModule::MODULE_ID, $module->id());
        self::assertSame(CliModule::PACKAGE_ID, $module->packageId());
        self::assertSame(CliModule::COMPOSER_PACKAGE, $module->composerPackage());
        self::assertSame(CliModule::KIND, $module->kind());
        self::assertSame(CliModule::CONFIG_ROOT, $module->configRoot());
        self::assertSame([CliServiceProvider::class], $module->providers());
        self::assertSame([CliServiceProvider::class], $module->providers());
    }

    public function testModuleExposesOnlyInstanceMetadataWithoutBootOrLegacyStaticProviderApi(): void
    {
        $class = new \ReflectionClass(CliModule::class);
        self::assertTrue($class->isFinal());
        self::assertSame([], $class->getConstructor()?->getParameters() ?? []);
        self::assertSame(
            ['composerPackage', 'configRoot', 'id', 'kind', 'packageId', 'providers'],
            self::metadataMethodNames($class),
        );
        self::assertSame([], $class->getProperties());

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            self::assertFalse($method->isStatic(), $method->getName());
            self::assertCount(0, $method->getParameters(), $method->getName());
        }
        $source = \file_get_contents($class->getFileName());
        self::assertIsString($source);
        $tokens = \token_get_all($source);
        $executable = \implode(
            '',
            \array_map(
                static fn (array|string $token): string => \is_array($token)
                && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : (\is_array(
                    $token,
                ) ? $token[1] : $token),
                $tokens,
            ),
        );
        foreach (
            [
                'ConfigRepositoryInterface',
                'ContainerBuilder',
                'KernelOpsHostBooter',
                'TagRegistry',
                'CliApplication',
                'new CliServiceProvider',
                'require(',
                'include(',
                'file_get_contents(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $executable);
        }
    }

    /** @return list<string> */
    private static function metadataMethodNames(\ReflectionClass $class): array
    {
        $methods = \array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $class->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        \sort($methods);
        return $methods;
    }
}
