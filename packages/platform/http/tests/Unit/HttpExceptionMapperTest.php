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

namespace Coretsia\Platform\Http\Tests\Unit;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\Exception\BodyTooLargeException;
use Coretsia\Platform\Http\Exception\TrustedHostRejectedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpExceptionMapperTest extends TestCase
{
    #[DataProvider('httpExceptions')]
    public function testExactlyThreeHttpOwnedExceptionsMapToFixedSafeDescriptors(
        \Throwable $failure,
        string $code,
        string $reason,
        int $status,
    ): void {
        $mapper = new HttpExceptionMapper();
        self::assertInstanceOf(ExceptionMapperInterface::class, $mapper);

        $descriptor = $mapper->map($failure);
        self::assertInstanceOf(ErrorDescriptor::class, $descriptor);
        self::assertSame([
            'code' => $code,
            'extensions' => [],
            'httpStatus' => $status,
            'message' => $reason,
            'schemaVersion' => ErrorDescriptor::SCHEMA_VERSION,
            'severity' => ErrorSeverity::Error->value,
        ], $descriptor->toArray());
        self::assertSame($code, $failure->errorCode());
        self::assertSame($reason, $failure->reason());
        self::assertNull($failure->getPrevious());
    }

    /** @return iterable<string, array{\Throwable, string, string, int}> */
    public static function httpExceptions(): iterable
    {
        yield 'bad-json' => [
            BadJsonException::invalid(),
            'CORETSIA_HTTP_BAD_JSON',
            'http-bad-json',
            400,
        ];
        yield 'body-too-large' => [
            BodyTooLargeException::tooLarge(),
            'CORETSIA_HTTP_PAYLOAD_TOO_LARGE',
            'http-payload-too-large',
            413,
        ];
        yield 'trusted-host-rejected' => [
            TrustedHostRejectedException::rejected(),
            'CORETSIA_HTTP_TRUSTED_HOST_REJECTED',
            'http-trusted-host-rejected',
            400,
        ];
    }

    public function testUnrelatedThrowablesAlwaysReturnNull(): void
    {
        $mapper = new HttpExceptionMapper();
        $context = new ErrorHandlingContext('http', null, ['marker' => 'SAFE_MARKER']);

        foreach (
            [
                new \RuntimeException('PRIVATE_EXCEPTION_MESSAGE'),
                new \InvalidArgumentException('PRIVATE_BAD_ARGUMENT'),
                new \LogicException('PRIVATE_LOGIC'),
                new \TypeError('PRIVATE_TYPE_ERROR'),
                new \Error('PRIVATE_ENGINE_ERROR'),
            ] as $failure
        ) {
            self::assertNull($mapper->map($failure));
            self::assertNull($mapper->map($failure, $context));
        }
    }

    public function testCallerContextAndThrowableInternalsNeverReachDescriptorExtensions(): void
    {
        $mapper = new HttpExceptionMapper();
        $context = new ErrorHandlingContext('http', null, [
            'marker' => 'PRIVATE_CONTEXT_SENTINEL',
        ]);
        $message = new \ReflectionProperty(\Exception::class, 'message');
        $previous = new \ReflectionProperty(\Exception::class, 'previous');

        foreach (self::httpExceptions() as [$failure, $code, $reason, $status]) {
            $message->setValue($failure, 'PRIVATE_THROWABLE_MESSAGE_SENTINEL');
            $previous->setValue($failure, new \RuntimeException('PRIVATE_PREVIOUS_SENTINEL'));

            $descriptor = $mapper->map($failure, $context);
            self::assertInstanceOf(ErrorDescriptor::class, $descriptor);
            self::assertSame($code, $descriptor->code());
            self::assertSame($reason, $descriptor->message());
            self::assertSame($status, $descriptor->httpStatus());
            self::assertSame([], $descriptor->extensions());

            $serialized = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('PRIVATE_THROWABLE_MESSAGE_SENTINEL', $serialized);
            self::assertStringNotContainsString('PRIVATE_PREVIOUS_SENTINEL', $serialized);
            self::assertStringNotContainsString('PRIVATE_CONTEXT_SENTINEL', $serialized);
            self::assertStringNotContainsString('Stack trace', $serialized);
            self::assertStringNotContainsString(__FILE__, $serialized);
        }
        self::assertSame(['marker' => 'PRIVATE_CONTEXT_SENTINEL'], $context->metadata());
    }

    public function testMapperIntroducesNoRendererReporterOrMutableState(): void
    {
        $reflection = new \ReflectionClass(HttpExceptionMapper::class);
        self::assertTrue($reflection->isFinal());
        self::assertSame([], $reflection->getProperties());
        self::assertNull($reflection->getConstructor());

        $source = \file_get_contents($reflection->getFileName());
        self::assertIsString($source);
        foreach (
            [
                'getMessage(',
                'getPrevious(',
                'getTrace(',
                'getFile(',
                'SensitiveDataRedactor',
                'ErrorHandlerInterface',
                'ResponseFactoryInterface',
                'StreamFactoryInterface',
                'ProblemDetails',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }
}
