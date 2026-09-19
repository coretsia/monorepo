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

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestBuilder;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactPublicationSet;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArtifactGenerationLocatorRejectsSymlinkTest extends TestCase
{
    #[DataProvider('symlinkScenarioProvider')]
    public function testRejectsSymlinkSubstitution(
        string $scenario,
    ): void {
        if (!\function_exists('symlink')) {
            self::markTestSkipped('symlink() is unavailable in this environment.');
        }

        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-generation-symlink-' . $scenario);
        $artifactRoot = $root . '/var/cache/web';

        try {
            \mkdir($artifactRoot, 0777, true);

            $generation = self::publisher($this)->publish(
                artifactRoot: $artifactRoot,
                publicationSet: self::publicationSet(
                    fingerprint: self::fingerprint('a'),
                    configValue: 'safe-value',
                ),
            );

            self::validator()->validate($generation);
            self::substituteSymlink(
                root: $root,
                artifactRoot: $artifactRoot,
                generation: $generation,
                scenario: $scenario,
            );

            try {
                self::locator()->locate($artifactRoot);
                self::fail('Expected ArtifactInvalidException was not thrown.');
            } catch (
                ArtifactInvalidException $exception
            ) {
                self::assertSame(
                    ArtifactInvalidException::ERROR_CODE,
                    $exception->errorCode(),
                );
                self::assertSame(
                    ArtifactInvalidException::REASON_INVALID,
                    $exception->reason(),
                );
                self::assertSame(
                    ArtifactInvalidException::ERROR_CODE
                    . ': '
                    . ArtifactInvalidException::REASON_INVALID,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    $artifactRoot,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString(
                    self::fingerprint('a'),
                    $exception->getMessage(),
                );
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @return iterable<string, array{0:string}>
     */
    public static function symlinkScenarioProvider(): iterable
    {
        yield 'current-pointer' => [
            'current-pointer',
        ];

        yield 'generation-directory' => [
            'generation-directory',
        ];

        yield 'generations-parent' => [
            'generations-parent',
        ];

        yield 'artifact-file' => [
            'artifact-file',
        ];
    }

    private static function substituteSymlink(
        string $root,
        string $artifactRoot,
        ArtifactGeneration $generation,
        string $scenario,
    ): void {
        $pathResolver = new ArtifactGenerationPathResolver();

        switch ($scenario) {
            case 'current-pointer':
                $currentPath = $pathResolver->currentPath($artifactRoot);
                $targetPath = $root . '/current-target';
                $pointer = \file_get_contents($currentPath);

                self::assertIsString($pointer);
                self::assertSame(
                    \strlen($pointer),
                    \file_put_contents(
                        $targetPath,
                        $pointer,
                    ),
                );
                self::assertTrue(
                    \unlink($currentPath),
                );
                self::createSymlinkOrSkip(
                    $targetPath,
                    $currentPath,
                );

                return;

            case 'generation-directory':
                $targetDirectory = $root . '/generation-target';

                self::assertTrue(
                    \rename(
                        $generation->generationDirectory(),
                        $targetDirectory,
                    ),
                );
                self::createSymlinkOrSkip(
                    $targetDirectory,
                    $generation->generationDirectory(),
                );

                return;

            case 'generations-parent':
                $generationsDirectory = $pathResolver->generationsDirectory($artifactRoot);
                $targetDirectory = $root . '/generations-target';

                self::assertTrue(
                    \rename(
                        $generationsDirectory,
                        $targetDirectory,
                    ),
                );
                self::createSymlinkOrSkip(
                    $targetDirectory,
                    $generationsDirectory,
                );

                return;

            case 'artifact-file':
                $targetPath = $root . '/config-target.php';

                self::assertTrue(
                    \rename(
                        $generation->configPath(),
                        $targetPath,
                    ),
                );
                self::createSymlinkOrSkip(
                    $targetPath,
                    $generation->configPath(),
                );

                return;

            default:
                self::fail('Unknown symlink scenario: ' . $scenario);
        }
    }

    private static function createSymlinkOrSkip(
        string $target,
        string $link,
    ): void {
        if (!@\symlink($target, $link)) {
            self::markTestSkipped('Filesystem symlink creation is unavailable in this environment.');
        }

        self::assertTrue(
            \is_link($link),
        );
    }

    private static function publisher(
        TestCase $testCase,
    ): ArtifactGenerationPublisher {
        $normalizer = new PayloadNormalizer();
        $envelopeFactory = new ArtifactEnvelopeFactory($normalizer);
        $dumper = new StablePhpArrayDumper($normalizer);
        $pathResolver = new ArtifactGenerationPathResolver();

        return new ArtifactGenerationPublisher(
            artifactWriter: ArtifactPipelineTestSupport::artifactWriter($testCase),
            phpArrayDumper: $dumper,
            manifestBuilder: new ArtifactGenerationManifestBuilder($envelopeFactory),
            validator: self::validator(),
            lock: new ArtifactGenerationLock($pathResolver),
            pathResolver: $pathResolver,
        );
    }

    private static function locator(): ArtifactGenerationLocator
    {
        $pathResolver = new ArtifactGenerationPathResolver();

        return new ArtifactGenerationLocator(
            lock: new ArtifactGenerationLock($pathResolver),
            pathResolver: $pathResolver,
            validator: self::validator(),
        );
    }

    private static function validator(): ArtifactGenerationValidator
    {
        $schemaValidator = new ArtifactSchemaValidator();

        return new ArtifactGenerationValidator(
            artifactReader: new PhpArtifactReader(),
            schemaValidator: $schemaValidator,
            manifestValidator: new ArtifactGenerationManifestValidator($schemaValidator),
        );
    }

    private static function publicationSet(
        string $fingerprint,
        string $configValue,
    ): ArtifactPublicationSet {
        $normalizer = new PayloadNormalizer();
        $envelopeFactory = new ArtifactEnvelopeFactory($normalizer);
        $dumper = new StablePhpArrayDumper($normalizer);

        $moduleManifestEnvelope = new ModuleManifestBuilder($envelopeFactory)->build(
            modulePlan: self::modulePlan(),
            fingerprint: $fingerprint,
        );

        $configEnvelope = new CompiledConfigBuilder($envelopeFactory)->build(
            compiledConfig: self::compiledConfig($configValue),
            fingerprint: $fingerprint,
        );

        $containerEnvelope = new CompiledContainerBuilder($envelopeFactory)->build(
            graph: DefinitionGraph::empty(),
            fingerprint: $fingerprint,
        );

        return new ArtifactPublicationSet(
            moduleManifestEnvelope: $moduleManifestEnvelope,
            moduleManifestBytes: $dumper->dumpEnvelope($moduleManifestEnvelope),
            configEnvelope: $configEnvelope,
            configBytes: $dumper->dumpEnvelope($configEnvelope),
            containerEnvelope: $containerEnvelope,
            containerBytes: $dumper->dumpEnvelope($containerEnvelope),
        );
    }

    private static function modulePlan(): ModulePlan
    {
        return new ModulePlan(
            app: 'web',
            preset: 'default',
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
    private static function compiledConfig(
        string $configValue,
    ): array {
        return [
            'config' => [
                'custom' => [
                    'feature' => [
                        'value' => $configValue,
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
                'validated' => [],
            ],
        ];
    }

    private static function fingerprint(
        string $character,
    ): string {
        return \str_repeat(
            $character,
            64,
        );
    }
}
