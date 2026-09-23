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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\Preset\PresetNamespace;
use Coretsia\Kernel\Module\ResolvedModuleOverrides;
use PHPUnit\Framework\TestCase;

final class ModePresetLoaderDoesNotMergeOverrideWithDefaultTest extends TestCase
{
    public function testCustomPresetIsLoadedOnlyFromApplicationAndNotMergedWithCanonicalResource(): void
    {
        $root = \sys_get_temp_dir() . '/coretsia-preset-no-merge-' . \bin2hex(\random_bytes(8));
        $packageRoot = $root . '/package';
        $applicationRoot = $root . '/application';
        \mkdir($packageRoot . '/resources/modes', 0777, true);
        \mkdir($applicationRoot . '/config/modes', 0777, true);
        $payload = [
            'schemaVersion' => 1,
            'name' => 'custom-mode',
            'description' => 'Application owned.',
            'required' => ['core.kernel'],
            'modules' => ['platform.worker'],
            'featureBundles' => ['applicationOnly' => true],
            'metadata' => [],
        ];
        \file_put_contents(
            $applicationRoot . '/config/modes/custom-mode.php',
            '<?php return ' . \var_export($payload, true) . ';',
        );
        \file_put_contents(
            $packageRoot . '/resources/modes/custom-mode.php',
            '<?php throw new \\LogicException("wrong-source");',
        );
        try {
            $factory = new ModePresetLoaderFactory($packageRoot, [
                'schema_version' => 1,
                'defaults_path' => 'resources/modes',
                'overrides_path' => 'config/modes',
            ], new ModePresetSchemaValidator());
            $bootstrap = new BootstrapConfig(
                appEnv: 'prod',
                preset: 'custom-mode',
                debug: false,
                artifactsCacheDir: 'var/cache',
                envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
                appTarget: AppTarget::Web,
                applicationRoot: $applicationRoot,
                moduleOverrides: new ResolvedModuleOverrides([], []),
            );
            $preset = $factory->createFor($bootstrap, PresetNamespace::Custom)->load('custom-mode');
            self::assertSame('Application owned.', $preset->description());
            self::assertSame(
                ['core.kernel'],
                \array_map(static fn (ModuleId $id): string => $id->value(), $preset->required()),
            );
            self::assertSame(
                ['platform.worker'],
                \array_map(static fn (ModuleId $id): string => $id->value(), $preset->modules()),
            );
            self::assertSame(['applicationOnly' => true], $preset->featureBundles());
            self::assertSame([], $preset->metadata());
        } finally {
            @\unlink($applicationRoot . '/config/modes/custom-mode.php');
            @\unlink($packageRoot . '/resources/modes/custom-mode.php');
            @\rmdir($applicationRoot . '/config/modes');
            @\rmdir($applicationRoot . '/config');
            @\rmdir($applicationRoot);
            @\rmdir($packageRoot . '/resources/modes');
            @\rmdir($packageRoot . '/resources');
            @\rmdir($packageRoot);
            @\rmdir($root);
        }
    }
}
