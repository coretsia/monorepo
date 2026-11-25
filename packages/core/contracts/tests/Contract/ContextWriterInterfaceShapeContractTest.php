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

use Coretsia\Contracts\Context\ContextWriterInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class ContextWriterInterfaceShapeContractTest extends TestCase
{
    public function testContextWriterInterfaceExposesOnlyCanonicalMutationMethods(): void
    {
        $reflection = new ReflectionClass(ContextWriterInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertSame('Coretsia\Contracts\Context', $reflection->getNamespaceName());
        self::assertSame([], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getConstants());
        self::assertSame([], $reflection->getProperties());

        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );
        sort($methods, \SORT_STRING);

        self::assertSame(['remove', 'set'], $methods);
    }

    public function testSetMethodShapeIsStable(): void
    {
        self::assertMutationSignature('set', ['key' => 'string', 'value' => 'mixed']);
    }

    public function testRemoveMethodShapeIsStable(): void
    {
        self::assertMutationSignature('remove', ['key' => 'string']);
    }

    public function testContextWriterInterfaceHasNoImplementationOrTransportDependencies(): void
    {
        $fileName = new ReflectionClass(ContextWriterInterface::class)->getFileName();
        self::assertIsString($fileName);
        $source = file_get_contents($fileName);
        self::assertIsString($source);

        // Only declaration tokens and canonical built-in type names are needed by this port.
        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                continue;
            }

            [$id, $value] = $token;
            if (in_array($id, [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }

            self::assertContains($id, [
                \T_OPEN_TAG,
                \T_DECLARE,
                \T_LNUMBER,
                \T_NAMESPACE,
                \T_NAME_QUALIFIED,
                \T_INTERFACE,
                \T_STRING,
                \T_PUBLIC,
                \T_FUNCTION,
                \T_VARIABLE,
            ], token_name($id));

            if ($id === \T_NAME_QUALIFIED) {
                self::assertSame('Coretsia\Contracts\Context', $value);
            }
            if ($id === \T_STRING) {
                self::assertContains($value, [
                    'strict_types',
                    'ContextWriterInterface',
                    'set',
                    'remove',
                    'string',
                    'mixed',
                    'void',
                ]);
            }
        }
    }

    /**
     * @param array<string, string> $expectedParameters
     */
    private static function assertMutationSignature(string $name, array $expectedParameters): void
    {
        $method = new ReflectionMethod(ContextWriterInterface::class, $name);

        self::assertTrue($method->isPublic());
        self::assertTrue($method->isAbstract());
        self::assertFalse($method->isStatic());
        self::assertFalse($method->returnsReference());
        self::assertSame(count($expectedParameters), $method->getNumberOfParameters());
        self::assertSame(count($expectedParameters), $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();
        self::assertSame(
            array_keys($expectedParameters),
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                $parameters,
            ),
        );
        foreach ($parameters as $parameter) {
            self::assertFalse($parameter->isOptional());
            self::assertFalse($parameter->isDefaultValueAvailable());
            self::assertFalse($parameter->isVariadic());
            self::assertFalse($parameter->isPassedByReference());

            $expectedType = $expectedParameters[$parameter->getName()];
            $type = $parameter->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type);
            self::assertTrue($type->isBuiltin());
            self::assertSame($expectedType, $type->getName());
            self::assertSame($expectedType === 'mixed', $type->allowsNull());
        }

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertTrue($returnType->isBuiltin());
        self::assertSame('void', $returnType->getName());
        self::assertFalse($returnType->allowsNull());
    }
}
