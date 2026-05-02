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

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Module\ModuleManifest;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class ManifestReaderInterfaceShapeContractTest extends TestCase
{
    public function test_manifest_reader_interface_exposes_read_only(): void
    {
        $interface = new ReflectionClass(ManifestReaderInterface::class);

        self::assertTrue($interface->isInterface());

        self::assertSame(
            ['read'],
            array_map(
                static fn (\ReflectionMethod $method): string => $method->getName(),
                $interface->getMethods(),
            ),
        );
    }

    public function test_read_returns_module_manifest_not_descriptor_list(): void
    {
        $method = new \ReflectionMethod(ManifestReaderInterface::class, 'read');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(0, $method->getNumberOfParameters());

        $returnType = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame(ModuleManifest::class, $returnType->getName());
        self::assertFalse($returnType->allowsNull());
    }
}
