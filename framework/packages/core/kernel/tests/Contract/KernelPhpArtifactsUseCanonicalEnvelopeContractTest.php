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

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;

final class KernelPhpArtifactsUseCanonicalEnvelopeContractTest extends TestCase
{
    public function testKernelOwnedPhpArtifactsReturnCanonicalTopLevelEnvelope(): void
    {
        $validator = new ArtifactSchemaValidator();

        $artifacts = [
            'module-manifest.php' => [
                'name' => ArtifactEnvelopeFactory::ARTIFACT_MODULE_MANIFEST,
                'schemaVersion' => ArtifactEnvelopeFactory::SCHEMA_VERSION_MODULE_MANIFEST,
                'envelope' => self::moduleManifestBuilder()->build(
                    modulePlan: self::modulePlan(),
                    fingerprint: self::fingerprint(),
                ),
            ],
            'config.php' => [
                'name' => ArtifactEnvelopeFactory::ARTIFACT_CONFIG,
                'schemaVersion' => ArtifactEnvelopeFactory::SCHEMA_VERSION_CONFIG,
                'envelope' => self::compiledConfigBuilder()->build(
                    compiledConfig: self::compiledConfig(),
                    fingerprint: self::fingerprint(),
                ),
            ],
            'container.php' => [
                'name' => ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
                'schemaVersion' => ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
                'envelope' => self::compiledContainerBuilder()->build(
                    graph: self::emptyContainerGraph(),
                    fingerprint: self::fingerprint(),
                ),
            ],
        ];

        foreach ($artifacts as $basename => $artifact) {
            $returned = self::includePhpReturn(
                self::dumper()->dumpEnvelope($artifact['envelope']),
            );

            self::assertSame(
                ['_meta', 'payload'],
                \array_keys($returned),
                $basename . ' must return only the canonical envelope top-level keys.',
            );

            self::assertArrayNotHasKey('artifact', $returned);
            self::assertArrayNotHasKey('data', $returned);
            self::assertArrayNotHasKey('config', $returned);
            self::assertArrayNotHasKey('moduleManifest', $returned);
            self::assertArrayNotHasKey('container', $returned);

            $validator->validateExpected(
                envelope: $returned,
                expectedName: $artifact['name'],
                expectedSchemaVersion: $artifact['schemaVersion'],
            );
        }
    }

    public function testBuildersProduceCanonicalEnvelopesWithoutArtifactSpecificTopLevelShapes(): void
    {
        $envelopes = [
            self::moduleManifestBuilder()->build(
                modulePlan: self::modulePlan(),
                fingerprint: self::fingerprint(),
            ),
            self::compiledConfigBuilder()->build(
                compiledConfig: self::compiledConfig(),
                fingerprint: self::fingerprint(),
            ),
            self::compiledContainerBuilder()->build(
                graph: self::emptyContainerGraph(),
                fingerprint: self::fingerprint(),
            ),
        ];

        foreach ($envelopes as $envelope) {
            self::assertSame(['_meta', 'payload'], \array_keys($envelope));
            self::assertArrayHasKey('name', $envelope['_meta']);
            self::assertArrayHasKey('schemaVersion', $envelope['_meta']);
            self::assertArrayHasKey('fingerprint', $envelope['_meta']);
            self::assertArrayHasKey('generator', $envelope['_meta']);

            self::assertArrayNotHasKey('artifact', $envelope);
            self::assertArrayNotHasKey('header', $envelope);
            self::assertArrayNotHasKey('data', $envelope);
            self::assertArrayNotHasKey('moduleManifest', $envelope);
            self::assertArrayNotHasKey('compiledConfig', $envelope);
            self::assertArrayNotHasKey('container', $envelope);
        }
    }

    public function testBuildersUseArtifactEnvelopeFactoryAsCanonicalFactoryPath(): void
    {
        foreach (
            [
                ModuleManifestBuilder::class,
                CompiledConfigBuilder::class,
                CompiledContainerBuilder::class,
            ] as $builderClass
        ) {
            $constructor = new \ReflectionMethod($builderClass, '__construct');
            $parameters = $constructor->getParameters();

            self::assertCount(1, $parameters, $builderClass . ' must receive exactly one canonical envelope factory.');

            $type = $parameters[0]->getType();

            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame(ArtifactEnvelopeFactory::class, $type->getName());
        }
    }

    private static function moduleManifestBuilder(): ModuleManifestBuilder
    {
        return new ModuleManifestBuilder(self::envelopeFactory());
    }

    private static function compiledConfigBuilder(): CompiledConfigBuilder
    {
        return new CompiledConfigBuilder(self::envelopeFactory());
    }

    private static function compiledContainerBuilder(): CompiledContainerBuilder
    {
        return new CompiledContainerBuilder(self::envelopeFactory());
    }

    private static function emptyContainerGraph(): DefinitionGraph
    {
        return DefinitionGraph::empty();
    }

    private static function envelopeFactory(): ArtifactEnvelopeFactory
    {
        return new ArtifactEnvelopeFactory(new PayloadNormalizer());
    }

    private static function dumper(): StablePhpArrayDumper
    {
        return new StablePhpArrayDumper(new PayloadNormalizer());
    }

    private static function modulePlan(): ModulePlan
    {
        return new ModulePlan(
            app: 'api',
            preset: 'micro',
            enabled: [],
            disabled: [],
            optionalMissing: [],
            topologicalOrder: [],
            modules: [],
            warnings: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function compiledConfig(): array
    {
        return [
            'config' => [
                'kernel' => [
                    'artifacts' => [
                        'cache_dir' => 'var/cache',
                    ],
                ],
            ],
            'sources' => [],
            'owners' => [],
            'envOverlayMappings' => [],
            'configSourceFiles' => [],
            'validation' => ConfigValidationResult::success(),
            'validationSubjects' => [
                'unvalidated' => [],
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'kernel',
                        'validation' => 'validated',
                    ],
                ],
            ],
        ];
    }

    private static function fingerprint(): string
    {
        return \str_repeat('a', 64);
    }

    /**
     * @return array<string, mixed>
     */
    private static function includePhpReturn(string $bytes): array
    {
        $path = \tempnam(\sys_get_temp_dir(), 'coretsia-kernel-artifact-');

        if ($path === false) {
            self::fail('Failed to create temporary artifact file.');
        }

        try {
            \file_put_contents($path, $bytes);

            $returned = (static function (string $__path): mixed {
                return include $__path;
            })(
                $path
            );

            self::assertIsArray($returned);

            /** @var array<string, mixed> $returned */
            return $returned;
        } finally {
            if (\is_file($path)) {
                \unlink($path);
            }
        }
    }
}
