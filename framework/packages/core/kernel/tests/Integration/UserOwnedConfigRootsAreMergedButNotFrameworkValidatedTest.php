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
use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\TestCase;

final class UserOwnedConfigRootsAreMergedButNotFrameworkValidatedTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-user-owned-config-roots-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testCustomRootsAreMergedExplainedFingerprintableAndUnvalidatedWithoutRules(): void
    {
        self::writePhpReturn(
            $this->temporaryDirectory . '/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'prod',
                    ],
                ],
                'custom_aggregate' => [
                    'feature' => [
                        'enabled' => true,
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $this->temporaryDirectory . '/config/custom_split.php',
            [
                'feature' => [
                    'name' => 'split-root',
                ],
            ],
        );

        $loaded = self::loader()->load(
            bootstrapConfig: self::bootstrapConfig($this->temporaryDirectory),
            splitRoots: [
                'custom_split',
                'kernel',
            ],
        );

        $config = self::foldEntries($loaded['entries']);

        self::assertSame(
            [
                'custom_aggregate' => [
                    'feature' => [
                        'enabled' => true,
                    ],
                ],
                'custom_split' => [
                    'feature' => [
                        'name' => 'split-root',
                    ],
                ],
                'kernel' => [
                    'boot' => [
                        'default_env' => 'prod',
                    ],
                ],
            ],
            $config,
        );

        $validator = new ConfigValidator();
        $validation = $validator->validate($config, [
            self::kernelRuleset(),
        ]);

        self::assertTrue($validation->isSuccess());
        self::assertSame([], $validation->violations());

        $subjects = $validator->validationSubjects($config, [
            self::kernelRuleset(),
        ]);

        self::assertSame(
            [
                'unvalidated' => [
                    [
                        'ownership' => 'user_owned',
                        'root' => 'custom_aggregate',
                        'validation' => 'unvalidated',
                    ],
                    [
                        'ownership' => 'user_owned',
                        'root' => 'custom_split',
                        'validation' => 'unvalidated',
                    ],
                ],
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'kernel',
                        'validation' => 'validated',
                    ],
                ],
            ],
            $subjects,
        );

        $sources = [
            new ConfigValueSource(
                type: ConfigSourceType::SkeletonConfig,
                root: 'custom_aggregate',
                sourceId: 'skeleton-config/skeleton_shared/aggregate_root_map/skeleton_config_roots.php/custom_aggregate',
                path: 'skeleton/config/roots.php',
                keyPath: 'custom_aggregate',
                directive: null,
                precedence: 100,
                redacted: false,
                meta: [
                    'kind' => 'aggregate_root_map',
                    'layer' => 'skeleton_shared',
                    'sourceOrder' => 0,
                ],
            ),
            new ConfigValueSource(
                type: ConfigSourceType::SkeletonConfig,
                root: 'custom_split',
                sourceId: 'skeleton-config/skeleton_shared/split_root_subtree/skeleton_config_custom_split.php/custom_split',
                path: 'skeleton/config/custom_split.php',
                keyPath: 'custom_split',
                directive: null,
                precedence: 101,
                redacted: false,
                meta: [
                    'kind' => 'split_root_subtree',
                    'layer' => 'skeleton_shared',
                    'sourceOrder' => 1,
                ],
            ),
            new ConfigValueSource(
                type: ConfigSourceType::SkeletonConfig,
                root: 'kernel',
                sourceId: 'skeleton-config/skeleton_shared/aggregate_root_map/skeleton_config_roots.php/kernel',
                path: 'skeleton/config/roots.php',
                keyPath: 'kernel',
                directive: null,
                precedence: 100,
                redacted: false,
                meta: [
                    'kind' => 'aggregate_root_map',
                    'layer' => 'skeleton_shared',
                    'sourceOrder' => 0,
                ],
            ),
        ];

        $explain = new ConfigExplainer()->explain(
            config: $config,
            sources: $sources,
            validationSubjects: $subjects,
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [],
        );

        self::assertSame(
            [
                'custom_aggregate',
                'custom_aggregate.feature',
                'custom_aggregate.feature.enabled',
                'custom_split',
                'custom_split.feature',
                'custom_split.feature.name',
                'kernel',
                'kernel.boot',
                'kernel.boot.default_env',
            ],
            \array_column($explain['paths'], 'path'),
        );

        self::assertSame(
            [
                'ownership' => 'user_owned',
                'status' => 'unvalidated',
            ],
            self::pathRow($explain, 'custom_aggregate.feature.enabled')['validation'],
        );

        self::assertSame(
            [
                'ownership' => 'user_owned',
                'status' => 'unvalidated',
            ],
            self::pathRow($explain, 'custom_split.feature.name')['validation'],
        );

        $fingerprintInput = self::stableFingerprintInput($config);

        self::assertStringContainsString('custom_aggregate', $fingerprintInput);
        self::assertStringContainsString('custom_split', $fingerprintInput);
        self::assertStringContainsString('kernel', $fingerprintInput);
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
            'additionalKeys' => false,
            'type' => 'map',
            'keys' => [
                'boot' => [
                    'type' => 'map',
                    'required' => true,
                    'additionalKeys' => false,
                    'keys' => [
                        'default_env' => [
                            'type' => 'non-empty-string-no-ws',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ]);
    }

    private static function loader(): SkeletonConfigLoader
    {
        return new SkeletonConfigLoader(
            directiveProcessor: self::processor(),
        );
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

    /**
     * @param array<string,mixed> $config
     */
    private static function stableFingerprintInput(array $config): string
    {
        $normalized = self::sortRecursively($config);

        return \json_encode($normalized, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array-key,mixed> $value
     *
     * @return array<array-key,mixed>
     */
    private static function sortRecursively(array $value): array
    {
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::sortRecursively($item);
            }
        }

        if (!\array_is_list($value)) {
            \ksort($value, \SORT_STRING);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $explain
     *
     * @return array<string,mixed>
     */
    private static function pathRow(array $explain, string $path): array
    {
        foreach ($explain['paths'] as $row) {
            if ($row['path'] === $path) {
                return $row;
            }
        }

        self::fail('Missing explain path row: ' . $path);
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
