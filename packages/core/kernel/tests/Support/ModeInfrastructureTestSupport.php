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

namespace Coretsia\Kernel\Tests\Support;

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Module\ModuleSelectionFactory;
use Coretsia\Kernel\Module\Preset\PresetNamespaceResolver;
use Coretsia\Kernel\Module\ResolvedModuleOverrides;
use Coretsia\Kernel\Module\TopologicalSorter;
use Psr\Log\NullLogger;

/**
 * Small, package-local fixtures for the Phase A/Phase B integration tests.
 *
 * @internal
 */
final class ModeInfrastructureTestSupport
{
    public static function root(): string
    {
        $root = \sys_get_temp_dir() . '/coretsia-mode-infrastructure-' . \bin2hex(\random_bytes(8));
        \mkdir($root, 0777, true);
        return $root;
    }

    public static function remove(string $path): void
    {
        if (\is_link($path) || \is_file($path)) {
            @\unlink($path);
            return;
        }
        if (!\is_dir($path)) {
            return;
        }
        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }
        @\rmdir($path);
    }

    public static function write(string $file, string $text): void
    {
        $dir = \dirname($file);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0777, true);
        }
        \file_put_contents($file, $text);
    }

    public static function payload(string $name, array $required = ['core.kernel'], array $modules = []): array
    {
        return [
            'schemaVersion' => 1,
            'name' => $name,
            'description' => null,
            'required' => $required,
            'modules' => $modules,
            'featureBundles' => [],
            'metadata' => [],
        ];
    }

    public static function writePreset(
        string $file,
        string $name,
        array $required = ['core.kernel'],
        array $modules = [],
    ): void {
        self::write($file, '<?php return ' . \var_export(self::payload($name, $required, $modules), true) . ';');
    }

    public static function bootstrap(
        string $applicationRoot,
        string $name = 'micro',
        ?ResolvedModuleOverrides $overrides = null,
    ): BootstrapConfig {
        return new BootstrapConfig(
            appEnv: 'prod',
            preset: $name,
            debug: false,
            artifactsCacheDir: 'var/cache',
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            applicationRoot: $applicationRoot,
            moduleOverrides: $overrides ?? new ResolvedModuleOverrides([], []),
        );
    }

    public static function factory(string $packageRoot, array $modesConfig = []): ModePresetLoaderFactory
    {
        return new ModePresetLoaderFactory(
            $packageRoot,
            $modesConfig ?: [
            'schema_version' => 1,
            'defaults_path' => 'resources/modes',
            'overrides_path' => 'config/modes',
        ],
            new ModePresetSchemaValidator(),
        );
    }

    public static function id(string $id): ModuleId
    {
        return ModuleId::fromString($id);
    }

    public static function ids(array $ids): array
    {
        return \array_map(static fn (string $id): ModuleId => ModuleId::fromString($id), $ids);
    }

    public static function values(array $ids): array
    {
        return \array_map(static fn (ModuleId $id): string => $id->value(), $ids);
    }

    public static function descriptor(string $id, array $requires = [], array $conflicts = []): ModuleDescriptor
    {
        return new ModuleDescriptor(
            id: self::id($id),
            composerName: 'coretsia/' . \str_replace('.', '-', $id),
            packageKind: 'runtime',
            moduleClass: null,
            capabilities: [],
            metadata: ['requires' => $requires, 'conflicts' => $conflicts],
        );
    }

    public static function manifest(array $descriptors): ModuleManifest
    {
        return new ModuleManifest($descriptors);
    }

    public static function orchestrator(string $packageRoot, ModuleManifest $manifest): ModuleResolutionOrchestrator
    {
        $reader = new class($manifest) implements ManifestReaderInterface {
            public int $reads = 0;

            public function __construct(private ModuleManifest $manifest)
            {
            }

            public function read(): ModuleManifest
            {
                ++$this->reads;
                return $this->manifest;
            }
        };
        return new ModuleResolutionOrchestrator(
            presetLoaderFactory: self::factory($packageRoot),
            presetNamespaceResolver: new PresetNamespaceResolver(),
            moduleSelectionFactory: new ModuleSelectionFactory(new ModuleIdSetNormalizer()),
            manifestReader: $reader,
            modulePlanResolver: new ModulePlanResolver(new ModuleGraphResolver(new TopologicalSorter())),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            stopwatch: new Stopwatch(),
            logger: new NullLogger(),
            modulesConfig: ['discovery' => ['source' => 'composer', 'allowed_sources' => ['composer']]],
        );
    }
}
