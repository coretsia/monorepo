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

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArrayEnvRepository;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\TestCase;

final class ConfigEnvironmentSpecificOverlaysPrecedenceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-config-env-precedence-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testEnvironmentAndAppLayersOverrideInCanonicalOrderAndEnvOverlayWins(): void
    {
        self::writePhpReturn(
            $this->temporaryDirectory . '/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'from-skeleton-shared',
                        'default_env' => 'from-skeleton-shared',
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $this->temporaryDirectory . '/config/environments/prod/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'from-skeleton-environment',
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $this->temporaryDirectory . '/apps/web/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'from-app-shared',
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $this->temporaryDirectory . '/apps/web/config/environments/prod/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'from-app-environment',
                    ],
                ],
            ],
        );

        $loader = new SkeletonConfigLoader(
            directiveProcessor: self::processor(),
        );

        $loaded = $loader->load(
            bootstrapConfig: self::bootstrapConfig($this->temporaryDirectory),
            splitRoots: [
                'kernel',
            ],
        );

        self::assertSame(
            [
                'skeleton/config/roots.php',
                'skeleton/config/environments/prod/roots.php',
                'skeleton/apps/web/config/roots.php',
                'skeleton/apps/web/config/environments/prod/roots.php',
            ],
            \array_column($loaded['entries'], 'path'),
        );

        $envOverlay = new EnvironmentOverlayLoader()->load(
            env: new ArrayEnvRepository([
                'KERNEL_BOOT_DEFAULT_ENV' => 'from-env-overlay',
                'UNKNOWN_ENV_VAR' => 'must-not-create-config-key',
            ]),
            rulesets: [
                self::kernelRuleset(),
            ],
            explicitMappings: [],
            precedence: 500,
        );

        self::assertSame(
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'from-env-overlay',
                    ],
                ],
            ],
            $envOverlay['config'],
        );

        $entries = $loaded['entries'];
        $entries[] = [
            'config' => $envOverlay['config'],
            'kind' => 'env_overlay',
            'layer' => 'env_overlay',
            'path' => 'env',
            'precedence' => 500,
            'sourceId' => 'env-overlay/ruleset/KERNEL_BOOT_DEFAULT_ENV',
            'type' => 'env',
        ];

        $merged = self::foldEntries($entries);

        self::assertSame(
            [
                'kernel' => [
                    'boot' => [
                        'default_app' => 'from-skeleton-shared',
                        'default_env' => 'from-env-overlay',
                    ],
                ],
            ],
            $merged,
        );

        self::assertArrayNotHasKey('unknown', $merged);
        self::assertArrayNotHasKey('UNKNOWN_ENV_VAR', $merged);
    }

    /**
     * @param list<array{config: array<string,mixed>, precedence: int, path: string}> $entries
     *
     * @return array<string,mixed>
     */
    private static function foldEntries(array $entries): array
    {
        \usort(
            $entries,
            static fn (array $a, array $b): int => ($a['precedence'] <=> $b['precedence'])
                ?: \strcmp($a['path'], $b['path']),
        );

        $processor = self::processor();
        $merger = new ConfigMerger($processor);
        $merged = [];

        foreach ($entries as $entry) {
            $merged = $merger->merge($merged, $entry['config']);

            self::assertIsArray($merged);
        }

        /** @var array<string,mixed> $merged */
        return $merged;
    }

    private static function kernelRuleset(): ConfigRuleset
    {
        return ConfigRuleset::fromArray('kernel', [
            'configRoot' => 'kernel',
            'schemaVersion' => 1,
            'additionalKeys' => true,
            'type' => 'map',
            'keys' => [
                'boot' => [
                    'type' => 'map',
                    'required' => false,
                    'additionalKeys' => true,
                    'keys' => [
                        'default_env' => [
                            'type' => 'non-empty-string-no-ws',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ]);
    }

    private static function processor(): DirectiveProcessor
    {
        return new DirectiveProcessor(
            namespaceGuard: new ConfigNamespaceGuard([
                'coretsia',
                '_internal',
            ]),
        );
    }

    private static function bootstrapConfig(string $skeletonRoot): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'prod',
            preset: 'default',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: $skeletonRoot,
        );
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function writePhpReturn(string $path, array $value): void
    {
        $directory = \dirname($path);

        if (!\is_dir($directory)) {
            \mkdir($directory, 0777, true);
        }

        \file_put_contents(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($value, true) . ";\n",
        );
    }

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $items = \scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (\is_dir($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            \unlink($itemPath);
        }

        \rmdir($path);
    }
}
