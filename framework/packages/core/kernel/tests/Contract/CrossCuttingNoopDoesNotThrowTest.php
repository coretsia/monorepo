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

use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testModuleAndProviderAreLoadableAndDoNotThrow(): void
    {
        $module = new KernelModule();

        self::assertSame(KernelModule::MODULE_ID, $module->id());
        self::assertSame(KernelModule::PACKAGE_ID, $module->packageId());
        self::assertSame(KernelModule::COMPOSER_PACKAGE, $module->composerPackage());
        self::assertSame(KernelModule::KIND, $module->kind());
        self::assertSame(KernelModule::CONFIG_ROOT, $module->configRoot());

        self::assertSame('core.kernel', $module->id());
        self::assertSame('core/kernel', $module->packageId());
        self::assertSame('coretsia/core-kernel', $module->composerPackage());
        self::assertSame('runtime', $module->kind());
        self::assertSame('kernel', $module->configRoot());

        $providers = $module->providers();

        self::assertSame(
            [
                KernelServiceProvider::class,
            ],
            $providers,
            'KernelModule::providers() MUST return the Kernel provider in module-declared order.',
        );

        foreach ($providers as $providerFqcn) {
            self::assertIsString($providerFqcn);
            self::assertNotSame('', $providerFqcn);
            self::assertTrue(\class_exists($providerFqcn), 'Kernel provider class MUST be loadable.');
        }

        $provider = new KernelServiceProvider();
        $providerReflection = new ReflectionClass($provider);

        self::assertSame(KernelServiceProvider::class, $providerReflection->getName());
        self::assertTrue($providerReflection->hasMethod('register'));
    }

    public function testComposerMetadataIsLoadableAndMatchesKernelModuleMetadata(): void
    {
        $composer = self::packageComposer();

        self::assertSame('coretsia/core-kernel', $composer['name'] ?? null);
        self::assertSame('library', $composer['type'] ?? null);

        self::assertSame(
            [
                'Coretsia\\Kernel\\' => 'src/',
            ],
            $composer['autoload']['psr-4'] ?? null,
        );

        self::assertSame(
            [
                'Coretsia\\Kernel\\Tests\\' => 'tests/',
            ],
            $composer['autoload-dev']['psr-4'] ?? null,
        );

        self::assertSame(
            KernelModule::KIND,
            $composer['extra']['coretsia']['kind'] ?? null,
        );

        self::assertSame(
            KernelModule::MODULE_ID,
            $composer['extra']['coretsia']['moduleId'] ?? null,
        );

        self::assertSame(
            KernelModule::class,
            $composer['extra']['coretsia']['moduleClass'] ?? null,
        );

        self::assertSame(
            [
                KernelServiceProvider::class,
            ],
            $composer['extra']['coretsia']['providers'] ?? null,
        );

        self::assertSame(
            [
                'core.foundation',
            ],
            $composer['extra']['coretsia']['requires'] ?? null,
        );

        self::assertSame(
            [],
            $composer['extra']['coretsia']['conflicts'] ?? null,
        );

        self::assertSame(
            'config/kernel.php',
            $composer['extra']['coretsia']['defaultsConfigPath'] ?? null,
        );
    }

    public function testConfigFilesAreLoadableAndHaveNoSideEffects(): void
    {
        $kernelFile = self::packageRoot() . '/config/kernel.php';
        $rulesFile = self::packageRoot() . '/config/rules.php';

        self::assertFileExists($kernelFile);
        self::assertFileExists($rulesFile);

        \ob_start();
        $kernelSubtree = require $kernelFile;
        $out = \ob_get_clean();

        self::assertIsString($out);
        self::assertSame('', $out, 'config/kernel.php MUST NOT emit output.');
        self::assertIsArray($kernelSubtree, 'config/kernel.php MUST return an array subtree.');
        self::assertFalse(
            \array_is_list($kernelSubtree),
            'config/kernel.php MUST return the kernel subtree map.',
        );
        self::assertArrayNotHasKey(
            'kernel',
            $kernelSubtree,
            'config/kernel.php MUST NOT repeat the root key ("kernel").',
        );

        self::assertSame(
            [
                'config',
                'boot',
                'runtime',
                'env',
                'modules',
                'modes',
                'artifacts',
                'fingerprint',
                'uow',
            ],
            \array_keys($kernelSubtree),
            'config/kernel.php top-level key order is part of the deterministic defaults contract.',
        );

        self::assertSame(
            [
                'coretsia',
                '_internal',
            ],
            $kernelSubtree['config']['forbidden_top_level_roots'] ?? null,
        );

        self::assertSame('local', $kernelSubtree['boot']['default_env'] ?? null);
        self::assertSame('micro', $kernelSubtree['boot']['default_preset'] ?? null);
        self::assertSame(false, $kernelSubtree['boot']['default_debug'] ?? null);

        self::assertSame(false, $kernelSubtree['runtime']['frankenphp']['enabled'] ?? null);
        self::assertSame(false, $kernelSubtree['runtime']['swoole']['enabled'] ?? null);
        self::assertSame(false, $kernelSubtree['runtime']['roadrunner']['enabled'] ?? null);

        self::assertSame('strict_dotenv', $kernelSubtree['env']['source_policy']['default_local'] ?? null);
        self::assertSame('allow_system', $kernelSubtree['env']['source_policy']['default_production'] ?? null);
        self::assertSame(
            [
                '.env',
                '.env.local',
                '.env.<env>',
                '.env.<env>.local',
            ],
            $kernelSubtree['env']['dotenv']['files'] ?? null,
        );

        self::assertSame('composer', $kernelSubtree['modules']['discovery']['source'] ?? null);
        self::assertSame(
            [
                'composer',
            ],
            $kernelSubtree['modules']['discovery']['allowed_sources'] ?? null,
        );

        self::assertSame(1, $kernelSubtree['modes']['schema_version'] ?? null);
        self::assertSame('resources/modes', $kernelSubtree['modes']['defaults_path'] ?? null);
        self::assertSame('config/modes', $kernelSubtree['modes']['overrides_path'] ?? null);

        self::assertSame('var/cache', $kernelSubtree['artifacts']['cache_dir'] ?? null);
        self::assertSame(
            [
                'var/cache',
                'var/maintenance',
            ],
            $kernelSubtree['fingerprint']['skeleton_ignore_prefixes'] ?? null,
        );

        self::assertSame(10, $kernelSubtree['uow']['attributes']['max_depth'] ?? null);
        self::assertSame(200, $kernelSubtree['uow']['attributes']['max_keys'] ?? null);

        \ob_start();
        $rules = require $rulesFile;
        $out = \ob_get_clean();

        self::assertIsString($out);
        self::assertSame('', $out, 'config/rules.php MUST NOT emit output.');
        self::assertIsArray($rules, 'config/rules.php MUST return a plain declarative ruleset array.');

        self::assertSame(1, $rules['schemaVersion'] ?? null);
        self::assertSame('kernel', $rules['configRoot'] ?? null);
        self::assertSame(false, $rules['additionalKeys'] ?? null);

        self::assertArrayHasKey('keys', $rules);
        self::assertIsArray($rules['keys']);

        foreach (
            [
                'config',
                'boot',
                'runtime',
                'env',
                'modules',
                'modes',
                'artifacts',
                'fingerprint',
                'uow',
            ] as $key
        ) {
            self::assertArrayHasKey($key, $rules['keys']);
        }

        self::assertSame('map', $rules['keys']['config']['type'] ?? null);
        self::assertSame('map', $rules['keys']['boot']['type'] ?? null);
        self::assertSame('map', $rules['keys']['runtime']['type'] ?? null);
        self::assertSame('map', $rules['keys']['env']['type'] ?? null);
        self::assertSame('map', $rules['keys']['modules']['type'] ?? null);
        self::assertSame('map', $rules['keys']['modes']['type'] ?? null);
        self::assertSame('map', $rules['keys']['artifacts']['type'] ?? null);
        self::assertSame('map', $rules['keys']['fingerprint']['type'] ?? null);
        self::assertSame('map', $rules['keys']['uow']['type'] ?? null);

        self::assertSame('list', $rules['keys']['config']['keys']['forbidden_top_level_roots']['type'] ?? null);

        self::assertSame('non-empty-string', $rules['keys']['boot']['keys']['default_env']['type'] ?? null);
        self::assertSame('non-empty-string', $rules['keys']['boot']['keys']['default_preset']['type'] ?? null);
        self::assertSame('bool', $rules['keys']['boot']['keys']['default_debug']['type'] ?? null);

        self::assertSame('bool', $rules['keys']['runtime']['keys']['frankenphp']['keys']['enabled']['type'] ?? null);
        self::assertSame('bool', $rules['keys']['runtime']['keys']['swoole']['keys']['enabled']['type'] ?? null);
        self::assertSame('bool', $rules['keys']['runtime']['keys']['roadrunner']['keys']['enabled']['type'] ?? null);

        self::assertSame(
            [
                'strict_dotenv',
            ],
            $rules['keys']['env']['keys']['source_policy']['keys']['default_local']['allowedValues'] ?? null
        );
        self::assertSame(
            [
                'allow_system',
            ],
            $rules['keys']['env']['keys']['source_policy']['keys']['default_production']['allowedValues'] ?? null
        );

        self::assertSame(
            'non-empty-string-no-ws',
            $rules['keys']['modules']['keys']['discovery']['keys']['source']['type'] ?? null
        );
        self::assertSame(
            'list',
            $rules['keys']['modules']['keys']['discovery']['keys']['allowed_sources']['type'] ?? null
        );
        self::assertSame(
            'non-empty-string-no-ws',
            $rules['keys']['modules']['keys']['discovery']['keys']['allowed_sources']['items']['type'] ?? null
        );
        self::assertSame('int', $rules['keys']['modes']['keys']['schema_version']['type'] ?? null);
        self::assertSame('relative-safe-path', $rules['keys']['modes']['keys']['defaults_path']['type'] ?? null);
        self::assertSame('relative-safe-path', $rules['keys']['modes']['keys']['overrides_path']['type'] ?? null);
        self::assertSame('relative-safe-path', $rules['keys']['artifacts']['keys']['cache_dir']['type'] ?? null);
        self::assertSame('list', $rules['keys']['fingerprint']['keys']['skeleton_ignore_prefixes']['type'] ?? null);
        self::assertSame('int', $rules['keys']['uow']['keys']['attributes']['keys']['max_depth']['type'] ?? null);
        self::assertSame('int', $rules['keys']['uow']['keys']['attributes']['keys']['max_keys']['type'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private static function packageComposer(): array
    {
        $composerPath = self::packageRoot() . '/composer.json';

        self::assertFileExists($composerPath);

        $contents = \file_get_contents($composerPath);

        self::assertIsString($contents);

        $decoded = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
