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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Kernel\Ops\OpsResult;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class KernelOpsContractsShapeContractTest extends TestCase
{
    public function testKernelOpsInterfaceExposesExactSixMethodSurface(): void
    {
        $interface = new ReflectionClass(KernelOpsInterface::class);

        self::assertTrue($interface->isInterface());
        self::assertSame(
            [
                'validateConfig',
                'debugConfig',
                'compileConfig',
                'hashConfig',
                'verifyCache',
                'debugModules',
            ],
            \array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $interface->getMethods(),
            ),
        );

        foreach ($interface->getMethods() as $method) {
            self::assertTrue($method->isPublic());
            self::assertFalse($method->isStatic());
            self::assertSame(1, $method->getNumberOfParameters());
            self::assertSame(1, $method->getNumberOfRequiredParameters());

            $parameter = $method->getParameters()[0];

            self::assertSame('request', $parameter->getName());
            self::assertNamedType($parameter->getType(), KernelOpsRequest::class, false);
            self::assertNamedType($method->getReturnType(), OpsResult::class, false);
        }
    }

    public function testKernelOpsRequestContainsExplicitAppTargetOnly(): void
    {
        $class = new ReflectionClass(KernelOpsRequest::class);

        self::assertTrue($class->isFinal());
        self::assertTrue($class->isReadOnly());
        self::assertSame(
            ['__construct', 'appTarget'],
            \array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $class->getMethods(),
            ),
        );
        self::assertSame(
            ['appTarget'],
            \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $class->getProperties(),
            ),
        );

        $constructor = $class->getConstructor();

        self::assertNotNull($constructor);
        self::assertSame(1, $constructor->getNumberOfParameters());
        self::assertSame(1, $constructor->getNumberOfRequiredParameters());
        self::assertSame('appTarget', $constructor->getParameters()[0]->getName());
        self::assertNamedType($constructor->getParameters()[0]->getType(), 'string', false);

        $request = new KernelOpsRequest('custom-target');

        self::assertSame('custom-target', $request->appTarget());
    }

    public function testKernelOpsRequestRejectsInvalidStructuralInputAtConstructionBoundary(): void
    {
        foreach (
            [
                '',
                "web\napi",
                "web\rapi",
                "web\0api",
                "web\x1Fapi",
                "web\x7Fapi",
            ] as $invalid
        ) {
            try {
                new KernelOpsRequest($invalid);

                self::fail('Expected structurally invalid Kernel Ops app target to be rejected by the constructor.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testOpsResultSchemaVersionOutcomeAndReasonSemanticsAreStable(): void
    {
        self::assertSame(1, OpsResult::SCHEMA_VERSION);

        $resultReflection = new ReflectionClass(OpsResult::class);
        $outcomes = $resultReflection->getConstant('OUTCOMES');

        self::assertSame(
            [
                'handled_error' => true,
                'success' => true,
            ],
            $outcomes,
        );

        $success = new OpsResult(
            operation: 'config.validate',
            appTarget: 'console',
            preset: 'hybrid',
            outcome: 'success',
            reason: null,
        );

        self::assertSame(1, $success->schemaVersion());
        self::assertSame('success', $success->outcome());
        self::assertNull($success->reason());

        $handledBeforeTargetValidation = new OpsResult(
            operation: 'config.validate',
            appTarget: null,
            preset: null,
            outcome: 'handled_error',
            reason: 'invalid-target',
        );

        self::assertSame('handled_error', $handledBeforeTargetValidation->outcome());
        self::assertNull($handledBeforeTargetValidation->appTarget());
        self::assertNull($handledBeforeTargetValidation->preset());
        self::assertSame('invalid-target', $handledBeforeTargetValidation->reason());

        self::assertOpsResultRejected(
            appTarget: 'console',
            preset: 'hybrid',
            outcome: 'failure',
            reason: 'operation-failed',
        );
        self::assertOpsResultRejected(
            appTarget: null,
            preset: 'hybrid',
            outcome: 'success',
            reason: null,
        );
        self::assertOpsResultRejected(
            appTarget: 'console',
            preset: null,
            outcome: 'success',
            reason: null,
        );
        self::assertOpsResultRejected(
            appTarget: 'console',
            preset: 'hybrid',
            outcome: 'success',
            reason: 'must-not-exist',
        );
        self::assertOpsResultRejected(
            appTarget: 'console',
            preset: 'hybrid',
            outcome: 'handled_error',
            reason: null,
        );
        self::assertOpsResultRejected(
            appTarget: null,
            preset: 'hybrid',
            outcome: 'handled_error',
            reason: 'invalid-target',
        );
    }

    public function testKernelOpsFailedExceptionPublicApiIsStableAndSafe(): void
    {
        $reflection = new ReflectionClass(KernelOpsFailedException::class);
        $declaredPublicMethods = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== KernelOpsFailedException::class) {
                continue;
            }

            $declaredPublicMethods[] = $method->getName();
        }

        self::assertSame(
            ['__construct', 'errorCode', 'reason'],
            $declaredPublicMethods,
        );
        self::assertSame('CORETSIA_KERNEL_OPS_FAILED', KernelOpsFailedException::ERROR_CODE);
        self::assertSame('operation-failed', KernelOpsFailedException::REASON_OPERATION_FAILED);
        self::assertSame('host-boot-failed', KernelOpsFailedException::REASON_HOST_BOOT_FAILED);

        foreach (
            [
                KernelOpsFailedException::REASON_OPERATION_FAILED,
                KernelOpsFailedException::REASON_HOST_BOOT_FAILED,
            ] as $reason
        ) {
            $exception = new KernelOpsFailedException($reason);

            self::assertSame(KernelOpsFailedException::ERROR_CODE, $exception->errorCode());
            self::assertSame($reason, $exception->reason());
            self::assertSame(KernelOpsFailedException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        $this->expectException(\InvalidArgumentException::class);

        new KernelOpsFailedException('unknown-reason');
    }

    public function testContractsOwnedKernelOpsPortHasNoImplementationDependencies(): void
    {
        $root = self::contractsRoot() . '/src/Kernel/Ops';

        self::assertDirectoryExists($root);

        $violations = [];

        foreach (self::phpFiles($root) as $file) {
            $source = \file_get_contents($file);

            self::assertIsString($source);

            $code = self::phpWithoutComments($source);
            $relative = self::relativePath($root, $file);

            foreach (self::forbiddenDependencyPatterns() as $label => $pattern) {
                if (\preg_match($pattern, $code) === 1) {
                    $violations[] = $relative . ': ' . $label;
                }
            }
        }

        \sort($violations, \SORT_STRING);

        self::assertSame([], $violations, \implode("\n", $violations));
    }

    private static function assertOpsResultRejected(
        ?string $appTarget,
        ?string $preset,
        string $outcome,
        ?string $reason,
    ): void {
        try {
            new OpsResult(
                operation: 'config.validate',
                appTarget: $appTarget,
                preset: $preset,
                outcome: $outcome,
                reason: $reason,
            );

            self::fail('Expected invalid Kernel Ops result semantics to be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    /**
     * @return array<string, string>
     */
    private static function forbiddenDependencyPatterns(): array
    {
        return [
            'forbidden-core-kernel-implementation' => '/\bCoretsia\\\\Kernel\\\\/',
            'forbidden-platform-implementation' => '/\bCoretsia\\\\Platform\\\\/',
            'forbidden-container-builder' => '/\b(?:Coretsia\\\\Foundation\\\\Container\\\\ContainerBuilder|ContainerBuilder)\b/',
            'forbidden-contracts-cli-boundary' => '/\bCoretsia\\\\Contracts\\\\Cli\\\\/',
            'forbidden-psr-http-transport' => '/\bPsr\\\\Http\\\\/',
            'forbidden-symfony-console-transport' => '/\bSymfony\\\\Component\\\\Console\\\\/',
            'forbidden-filesystem-type' =>
                '/\b(?:FilesystemIterator|DirectoryIterator|RecursiveDirectoryIterator|' .
                'RecursiveIteratorIterator|SplFileInfo|SplFileObject)\b/',
            'forbidden-filesystem-function' =>
                '/\b(?:fopen|fclose|fread|fwrite|file_get_contents|file_put_contents|' .
                'file_exists|filesize|is_file|is_dir|is_readable|is_writable|realpath|' .
                'glob|scandir|readfile|unlink|rename|copy|mkdir|rmdir|opendir|readdir|' .
                'closedir|touch|chmod|chown|chgrp|symlink|readlink|tempnam)\s*\(/',
            'forbidden-direct-include' => '/\b(?:require|require_once|include|include_once)\b/',
        ];
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $files[] = \str_replace('\\', '/', $fileInfo->getPathname());
        }

        \sort($files, \SORT_STRING);

        return $files;
    }

    private static function phpWithoutComments(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                $out .= ' ';

                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function contractsRoot(): string
    {
        $root = \realpath(__DIR__ . '/../..');

        self::assertIsString($root);

        return \str_replace('\\', '/', $root);
    }

    private static function relativePath(string $root, string $file): string
    {
        $root = \rtrim(\str_replace('\\', '/', $root), '/');
        $file = \str_replace('\\', '/', $file);

        if (\str_starts_with($file, $root . '/')) {
            return \substr($file, \strlen($root) + 1);
        }

        return \basename($file);
    }

    private static function assertNamedType(
        ?\ReflectionType $type,
        string $expectedName,
        bool $allowsNull,
    ): void {
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }
}
