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

use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class SensitiveKeyClassifierInterfaceShapeContractTest extends TestCase
{
    public function testContractExposesExactlyOnePublicInstanceMethod(): void
    {
        $reflection = new ReflectionClass(SensitiveKeyClassifierInterface::class);

        self::assertSame('Coretsia\\Contracts\\Security\\SensitiveKeyClassifierInterface', $reflection->getName());
        self::assertTrue($reflection->isInterface());
        self::assertFalse($reflection->isEnum());
        self::assertSame([], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getProperties());
        self::assertSame([], $reflection->getReflectionConstants());
        self::assertSame([], $reflection->getAttributes());

        $methods = $reflection->getMethods();

        self::assertCount(1, $methods);
        self::assertSame('classify', $methods[0]->getName());
        self::assertSame($reflection->getName(), $methods[0]->getDeclaringClass()->getName());
        self::assertTrue($methods[0]->isPublic());
        self::assertTrue($methods[0]->isAbstract());
        self::assertFalse($methods[0]->isStatic());
        self::assertSame([], $methods[0]->getAttributes());
    }

    public function testClassifySignatureUsesOnlyContractsLevelTypes(): void
    {
        $method = new ReflectionMethod(SensitiveKeyClassifierInterface::class, 'classify');

        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameter = $method->getParameters()[0];

        self::assertSame('key', $parameter->getName());
        self::assertFalse($parameter->isVariadic());
        self::assertFalse($parameter->isPassedByReference());
        self::assertFalse($parameter->isDefaultValueAvailable());
        self::assertFalse($parameter->isPromoted());
        self::assertSame([], $parameter->getAttributes());

        $parameterType = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $parameterType);
        self::assertSame('string', $parameterType->getName());
        self::assertTrue($parameterType->isBuiltin());
        self::assertFalse($parameterType->allowsNull());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(RedactionKind::class, $returnType->getName());
        self::assertFalse($returnType->isBuiltin());
        self::assertTrue($returnType->allowsNull());
    }

    public function testContractHasNoImplementationOrRuntimeDependencySurface(): void
    {
        $reflection = new ReflectionClass(SensitiveKeyClassifierInterface::class);
        $file = $reflection->getFileName();

        self::assertIsString($file);

        $source = \file_get_contents($file);

        self::assertIsString($source);

        $declarations = '';

        foreach (\token_get_all($source) as $token) {
            if (\is_array($token)) {
                if (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }

                self::assertFalse($token[0] === \T_USE, 'Contracts port must not import implementation dependencies.');
                $declarations .= $token[1];
                continue;
            }

            $declarations .= $token;
        }

        foreach (
            [
                'Coretsia\\Platform\\',
                'Coretsia\\Kernel\\',
                'Coretsia\\Foundation\\',
                'Coretsia\\Integrations\\',
                'Psr\\',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $declarations);
        }

        self::assertSame([], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getProperties());
        self::assertSame([], $reflection->getReflectionConstants());
    }

    public function testNullClassificationIsDocumentedAsUnclassifiedNotSinkSafe(): void
    {
        $interfaceDoc = new ReflectionClass(SensitiveKeyClassifierInterface::class)->getDocComment();

        self::assertIsString($interfaceDoc);
        self::assertStringContainsString(
            'A null result means that the key is unclassified, NOT that it is safe for',
            $interfaceDoc,
        );
        self::assertStringContainsString('mandatory redaction requirements.', $interfaceDoc);
        self::assertStringContainsString(
            'does not expose mutable policy registries, configuration, runtime',
            $interfaceDoc,
        );

        $methodDoc = new ReflectionMethod(SensitiveKeyClassifierInterface::class, 'classify')->getDocComment();

        self::assertIsString($methodDoc);
        self::assertStringContainsString(
            'Returns null when the key has no matching canonical classification.',
            $methodDoc,
        );
        self::assertStringContainsString(
            'A non-match does not authorize exposing the key or its associated value.',
            $methodDoc,
        );
    }
}
