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

use PHPUnit\Framework\TestCase;

final class KernelArtifactServicesRegisterAsFactoriesOnlyTest extends TestCase
{
    public function testProviderRegistersArtifactFingerprintAndCacheServicesAsFactoriesOnly(): void
    {
        $source = self::providerSource();

        foreach (
            [
                'PayloadNormalizer::class',
                'ArtifactEnvelopeFactory::class',
                'ArtifactWriter::class',
                'ModuleManifestBuilder::class',
                'CompiledConfigBuilder::class',
                'CompiledContainerBuilder::class',
                'ContainerCompiler::class',
                'ArtifactCompiler::class',
                'ConfigFingerprintInputBuilder::class',
                'DeterministicFileLister::class',
                'FingerprintCalculator::class',
                'FingerprintExplainer::class',
                'ArtifactPathResolver::class',
                'PhpArtifactReader::class',
                'StablePhpArrayDumper::class',
                'ArtifactSchemaValidator::class',
                'CacheVerifier::class',
            ] as $serviceClassReference
        ) {
            self::assertStringContainsString(
                '$builder->factory(' . "\n" . '            ' . $serviceClassReference,
                $source,
                $serviceClassReference . ' must be registered through ContainerBuilder::factory(...).',
            );
        }

        self::assertStringNotContainsString('$builder->singleton(', $source);
        self::assertStringNotContainsString('$builder->instance(', $source);
    }

    public function testProviderRegistrationDoesNotWriteArtifacts(): void
    {
        $source = self::providerSource();

        self::assertStringNotContainsString('writePhpEnvelope(', $source);
        self::assertStringNotContainsString('writeTextArtifact(', $source);
        self::assertStringNotContainsString('file_put_contents(', $source);
        self::assertStringNotContainsString('rename(', $source);
    }

    public function testProviderRegistrationDoesNotReadArtifacts(): void
    {
        $source = self::providerSource();

        self::assertStringNotContainsString('PhpArtifactReader::read', $source);
        self::assertStringNotContainsString('->read(', $source);
        self::assertStringNotContainsString('include ', $source);
        self::assertStringNotContainsString('require ', $source);
    }

    public function testProviderRegistrationDoesNotCalculateFingerprints(): void
    {
        $source = self::providerSource();

        self::assertStringNotContainsString('->calculate(', $source);
        self::assertStringNotContainsString('::calculate(', $source);
        self::assertStringNotContainsString('StableJsonEncoder::encodeStable(', $source);
        self::assertStringNotContainsString("hash('sha256'", $source);
        self::assertStringNotContainsString('hash("sha256"', $source);
    }

    public function testProviderRegistrationDoesNotRunCacheVerification(): void
    {
        $source = self::providerSource();

        self::assertStringNotContainsString('->verify(', $source);
        self::assertStringNotContainsString('::verify(', $source);
    }

    public function testProviderRegistrationDoesNotResolveBootstrapConfigOrModulePlan(): void
    {
        $source = self::providerSource();

        self::assertStringNotContainsString('get(BootstrapConfig::class)', $source);
        self::assertStringNotContainsString('get(ModulePlan::class)', $source);
        self::assertStringNotContainsString('$container->get(BootstrapConfig::class)', $source);
        self::assertStringNotContainsString('$container->get(ModulePlan::class)', $source);
    }

    public function testProviderRegistrationDoesNotRunConfigKernelCompile(): void
    {
        $source = self::providerSource();

        self::assertStringNotContainsString('->compile(', $source);
        self::assertStringNotContainsString('ConfigKernel::compile(', $source);
    }

    private static function providerSource(): string
    {
        return self::stripPhpComments(
            self::sourceFile('src/Provider/KernelServiceProvider.php'),
        );
    }

    private static function sourceFile(string $relativePath): string
    {
        $path = self::packageRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
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
}
