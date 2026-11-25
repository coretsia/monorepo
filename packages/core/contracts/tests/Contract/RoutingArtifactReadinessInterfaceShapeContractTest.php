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

use Coretsia\Contracts\Routing\RoutingArtifactReadinessInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class RoutingArtifactReadinessInterfaceShapeContractTest extends TestCase
{
    public function testReadinessInterfaceExposesOnlyTransportNeutralReadinessMethod(): void
    {
        $reflection = new ReflectionClass(RoutingArtifactReadinessInterface::class);

        self::assertTrue($reflection->isInterface());
        self::assertSame('Coretsia\Contracts\Routing', $reflection->getNamespaceName());
        self::assertSame([], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getConstants());
        self::assertSame([], $reflection->getProperties());
        self::assertSame(
            ['assertReady'],
            array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $reflection->getMethods(),
            ),
        );

        $method = $reflection->getMethod('assertReady');

        self::assertTrue($method->isPublic());
        self::assertTrue($method->isAbstract());
        self::assertFalse($method->isStatic());
        self::assertFalse($method->returnsReference());
        self::assertSame(0, $method->getNumberOfParameters());
        self::assertSame(0, $method->getNumberOfRequiredParameters());

        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertTrue($returnType->isBuiltin());
        self::assertSame('void', $returnType->getName());
        self::assertFalse($returnType->allowsNull());
    }

    public function testReadinessInterfaceHasNoRuntimeFilesystemOrTransportDependencies(): void
    {
        $fileName = new ReflectionClass(RoutingArtifactReadinessInterface::class)->getFileName();
        self::assertIsString($fileName);
        $source = file_get_contents($fileName);
        self::assertIsString($source);

        // The contract needs no imports, implementation symbols, or executable operations.
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
            ], token_name($id));

            if ($id === \T_NAME_QUALIFIED) {
                self::assertSame('Coretsia\Contracts\Routing', $value);
            }
            if ($id === \T_STRING) {
                self::assertContains($value, [
                    'strict_types',
                    'RoutingArtifactReadinessInterface',
                    'assertReady',
                    'void',
                ]);
            }
        }
    }
}
