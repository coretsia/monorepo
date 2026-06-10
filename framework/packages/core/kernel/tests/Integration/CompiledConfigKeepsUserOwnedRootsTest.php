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
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\TestCase;

final class CompiledConfigKeepsUserOwnedRootsTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-compiled-config-user-roots-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testUserOwnedRootsFromRootsPhpAreEmittedInCompiledConfigArtifact(): void
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

        $artifact = self::compiledConfigArtifact(
            skeletonRoot: $this->temporaryDirectory,
            splitRoots: [
                'kernel',
            ],
        );

        self::assertSame(
            [
                'feature' => [
                    'enabled' => true,
                ],
            ],
            $artifact['payload']['config']['custom_aggregate'],
        );

        self::assertSame(
            [
                [
                    'ownership' => 'user_owned',
                    'root' => 'custom_aggregate',
                    'validation' => 'unvalidated',
                ],
            ],
            $artifact['payload']['validationSubjects']['unvalidated'],
        );
    }

    public function testUserOwnedRootsFromSplitRootPhpAreEmittedInCompiledConfigArtifact(): void
    {
        self::writePhpReturn(
            $this->temporaryDirectory . '/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'prod',
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

        $artifact = self::compiledConfigArtifact(
            skeletonRoot: $this->temporaryDirectory,
            splitRoots: [
                'custom_split',
                'kernel',
            ],
        );

        self::assertSame(
            [
                'feature' => [
                    'name' => 'split-root',
                ],
            ],
            $artifact['payload']['config']['custom_split'],
        );

        self::assertSame(
            [
                [
                    'ownership' => 'user_owned',
                    'root' => 'custom_split',
                    'validation' => 'unvalidated',
                ],
            ],
            $artifact['payload']['validationSubjects']['unvalidated'],
        );
    }

    public function testSplitAndAggregateEquivalentUserConfigProducesEquivalentCompiledConfigPayload(): void
    {
        $aggregateRoot = $this->temporaryDirectory . '/aggregate';
        $splitRoot = $this->temporaryDirectory . '/split';

        self::writePhpReturn(
            $aggregateRoot . '/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'prod',
                    ],
                ],
                'custom_equiv' => [
                    'feature' => [
                        'enabled' => true,
                        'name' => 'same',
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $splitRoot . '/config/roots.php',
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'prod',
                    ],
                ],
            ],
        );

        self::writePhpReturn(
            $splitRoot . '/config/custom_equiv.php',
            [
                'feature' => [
                    'enabled' => true,
                    'name' => 'same',
                ],
            ],
        );

        $aggregateArtifact = self::compiledConfigArtifact(
            skeletonRoot: $aggregateRoot,
            splitRoots: [
                'kernel',
            ],
        );

        $splitArtifact = self::compiledConfigArtifact(
            skeletonRoot: $splitRoot,
            splitRoots: [
                'custom_equiv',
                'kernel',
            ],
        );

        self::assertSame(
            $aggregateArtifact['payload']['config']['custom_equiv'],
            $splitArtifact['payload']['config']['custom_equiv'],
        );
    }

    /**
     * @param list<non-empty-string> $splitRoots
     *
     * @return array{_meta: array<string,mixed>, payload: array<string,mixed>}
     */
    private static function compiledConfigArtifact(string $skeletonRoot, array $splitRoots): array
    {
        $loaded = self::loader()->load(
            bootstrapConfig: self::bootstrapConfig($skeletonRoot),
            splitRoots: $splitRoots,
        );

        $config = self::foldEntries($loaded['entries']);

        $validator = new ConfigValidator();

        $validation = $validator->validate($config, [
            self::kernelRuleset(),
        ]);

        self::assertTrue($validation->isSuccess());

        $compiledConfig = [
            'config' => $config,
            'sources' => self::sources($loaded),
            'owners' => [],
            'envOverlayMappings' => [],
            'configSourceFiles' => $loaded['sourceFiles'] ?? [],
            'validation' => ConfigValidationResult::success(),
            'validationSubjects' => $validator->validationSubjects($config, [
                self::kernelRuleset(),
            ]),
        ];

        return new CompiledConfigBuilder(
            new ArtifactEnvelopeFactory(new PayloadNormalizer()),
        )->build(
            compiledConfig: $compiledConfig,
            fingerprint: \str_repeat('a', 64),
        );
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

        $merger = new ConfigMerger(self::processor());
        $merged = [];

        foreach ($entries as $entry) {
            $merged = $merger->merge($merged, $entry['config']);

            self::assertIsArray($merged);
        }

        /** @var array<string,mixed> $merged */
        return $merged;
    }

    /**
     * @param array<string,mixed> $loaded
     *
     * @return list<ConfigValueSource>
     */
    private static function sources(array $loaded): array
    {
        $sources = $loaded['sources'] ?? [];

        self::assertIsArray($sources);

        foreach ($sources as $source) {
            self::assertInstanceOf(ConfigValueSource::class, $source);
        }

        /** @var list<ConfigValueSource> $sources */
        return $sources;
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
