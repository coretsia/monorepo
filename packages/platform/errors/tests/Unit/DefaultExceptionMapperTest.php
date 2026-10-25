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

namespace Coretsia\Platform\Errors\Tests\Unit;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use PHPUnit\Framework\TestCase;

final class DefaultExceptionMapperTest extends TestCase
{
    public function testArbitraryThrowableTypesUseTheSameFixedSafeDescriptor(): void
    {
        $mapper = new DefaultExceptionMapper();
        $previous = new \RuntimeException('SYNTHETIC_PREVIOUS_PRIVATE');
        $throwables = [
            new \RuntimeException('SYNTHETIC_RUNTIME_PRIVATE', previous: $previous),
            new \InvalidArgumentException('SYNTHETIC_ARGUMENT_PRIVATE'),
            new \TypeError('SYNTHETIC_TYPE_PRIVATE'),
            new \Error('SYNTHETIC_ENGINE_PRIVATE'),
        ];
        $context = new ErrorHandlingContext(
            operation: 'map',
            metadata: ['note' => 'SYNTHETIC_CONTEXT_PRIVATE'],
        );

        $expected = [
            'code' => 'CORETSIA_UNHANDLED_EXCEPTION',
            'extensions' => [],
            'httpStatus' => null,
            'message' => 'Unhandled exception.',
            'schemaVersion' => ErrorDescriptor::SCHEMA_VERSION,
            'severity' => ErrorSeverity::Error->value,
        ];

        foreach ($throwables as $throwable) {
            $descriptor = $mapper->map($throwable, $context);

            self::assertSame($expected, $descriptor->toArray());
            self::assertSame($expected, $mapper->map($throwable)->toArray());
            self::assertSame($descriptor->toArray(), ErrorDescriptor::fromArray($descriptor->toArray())->toArray());
        }
    }

    public function testFallbackNeverCopiesThrowableMessagesTracesOrPreviousExceptions(): void
    {
        $raw = 'SYNTHETIC_PRIVATE_CREDENTIAL';
        $previous = new \RuntimeException('SYNTHETIC_PREVIOUS_PRIVATE');
        $throwable = new \RuntimeException($raw, previous: $previous);
        $context = new ErrorHandlingContext(metadata: ['note' => 'SYNTHETIC_CONTEXT_PRIVATE']);

        $descriptor = new DefaultExceptionMapper()->map($throwable, $context);
        $export = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);

        foreach ([$raw, $previous->getMessage(), 'SYNTHETIC_CONTEXT_PRIVATE', __FILE__] as $secret) {
            self::assertStringNotContainsString($secret, $export);
        }

        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
        self::assertSame('Unhandled exception.', $descriptor->message());
        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertNull($descriptor->httpStatus());
        self::assertSame([], $descriptor->extensions());
    }
}
