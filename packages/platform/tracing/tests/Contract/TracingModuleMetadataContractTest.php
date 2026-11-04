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

namespace Coretsia\Platform\Tracing\Tests\Contract;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Tracing\Module\TracingModule;
use Coretsia\Platform\Tracing\Provider\TracingServiceProvider;
use PHPUnit\Framework\TestCase;

final class TracingModuleMetadataContractTest extends TestCase
{
    public function testModuleMetadataMatchesExactRuntimeIdentityAndProviderList(): void
    {
        $module = new TracingModule();
        $expected = [
            'MODULE_ID' => 'platform.tracing',
            'PACKAGE_ID' => 'platform/tracing',
            'COMPOSER_PACKAGE' => 'coretsia/platform-tracing',
            'KIND' => 'runtime',
            'CONFIG_ROOT' => 'tracing',
        ];
        $reflection = new \ReflectionClass(TracingModule::class);

        self::assertTrue($reflection->isFinal());
        foreach ($expected as $name => $value) {
            $constant = $reflection->getReflectionConstant($name);
            self::assertNotFalse($constant);
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
        self::assertSame([TracingServiceProvider::class], $module->providers());
        self::assertInstanceOf(ServiceProviderInterface::class, new TracingServiceProvider());
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, new TracingServiceProvider());
    }

    public function testComposerIdentityDependenciesAndModuleRequirementsAreExact(): void
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
        $extra = $manifest['extra']['coretsia'];
        $module = new TracingModule();

        self::assertSame($module->composerPackage(), $manifest['name']);
        self::assertSame('library', $manifest['type']);
        self::assertSame('Apache-2.0', $manifest['license']);
        self::assertSame([
            'php' => '^8.4',
            'coretsia/core-contracts' => $release['publicConstraint'],
            'coretsia/core-foundation' => $release['publicConstraint'],
        ], $manifest['require']);
        self::assertSame(['Coretsia\\Platform\\Tracing\\' => 'src/'], $manifest['autoload']['psr-4']);
        self::assertSame(['Coretsia\\Platform\\Tracing\\Tests\\' => 'tests/'], $manifest['autoload-dev']['psr-4']);
        self::assertSame([
            'kind' => $module->kind(),
            'moduleId' => $module->id(),
            'moduleClass' => TracingModule::class,
            'providers' => $module->providers(),
            'requires' => ['core.foundation'],
            'conflicts' => [],
            'defaultsConfigPath' => 'config/tracing.php',
        ], $extra);
        self::assertFileExists($root . '/config/tracing.php');
        self::assertFileExists($root . '/config/rules.php');
        self::assertArrayNotHasKey('replace', $manifest);
        self::assertArrayNotHasKey('provide', $manifest);
    }

    public function testWorkspaceRegistersTracingWithoutEnablingTheModule(): void
    {
        $repository = \dirname(__DIR__, 5);
        $workspace = \json_decode(
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
        $matches = \array_values(
            \array_filter(
                $workspace['repositories'],
                static fn (array $entry): bool => ($entry['url'] ?? '') === 'packages/platform/tracing',
            ),
        );

        self::assertSame($release['devVersion'], $workspace['require-dev']['coretsia/platform-tracing']);
        self::assertCount(1, $matches);
        self::assertSame('path', $matches[0]['type']);
        self::assertTrue($matches[0]['coretsia_managed']);
        self::assertSame(
            ['coretsia/platform-tracing' => $release['devVersion']],
            $matches[0]['options']['versions'],
        );
        self::assertSame(
            [],
            \array_intersect_key(
                ['coretsia/platform-tracing' => true],
                $workspace['require'],
            )
        );
    }
}
