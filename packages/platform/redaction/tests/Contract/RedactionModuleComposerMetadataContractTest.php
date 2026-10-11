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

namespace Coretsia\Platform\Redaction\Tests\Contract;

use Coretsia\Platform\Redaction\Module\RedactionModule;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RedactionModuleComposerMetadataContractTest extends TestCase
{
    public function testComposerIdentityNamespaceAndExactDirectDependencies(): void
    {
        $composer = self::composer();

        self::assertSame('coretsia/platform-redaction', $composer['name'] ?? null);
        self::assertSame('library', $composer['type'] ?? null);
        self::assertSame('Apache-2.0', $composer['license'] ?? null);
        $require = $composer['require'] ?? null;
        self::assertIsArray($require);

        $publicConstraint = $require['coretsia/core-contracts'] ?? null;
        self::assertIsString($publicConstraint);
        self::assertMatchesRegularExpression(
            '/\A\^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.0\z/D',
            $publicConstraint,
        );

        self::assertSame(
            [
                'php' => '^8.4',
                'coretsia/core-contracts' => $publicConstraint,
                'coretsia/core-foundation' => $publicConstraint,
            ],
            $require,
        );
        self::assertSame(
            ['Coretsia\\Platform\\Redaction\\' => 'src/'],
            $composer['autoload']['psr-4'] ?? null,
        );
        self::assertSame(
            ['Coretsia\\Platform\\Redaction\\Tests\\' => 'tests/'],
            $composer['autoload-dev']['psr-4'] ?? null,
        );

        foreach (['coretsia/core-kernel', 'psr/log'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $composer['require']);
        }
    }

    public function testModuleMetadataMatchesComposerExactly(): void
    {
        $composer = self::composer();
        $metadata = $composer['extra']['coretsia'] ?? null;
        $module = new RedactionModule();

        self::assertSame(
            [
                'kind' => 'runtime',
                'moduleId' => 'platform.redaction',
                'moduleClass' => RedactionModule::class,
                'providers' => [RedactionServiceProvider::class],
                'requires' => ['core.foundation'],
                'conflicts' => [],
            ],
            $metadata,
        );
        self::assertSame('platform.redaction', $module->id());
        self::assertSame('platform/redaction', $module->packageId());
        self::assertSame('coretsia/platform-redaction', $module->composerPackage());
        self::assertSame('runtime', $module->kind());
        self::assertSame([RedactionServiceProvider::class], $module->providers());
        self::assertSame($metadata['moduleId'], $module->id());
        self::assertSame($metadata['moduleClass'], $module::class);
        self::assertSame($metadata['providers'], $module->providers());
        self::assertSame($metadata['kind'], $module->kind());
        self::assertSame($composer['name'], $module->composerPackage());
        self::assertArrayNotHasKey('defaultsConfigPath', $metadata);
        self::assertFalse(\defined(RedactionModule::class . '::CONFIG_ROOT'));
        self::assertFalse(new ReflectionClass($module)->hasMethod('configRoot'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        $path = \dirname(__DIR__, 2) . '/composer.json';
        self::assertFileExists($path);
        $contents = \file_get_contents($path);
        self::assertIsString($contents);
        $composer = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);

        return $composer;
    }
}
