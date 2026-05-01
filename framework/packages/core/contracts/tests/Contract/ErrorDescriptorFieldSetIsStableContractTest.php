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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ErrorDescriptorFieldSetIsStableContractTest extends TestCase
{
    public function test_error_descriptor_declared_property_field_set_is_stable(): void
    {
        $reflection = new ReflectionClass(ErrorDescriptor::class);

        $properties = $reflection->getProperties();

        $propertyNames = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            $properties,
        );

        sort($propertyNames, \SORT_STRING);

        self::assertSame(
            [
                'code',
                'extensions',
                'httpStatus',
                'message',
                'severity',
            ],
            $propertyNames,
        );

        foreach ($properties as $property) {
            self::assertTrue(
                $property->isPrivate(),
                sprintf('ErrorDescriptor property "%s" must remain private.', $property->getName()),
            );
        }
    }

    public function test_error_descriptor_public_array_field_set_and_order_are_stable(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Critical,
            httpStatus: 503,
            extensions: [
                'component' => 'database',
            ],
        );

        self::assertSame(
            [
                'code',
                'extensions',
                'httpStatus',
                'message',
                'severity',
            ],
            array_keys($descriptor->toArray()),
        );
    }

    public function test_error_descriptor_does_not_expose_throwable_or_transport_fields(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Error,
        );

        $fieldNames = array_keys($descriptor->toArray());

        self::assertNotContains('throwable', $fieldNames);
        self::assertNotContains('exception', $fieldNames);
        self::assertNotContains('trace', $fieldNames);
        self::assertNotContains('request', $fieldNames);
        self::assertNotContains('response', $fieldNames);
        self::assertNotContains('headers', $fieldNames);
        self::assertNotContains('cookies', $fieldNames);
        self::assertNotContains('payload', $fieldNames);
    }
}
