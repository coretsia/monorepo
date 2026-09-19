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

use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Artifacts\Verifier\CacheVerifier;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Source\ConfigSourceSet;
use PHPUnit\Framework\TestCase;

final class KernelArtifactConfigSourceBoundaryContractTest extends TestCase
{
    public function testCanonicalConsumersRequireConfigSourceSet(): void
    {
        foreach (
            [
                [ConfigKernel::class, 'compile'],
                [ConfigFingerprintInputBuilder::class, 'build'],
                [ArtifactCompiler::class, 'compile'],
                [CacheVerifier::class, 'verify'],
            ] as [$class, $method]
        ) {
            $reflection = new \ReflectionMethod($class, $method);
            $parameter = $reflection->getParameters();
            $byName = [];

            foreach ($parameter as $item) {
                $byName[$item->getName()] = $item;
            }

            self::assertArrayHasKey('configSources', $byName, $class . '::' . $method);
            $type = $byName['configSources']->getType();

            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame(ConfigSourceSet::class, $type->getName());
            self::assertFalse($type->allowsNull());
        }
    }

    public function testCompilerAndVerifierDoNotExposeOldRawSourceParameters(): void
    {
        $forbidden = [
            'packageDefaultSources',
            'packageRuleSources',
            'splitRoots',
            'explicitRuleSources',
            'explicitEnvOverlayMappings',
            'modePresetSourceCandidates',
        ];

        foreach (
            [
                [ArtifactCompiler::class, 'compile'],
                [CacheVerifier::class, 'verify'],
            ] as [$class, $method]
        ) {
            $names = \array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                new \ReflectionMethod($class, $method)->getParameters(),
            );

            foreach ($forbidden as $parameter) {
                self::assertNotContains($parameter, $names, $class . '::' . $method);
            }
        }
    }

    public function testCompilerAndVerifierForwardSameConfigSourceVariable(): void
    {
        foreach (
            [
                'src/Artifacts/Compiler/ArtifactCompiler.php',
                'src/Artifacts/Verifier/CacheVerifier.php',
            ] as $relativePath
        ) {
            $source = self::sourceWithoutComments($relativePath);

            self::assertSame(
                2,
                \substr_count(
                    $source,
                    'configSources: $configSources',
                ),
                $relativePath,
            );

            self::assertMatchesRegularExpression(
                '/\$this->configKernel->compile\s*\([^;]*'
                . 'configSources:\s*\$configSources[^;]*\);/s',
                $source,
                $relativePath
                . ' must forward the method parameter to ConfigKernel::compile().',
            );

            self::assertMatchesRegularExpression(
                '/\$this->fingerprintInputBuilder->build\s*\([^;]*'
                . 'configSources:\s*\$configSources[^;]*\);/s',
                $source,
                $relativePath
                . ' must forward the same method parameter to '
                . 'ConfigFingerprintInputBuilder::build().',
            );

            self::assertStringNotContainsString(
                'new ConfigSourceSet(',
                $source,
            );
            self::assertStringNotContainsString(
                'ConfigSourceLocationBuilder',
                $source,
            );
        }
    }

    public function testLowerCompileLayersDoNotOwnSourceDiscovery(): void
    {
        $configKernel = self::sourceWithoutComments('src/Config/ConfigKernel.php');

        foreach (
            [
                'ConfigSourceLocationBuilder',
                'InstalledVersions',
                'getInstallPath',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $configKernel);
        }

        foreach (
            [
                'src/Artifacts/Compiler/ArtifactCompiler.php',
                'src/Artifacts/Verifier/CacheVerifier.php',
            ] as $relativePath
        ) {
            $source = self::sourceWithoutComments($relativePath);

            foreach (
                [
                    'ConfigSourceLocationBuilder',
                    'InstalledVersions',
                    'ModulePlanResolver',
                    'resolveResolution(',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source, $relativePath);
            }
        }
    }

    public function testKernelArtifactOperationAcceptsOnlyBootstrapInput(): void
    {
        foreach (['compile', 'verify'] as $method) {
            $parameters = new \ReflectionMethod(KernelArtifactOperation::class, $method)
                ->getParameters();

            self::assertCount(1, $parameters);
            self::assertSame('input', $parameters[0]->getName());
            $type = $parameters[0]->getType();

            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame(BootstrapInput::class, $type->getName());
        }
    }

    public function testBlockerDoesNotIntroducePublicBootstrapperOrBootstrapResult(): void
    {
        $roots = [
            self::packageRoot() . '/src',
            \dirname(self::packageRoot()) . '/contracts/src',
        ];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }

                $source = self::stripPhpComments((string) \file_get_contents($file->getPathname()));

                self::assertStringNotContainsString('class BootstrapResult', $source);
                self::assertStringNotContainsString('interface Bootstrapper', $source);
                self::assertStringNotContainsString('class Bootstrapper', $source);
            }
        }
    }

    public function testComposerInstallRootResolverUsesOnlyExactComposerLookup(): void
    {
        $source = self::sourceWithoutComments('src/Config/Source/ComposerPackageInstallPathResolver.php');

        self::assertStringContainsString('InstalledVersions::getInstallPath(', $source);

        foreach (
            [
                'getAllRawData(',
                'scandir(',
                'glob(',
                'DirectoryIterator',
                'RecursiveDirectoryIterator',
                'realpath(',
                'composer.json',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function testLowerCompileHostLayersDoNotReconstructConfigSourceSet(): void
    {
        foreach (
            [
                'src/Artifacts/Compiler/ArtifactCompiler.php',
                'src/Artifacts/Verifier/CacheVerifier.php',
            ] as $relativePath
        ) {
            $source = self::sourceWithoutComments($relativePath);

            self::assertStringNotContainsString('new ConfigSourceSet(', $source);
            self::assertStringNotContainsString('ConfigSourceLocationBuilder', $source);
            self::assertStringContainsString('configSources: $configSources', $source);
        }
    }

    public function testCanonicalOperationCannotBypassSourceBuilderWithEmptySet(): void
    {
        $source = self::sourceWithoutComments('src/Artifacts/Operation/KernelArtifactOperation.php');

        self::assertStringNotContainsString('ConfigSourceSet::empty(', $source);
        self::assertStringContainsString('resolveResolution(', $source);
        self::assertStringContainsString('configSourceLocationBuilder->build(', $source);
        self::assertStringContainsString("'configSources' => \$configSources", $source);
    }

    private static function sourceWithoutComments(string $relativePath): string
    {
        $path = self::packageRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return self::stripPhpComments($source);
    }

    private static function stripPhpComments(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
