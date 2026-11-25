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

namespace Coretsia\Platform\Routing\Tests\Contract;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Routing\Module\RoutingModule;
use Coretsia\Platform\Routing\Provider\RoutingServiceProvider;
use PHPUnit\Framework\TestCase;

final class RoutingModuleComposerMetadataContractTest extends TestCase
{
    public function testModuleHasExactTypedIdentityAndProviderList(): void
    {
        $module = new RoutingModule();
        $expected = [
            'MODULE_ID' => 'platform.routing',
            'PACKAGE_ID' => 'platform/routing',
            'COMPOSER_PACKAGE' => 'coretsia/platform-routing',
            'KIND' => 'runtime',
            'CONFIG_ROOT' => 'routing',
        ];
        $reflection = new \ReflectionClass(RoutingModule::class);

        self::assertTrue($reflection->isFinal());
        self::assertSame([], $reflection->getProperties());

        foreach ($expected as $name => $value) {
            $constant = $reflection->getReflectionConstant($name);
            self::assertInstanceOf(\ReflectionClassConstant::class, $constant);
            self::assertTrue($constant->isPublic());
            $type = $constant->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame('string', $type->getName());
            self::assertSame($value, $constant->getValue());
        }

        self::assertSame($expected['MODULE_ID'], $module->id());
        self::assertSame($expected['PACKAGE_ID'], $module->packageId());
        self::assertSame($expected['COMPOSER_PACKAGE'], $module->composerPackage());
        self::assertSame($expected['KIND'], $module->kind());
        self::assertSame($expected['CONFIG_ROOT'], $module->configRoot());
        self::assertSame([RoutingServiceProvider::class], $module->providers());
        self::assertInstanceOf(ServiceProviderInterface::class, new RoutingServiceProvider());
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, new RoutingServiceProvider());
    }

    public function testComposerManifestHasExactDependenciesAndModuleMetadata(): void
    {
        $root = \dirname(__DIR__, 2);
        $repository = \dirname(__DIR__, 5);
        $manifest = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $release = \json_decode(
            (string) \file_get_contents($repository . '/tools/release/release-line.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $module = new RoutingModule();

        self::assertSame($module->composerPackage(), $manifest['name']);
        self::assertSame('library', $manifest['type']);
        self::assertSame('Apache-2.0', $manifest['license']);
        self::assertSame([
            'php' => '^8.4',
            'coretsia/core-contracts' => $release['publicConstraint'],
            'coretsia/core-foundation' => $release['publicConstraint'],
            'coretsia/core-kernel' => $release['publicConstraint'],
            'coretsia/platform-redaction' => $release['publicConstraint'],
            'psr/container' => '^2.0',
            'psr/log' => '^3.0',
        ], $manifest['require']);
        self::assertSame(
            ['Coretsia\\Platform\\Routing\\' => 'src/'],
            $manifest['autoload']['psr-4'],
        );
        self::assertSame(
            ['Coretsia\\Platform\\Routing\\Tests\\' => 'tests/'],
            $manifest['autoload-dev']['psr-4'],
        );
        self::assertSame([
            'kind' => $module->kind(),
            'moduleId' => $module->id(),
            'moduleClass' => RoutingModule::class,
            'providers' => $module->providers(),
            'requires' => ['core.foundation', 'core.kernel', 'platform.redaction'],
            'conflicts' => [],
            'defaultsConfigPath' => 'config/routing.php',
        ], $manifest['extra']['coretsia']);
        self::assertFileExists($root . '/' . $manifest['extra']['coretsia']['defaultsConfigPath']);
        self::assertFileExists($root . '/config/rules.php');
        $registry = \file_get_contents($repository . '/docs/ssot/config-roots.md');
        self::assertIsString($registry);
        self::assertSame(
            1,
            \preg_match_all(
                '/^\|\s*`routing`\s*\|\s*`platform\/routing`\s*\|'
                . '\s*`packages\/platform\/routing\/config\/routing\.php`\s*\|'
                . '\s*`packages\/platform\/routing\/config\/rules\.php`\s*\|/m',
                $registry,
            ),
        );
        self::assertArrayNotHasKey('replace', $manifest);
        self::assertArrayNotHasKey('provide', $manifest);
        self::assertArrayNotHasKey('tags', $manifest['extra']['coretsia']);
        self::assertArrayNotHasKey('runtimeTags', $manifest['extra']['coretsia']);
    }

    public function testWorkspaceReferencesThePackageWithoutImplicitModuleSelection(): void
    {
        $repository = \dirname(__DIR__, 5);
        $manifest = \json_decode(
            (string) \file_get_contents($repository . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $release = \json_decode(
            (string) \file_get_contents($repository . '/tools/release/release-line.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $repositories = \array_values(
            \array_filter(
                $manifest['repositories'],
                static fn (array $entry): bool => ($entry['url'] ?? '') === 'packages/platform/routing',
            ),
        );

        self::assertSame($release['devVersion'], $manifest['require-dev']['coretsia/platform-routing']);
        self::assertArrayNotHasKey('coretsia/platform-routing', $manifest['require']);
        self::assertCount(1, $repositories);
        self::assertSame('path', $repositories[0]['type']);
        self::assertTrue($repositories[0]['coretsia_managed']);
        self::assertSame(
            ['coretsia/platform-routing' => $release['devVersion']],
            $repositories[0]['options']['versions'],
        );
    }
}
