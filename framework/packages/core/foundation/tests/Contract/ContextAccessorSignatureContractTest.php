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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Foundation\Context\ContextStore;
use PHPUnit\Framework\TestCase;

final class ContextAccessorSignatureContractTest extends TestCase
{
    public function testContextStoreImplementsContextAccessorInterface(): void
    {
        self::assertContains(
            ContextAccessorInterface::class,
            \class_implements(ContextStore::class),
        );
    }

    public function testContextStoreGetSignatureIsStable(): void
    {
        $method = new \ReflectionMethod(ContextStore::class, 'get');

        self::assertTrue($method->isPublic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('key', $parameters[0]->getName());
        self::assertFalse($parameters[0]->isOptional());
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        $parameterType = $parameters[0]->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        self::assertTrue($parameterType->isBuiltin());
        self::assertSame('string', $parameterType->getName());
        self::assertFalse($parameterType->allowsNull());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertTrue($returnType->isBuiltin());
        self::assertSame('mixed', $returnType->getName());
    }

    public function testContextAccessorInterfaceGetSignatureIsStable(): void
    {
        $method = new \ReflectionMethod(ContextAccessorInterface::class, 'get');

        self::assertTrue($method->isPublic());
        self::assertSame(1, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('key', $parameters[0]->getName());
        self::assertFalse($parameters[0]->isOptional());
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        $parameterType = $parameters[0]->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $parameterType);
        self::assertTrue($parameterType->isBuiltin());
        self::assertSame('string', $parameterType->getName());
        self::assertFalse($parameterType->allowsNull());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertTrue($returnType->isBuiltin());
        self::assertSame('mixed', $returnType->getName());
    }
}
