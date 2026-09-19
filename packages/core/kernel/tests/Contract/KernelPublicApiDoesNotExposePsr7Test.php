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

use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Kernel\Runtime\KernelRuntime;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class KernelPublicApiDoesNotExposePsr7Test extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_TYPE_PREFIXES = [
        'Psr\\Http\\Message\\',
        'Psr\\Http\\Server\\',
    ];

    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_SOURCE_REFERENCES = [
        'Psr\\Http\\Message\\',
        'Psr\\Http\\Server\\',
    ];

    public function testKernelRuntimePublicApiDoesNotExposePsr7OrPsr15Types(): void
    {
        $this->assertPublicApiDoesNotReferenceForbiddenTypes(KernelRuntime::class);
    }

    public function testExternalRuntimePortPublicApiDoesNotExposePsr7OrPsr15Types(): void
    {
        $this->assertPublicApiDoesNotReferenceForbiddenTypes(KernelRuntimeInterface::class);
    }

    public function testExternalRuntimePortIsContractsKernelRuntimeInterface(): void
    {
        self::assertTrue(interface_exists(KernelRuntimeInterface::class));

        $contract = new ReflectionClass(KernelRuntimeInterface::class);

        self::assertTrue($contract->isInterface());
        self::assertSame(
            'Coretsia\\Contracts\\Runtime\\KernelRuntimeInterface',
            $contract->getName(),
        );

        $runtime = new ReflectionClass(KernelRuntime::class);

        self::assertTrue($runtime->implementsInterface(KernelRuntimeInterface::class));
    }

    public function testKernelDoesNotDefineCompetingRuntimeKernelRuntimeInterface(): void
    {
        self::assertFalse(
            interface_exists('Coretsia\\Kernel\\Runtime\\KernelRuntimeInterface'),
            'Kernel must not define a competing runtime port. Adapters must depend on Coretsia\\Contracts\\Runtime\\KernelRuntimeInterface.',
        );

        $runtimeFile = new ReflectionClass(KernelRuntime::class)->getFileName();

        self::assertIsString($runtimeFile);

        $competingInterfaceFile = \dirname($runtimeFile) . '/KernelRuntimeInterface.php';

        self::assertFileDoesNotExist(
            $competingInterfaceFile,
            'Kernel must not define Coretsia\\Kernel\\Runtime\\KernelRuntimeInterface. The external runtime port belongs to core/contracts.',
        );
    }

    public function testKernelSourceDoesNotReferencePsrHttpMessageOrPsrHttpServer(): void
    {
        foreach ($this->kernelSourceFiles() as $file) {
            $source = \file_get_contents($file);

            self::assertIsString($source);

            foreach (self::FORBIDDEN_SOURCE_REFERENCES as $forbiddenReference) {
                self::assertStringNotContainsString(
                    $forbiddenReference,
                    $source,
                    \sprintf(
                        'Kernel source file "%s" must not reference forbidden PSR HTTP namespace "%s".',
                        $file,
                        $forbiddenReference,
                    ),
                );
            }
        }
    }

    public function testGuardDoesNotForbidAllowedPsrInfrastructureTypes(): void
    {
        $this->assertTypeNameIsAllowed(ContainerInterface::class);
        $this->assertTypeNameIsAllowed(LoggerInterface::class);
    }

    public function testKernelPackageMayRequirePsrContainerAndPsrLogButNotPsrHttpPackages(): void
    {
        $composerFile = $this->kernelPackageRoot() . '/composer.json';

        self::assertFileExists($composerFile);

        $composer = \json_decode(
            (string)\file_get_contents($composerFile),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);

        $require = $composer['require'] ?? [];

        self::assertIsArray($require);

        self::assertArrayHasKey('psr/container', $require);
        self::assertArrayHasKey('psr/log', $require);

        self::assertArrayNotHasKey('psr/http-message', $require);
        self::assertArrayNotHasKey('psr/http-server-handler', $require);
        self::assertArrayNotHasKey('psr/http-server-middleware', $require);
    }

    /**
     * @param class-string $class
     */
    private function assertPublicApiDoesNotReferenceForbiddenTypes(string $class): void
    {
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $this->assertMethodSignatureDoesNotReferenceForbiddenTypes($method);
        }
    }

    private function assertMethodSignatureDoesNotReferenceForbiddenTypes(ReflectionMethod $method): void
    {
        foreach ($method->getParameters() as $parameter) {
            $this->assertParameterSignatureDoesNotReferenceForbiddenTypes($parameter);
        }

        $returnType = $method->getReturnType();

        if ($returnType !== null) {
            $this->assertTypeDoesNotReferenceForbiddenTypes($returnType);
        }
    }

    private function assertParameterSignatureDoesNotReferenceForbiddenTypes(ReflectionParameter $parameter): void
    {
        $type = $parameter->getType();

        if ($type === null) {
            return;
        }

        $this->assertTypeDoesNotReferenceForbiddenTypes($type);
    }

    private function assertTypeDoesNotReferenceForbiddenTypes(ReflectionType $type): void
    {
        foreach ($this->typeNames($type) as $typeName) {
            $this->assertTypeNameIsAllowed($typeName);
        }
    }

    private function assertTypeNameIsAllowed(string $typeName): void
    {
        $normalizedTypeName = \ltrim($typeName, '\\');

        foreach (self::FORBIDDEN_TYPE_PREFIXES as $forbiddenPrefix) {
            self::assertStringStartsNotWith(
                $forbiddenPrefix,
                $normalizedTypeName,
                \sprintf(
                    'Kernel public API must not expose forbidden PSR HTTP type "%s".',
                    $typeName,
                ),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function typeNames(ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $names = [];

            foreach ($type->getTypes() as $nestedType) {
                $names = [
                    ...$names,
                    ...$this->typeNames($nestedType),
                ];
            }

            return $names;
        }

        self::fail(
            \sprintf(
                'Unsupported reflection type "%s".',
                $type::class,
            ),
        );
    }

    /**
     * @return list<non-empty-string>
     */
    private function kernelSourceFiles(): array
    {
        $sourceRoot = $this->kernelPackageRoot() . '/src';

        self::assertDirectoryExists($sourceRoot);

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $sourceRoot,
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $path = $fileInfo->getPathname();

            if ($path === '') {
                continue;
            }

            $files[] = $path;
        }

        \usort(
            $files,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        self::assertNotSame([], $files);

        /** @var list<non-empty-string> $files */
        return $files;
    }

    private function kernelPackageRoot(): string
    {
        $runtimeFile = new ReflectionClass(KernelRuntime::class)->getFileName();

        self::assertIsString($runtimeFile);

        return \dirname($runtimeFile, 3);
    }
}
