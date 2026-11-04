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

namespace Coretsia\Platform\Metrics\Tests\Contract;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Metrics\Default\DefaultMeter;
use Coretsia\Platform\Metrics\Labels\AllowedLabelSet;
use Coretsia\Platform\Metrics\Module\MetricsModule;
use Coretsia\Platform\Metrics\Noop\NullMetricsRenderer;
use Coretsia\Platform\Metrics\Provider\MetricsServiceProvider;
use PHPUnit\Framework\TestCase;

final class MetricsModuleComposerMetadataContractTest extends TestCase
{
    public function testModuleHasExactTypedIdentityAndProviderList(): void
    {
        $module = new MetricsModule();
        $expected = [
            'MODULE_ID' => 'platform.metrics',
            'PACKAGE_ID' => 'platform/metrics',
            'COMPOSER_PACKAGE' => 'coretsia/platform-metrics',
            'KIND' => 'runtime',
            'CONFIG_ROOT' => 'metrics',
        ];
        $reflection = new \ReflectionClass(MetricsModule::class);

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
        self::assertSame([MetricsServiceProvider::class], $module->providers());
        self::assertInstanceOf(ServiceProviderInterface::class, new MetricsServiceProvider());
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, new MetricsServiceProvider());
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
        $module = new MetricsModule();

        self::assertSame($module->composerPackage(), $manifest['name']);
        self::assertSame('library', $manifest['type']);
        self::assertSame('Apache-2.0', $manifest['license']);
        self::assertSame([
            'php' => '^8.4',
            'coretsia/core-contracts' => $release['publicConstraint'],
            'coretsia/core-foundation' => $release['publicConstraint'],
        ], $manifest['require']);
        self::assertSame(
            ['Coretsia\\Platform\\Metrics\\' => 'src/'],
            $manifest['autoload']['psr-4'],
        );
        self::assertSame(
            ['Coretsia\\Platform\\Metrics\\Tests\\' => 'tests/'],
            $manifest['autoload-dev']['psr-4'],
        );
        self::assertSame([
            'kind' => $module->kind(),
            'moduleId' => $module->id(),
            'moduleClass' => MetricsModule::class,
            'providers' => $module->providers(),
            'requires' => ['core.foundation'],
            'conflicts' => [],
            'defaultsConfigPath' => 'config/metrics.php',
        ], $manifest['extra']['coretsia']);
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
                static fn (array $entry): bool => ($entry['url'] ?? '') === 'packages/platform/metrics',
            ),
        );

        self::assertSame($release['devVersion'], $manifest['require-dev']['coretsia/platform-metrics']);
        self::assertArrayNotHasKey('coretsia/platform-metrics', $manifest['require']);
        self::assertCount(1, $repositories);
        self::assertSame('path', $repositories[0]['type']);
        self::assertTrue($repositories[0]['coretsia_managed']);
        self::assertSame(
            ['coretsia/platform-metrics' => $release['devVersion']],
            $repositories[0]['options']['versions'],
        );
    }

    public function testProviderDefinesOnlyTwoStatelessServicesAndNoRuntimeTags(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new MetricsServiceProvider()->define(
            $definitions,
            new ContainerDefinitionContext(['foundation' => [], 'metrics' => []]),
        );

        self::assertSame([
            [
                'arguments' => [],
                'class' => DefaultMeter::class,
                'id' => MeterPortInterface::class,
                'kind' => 'service.class',
                'shared' => true,
            ],
            [
                'arguments' => [],
                'class' => NullMetricsRenderer::class,
                'id' => MetricsRendererInterface::class,
                'kind' => 'service.class',
                'shared' => true,
            ],
        ], $definitions->build()->toDescriptorStream());

        foreach (
            [
                MetricsModule::class,
                MetricsServiceProvider::class,
                DefaultMeter::class,
                NullMetricsRenderer::class,
                AllowedLabelSet::class,
            ] as $class
        ) {
            $reflection = new \ReflectionClass($class);
            self::assertSame([], $reflection->getProperties());
            self::assertFalse($reflection->hasMethod('reset'));
        }
    }

    public function testProductionImportsRemainWithinTheDeclaredDependencyBoundary(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        $count = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            ++$count;
            $source = (string) \file_get_contents($file->getPathname());
            $tokens = \token_get_all($source, \TOKEN_PARSE);

            foreach ($tokens as $token) {
                if (!\is_array($token)) {
                    continue;
                }

                [$id, $symbol] = $token;
                if (!\in_array($id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }

                $symbol = \ltrim($symbol, '\\');
                if (!\str_contains($symbol, '\\')) {
                    continue;
                }

                self::assertTrue(
                    \str_starts_with($symbol, 'Coretsia\\Contracts\\')
                    || \str_starts_with($symbol, 'Coretsia\\Foundation\\')
                    || \str_starts_with($symbol, 'Coretsia\\Platform\\Metrics\\'),
                    'Forbidden production dependency in ' . $file->getFilename(),
                );
            }
        }

        self::assertGreaterThan(0, $count);
    }
}
