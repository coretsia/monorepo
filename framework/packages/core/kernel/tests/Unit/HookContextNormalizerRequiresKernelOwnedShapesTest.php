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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Runtime\Hook\HookContextNormalizer;
use Coretsia\Kernel\Runtime\UnitOfWorkContext;
use Coretsia\Kernel\Runtime\UnitOfWorkResult;
use PHPUnit\Framework\TestCase;

final class HookContextNormalizerRequiresKernelOwnedShapesTest extends TestCase
{
    public function testNormalizeContextAcceptsOnlyUnitOfWorkContext(): void
    {
        $parameter = new \ReflectionMethod(
            HookContextNormalizer::class,
            'normalizeContext',
        )->getParameters()[0];

        $type = $parameter->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertFalse($type->allowsNull());
        self::assertSame(UnitOfWorkContext::class, $type->getName());
    }

    public function testNormalizeResultAcceptsOnlyUnitOfWorkResult(): void
    {
        $parameter = new \ReflectionMethod(
            HookContextNormalizer::class,
            'normalizeResult',
        )->getParameters()[0];

        $type = $parameter->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertFalse($type->allowsNull());
        self::assertSame(UnitOfWorkResult::class, $type->getName());
    }
}
