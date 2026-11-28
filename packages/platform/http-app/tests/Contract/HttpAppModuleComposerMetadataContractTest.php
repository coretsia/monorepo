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

namespace Coretsia\Platform\HttpApp\Tests\Contract;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Platform\HttpApp\Module\HttpAppModule;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use PHPUnit\Framework\TestCase;

final class HttpAppModuleComposerMetadataContractTest extends TestCase
{
    public function testModuleHasExactTypedIdentityAndProviderList(): void
    {
        $module = new HttpAppModule();
        $expected = [
            'MODULE_ID' => 'platform.http-app',
            'PACKAGE_ID' => 'platform/http-app',
            'COMPOSER_PACKAGE' => 'coretsia/platform-http-app',
            'KIND' => 'runtime',
            'CONFIG_ROOT' => 'http_app',
        ];
        $reflection = new \ReflectionClass(HttpAppModule::class);

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
        self::assertSame([HttpAppServiceProvider::class], $module->providers());
        self::assertInstanceOf(ServiceProviderInterface::class, new HttpAppServiceProvider());
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, new HttpAppServiceProvider());
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
        $module = new HttpAppModule();

        self::assertSame($module->composerPackage(), $manifest['name']);
        self::assertSame('library', $manifest['type']);
        self::assertSame('Apache-2.0', $manifest['license']);
        self::assertSame([
            'php' => '^8.4',
            'coretsia/core-contracts' => $release['publicConstraint'],
            'coretsia/core-foundation' => $release['publicConstraint'],
            'coretsia/platform-errors' => $release['publicConstraint'],
            'coretsia/platform-http' => $release['publicConstraint'],
            'coretsia/platform-problem-details' => $release['publicConstraint'],
            'coretsia/platform-routing' => $release['publicConstraint'],
            'psr/container' => '^2.0',
            'psr/http-factory' => '^1.0',
            'psr/http-message' => '^2.0',
            'psr/http-server-handler' => '^1.0',
            'psr/http-server-middleware' => '^1.0',
            'psr/log' => '^3.0',
        ], $manifest['require']);
        self::assertSame(
            ['Coretsia\\Platform\\HttpApp\\' => 'src/'],
            $manifest['autoload']['psr-4'],
        );
        self::assertSame(
            ['Coretsia\\Platform\\HttpApp\\Tests\\' => 'tests/'],
            $manifest['autoload-dev']['psr-4'],
        );
        self::assertSame([
            'kind' => $module->kind(),
            'moduleId' => $module->id(),
            'moduleClass' => HttpAppModule::class,
            'providers' => $module->providers(),
            'requires' => [
                'core.foundation',
                'platform.errors',
                'platform.http',
                'platform.problem-details',
                'platform.routing',
            ],
            'conflicts' => [],
            'defaultsConfigPath' => 'config/http_app.php',
        ], $manifest['extra']['coretsia']);
        self::assertFileExists($root . '/' . $manifest['extra']['coretsia']['defaultsConfigPath']);
        self::assertFileExists($root . '/config/rules.php');
        self::assertStringContainsString(
            '@return list<class-string<ServiceProviderInterface>>',
            (string) new \ReflectionMethod(HttpAppModule::class, 'providers')->getDocComment(),
        );
    }

    public function testCanonicalModuleSelectionClosesRuntimeRequirementsWithoutImplicitActivation(): void
    {
        $root = \dirname(__DIR__, 5);
        $packages = [
            'core/foundation',
            'core/kernel',
            'platform/redaction',
            'platform/errors',
            'platform/http',
            'platform/problem-details',
            'platform/routing',
            'platform/http-app',
        ];
        $versions = [];
        foreach ($packages as $package) {
            $manifest = \json_decode(
                (string) \file_get_contents($root . '/packages/' . $package . '/composer.json'),
                true,
                flags: \JSON_THROW_ON_ERROR,
            );
            $versions[$manifest['name']] = ['type' => $manifest['type'], 'extra' => $manifest['extra']];
        }
        $manifest = new ComposerManifestReader(new ComposerInstalledMetadataProvider([
            [
                'root' => ['name' => 'coretsia/http-app-contract-test', 'type' => 'project', 'extra' => []],
                'versions' => $versions,
            ],
        ]))->read();
        $resolver = new ModuleGraphResolver(new TopologicalSorter());
        $selected = $resolver->resolve(
            'web',
            $manifest,
            new ModuleSelection([ModuleId::fromString('platform.http-app')], []),
        );
        $ids = \array_map(static fn (ModuleId $id): string => $id->value(), $selected->topologicalOrder());
        $expected = [
            'core.foundation',
            'core.kernel',
            'platform.errors',
            'platform.http',
            'platform.http-app',
            'platform.problem-details',
            'platform.redaction',
            'platform.routing',
        ];
        $sorted = $ids;
        \sort($sorted, \SORT_STRING);
        self::assertSame($expected, $sorted);
        self::assertSame('platform.http-app', $ids[\count($ids) - 1]);
        $httpOnly = $resolver->resolve(
            'web',
            $manifest,
            new ModuleSelection([ModuleId::fromString('platform.http')], []),
        );
        self::assertFalse($httpOnly->hasEnabledModule('platform.http-app'));
        self::assertFalse($httpOnly->hasEnabledModule('platform.routing'));
    }

    public function testProductionSourceImportsStayWithinTheDirectPhpDependencyBoundary(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2) . '/src'));
        $checked = 0;
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$checked;
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            foreach (\token_get_all($source) as $token) {
                if (!\is_array($token) || !\in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }
                $name = \ltrim($token[1], '\\');
                if (!\str_starts_with($name, 'Coretsia\\')) {
                    continue;
                }
                self::assertTrue(
                    \str_starts_with($name, 'Coretsia\\Contracts\\')
                    || \str_starts_with($name, 'Coretsia\\Foundation\\')
                    || \str_starts_with($name, 'Coretsia\\Platform\\HttpApp\\')
                    || $name === 'Coretsia\\Platform\\Http\\Middleware\\Pipeline',
                );
            }
        }
        self::assertGreaterThan(0, $checked);
    }
}
