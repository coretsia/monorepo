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

namespace Coretsia\Dto\Attribute\Tests\Contract;

use Attribute;
use Coretsia\Dto\Attribute\Dto;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AttributeExistsTest extends TestCase
{
    public function testDtoAttributeExists(): void
    {
        self::assertTrue(class_exists(Dto::class));
    }

    public function testDtoAttributeTargetsClassesOnly(): void
    {
        $reflection = new ReflectionClass(Dto::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();

        self::assertSame(Attribute::TARGET_CLASS, $attribute->flags);
    }
}
