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

use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPathInvalidException;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use PHPUnit\Framework\TestCase;

final class KernelDoesNotEmitRoutesArtifactContractTest extends TestCase
{
    public function testArtifactCompilerSourceDoesNotWriteRoutesArtifact(): void
    {
        $source = self::sourceFile('src/Artifacts/Compiler/ArtifactCompiler.php');

        self::assertStringContainsString('module-manifest', $source);
        self::assertStringContainsString('config', $source);
        self::assertStringContainsString('container', $source);

        self::assertStringNotContainsString('routes.php', $source);
        self::assertStringNotContainsString('routes@1', $source);
        self::assertStringNotContainsString('ARTIFACT_ROUTES', $source);
    }

    public function testArtifactBuildersListDoesNotContainRoutesBuilder(): void
    {
        $builderFiles = \glob(self::packageRoot() . '/src/Artifacts/Builders/*Builder.php');

        self::assertIsArray($builderFiles);

        $builderBasenames = \array_map(
            static fn (string $path): string => \basename($path),
            $builderFiles,
        );

        \sort($builderBasenames, \SORT_STRING);

        self::assertSame(
            [
                'CompiledConfigBuilder.php',
                'CompiledContainerBuilder.php',
                'ModuleManifestBuilder.php',
            ],
            $builderBasenames,
        );

        self::assertNotContains('RoutesBuilder.php', $builderBasenames);
        self::assertNotContains('RouteBuilder.php', $builderBasenames);
    }

    public function testPathResolverRejectsRoutesPhpBasename(): void
    {
        $resolver = new ArtifactPathResolver();

        try {
            $resolver->relativePath(
                bootstrapConfig: self::bootstrapConfig(),
                kernelConfig: self::kernelConfig(),
                basename: 'routes.php',
            );

            self::fail('Expected ArtifactPathInvalidException was not thrown.');
        } catch (ArtifactPathInvalidException $exception) {
            self::assertSame(ArtifactPathInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ArtifactPathInvalidException::REASON_BASENAME_INVALID, $exception->reason());
            self::assertStringNotContainsString('routes.php', $exception->getMessage());
        }
    }

    public function testKernelOwnedSchemaValidatorDoesNotClaimRoutesArtifactOwnership(): void
    {
        $validator = new ArtifactSchemaValidator();

        try {
            $validator->validate([
                '_meta' => [
                    'name' => 'routes',
                    'schemaVersion' => 1,
                    'fingerprint' => \str_repeat('a', 64),
                    'generator' => 'platform/routing/artifacts',
                ],
                'payload' => [
                    'routes' => [],
                ],
            ]);

            self::fail('Expected ArtifactInvalidException was not thrown.');
        } catch (ArtifactInvalidException $exception) {
            self::assertSame(ArtifactInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ArtifactInvalidException::REASON_NAME_MISMATCH, $exception->reason());
        }
    }

    public function testRoutesArtifactOwnershipRemainsDocumentedAsPlatformRouting(): void
    {
        $adr = self::repoFile('docs/adr/ADR-0028-kernel-artifacts-fingerprint-cache-verify.md');
        $artifactsSsot = self::repoFile('docs/ssot/artifacts.md');

        self::assertStringContainsString('routes@1', $adr);
        self::assertStringContainsString('platform/routing', $adr);

        self::assertStringContainsString('routes@1', $artifactsSsot);
        self::assertStringContainsString('platform/routing', $artifactsSsot);
    }

    private static function bootstrapConfig(): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: 'micro',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Api,
            skeletonRoot: '/workspace/skeleton',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function kernelConfig(): array
    {
        return [
            'artifacts' => [
                'cache_dir' => 'var/cache',
            ],
        ];
    }

    private static function sourceFile(string $relativePath): string
    {
        $path = self::packageRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function repoFile(string $relativePath): string
    {
        $path = self::repoRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    private static function repoRoot(): string
    {
        return \dirname(self::packageRoot(), 4);
    }
}
