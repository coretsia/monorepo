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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Foundation\Runtime\Reset\ResetErrorCodes;
use Coretsia\Foundation\Runtime\Reset\ResetException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResetExceptionRuntimeShapeTest extends TestCase
{
    /**
     * @return iterable<string,array{0:string,1:string,2:string}>
     */
    public static function resetExceptionFactoryProvider(): iterable
    {
        yield 'meta invalid' => [
            'metaInvalid',
            ResetErrorCodes::CORETSIA_RESET_META_INVALID,
            'reset-meta-invalid',
        ];

        yield 'service not resettable' => [
            'serviceNotResettable',
            ResetErrorCodes::CORETSIA_RESET_SERVICE_NOT_RESETTABLE,
            'reset-not-resettable',
        ];

        yield 'service failed' => [
            'serviceFailed',
            ResetErrorCodes::CORETSIA_RESET_SERVICE_FAILED,
            'reset-service-failed',
        ];

        yield 'observability failed' => [
            'observabilityFailed',
            ResetErrorCodes::CORETSIA_RESET_OBSERVABILITY_FAILED,
            'reset-observability-failed',
        ];
    }

    #[DataProvider('resetExceptionFactoryProvider')]
    public function testStaticConstructorsExposeStableRuntimeShape(
        string $factory,
        string $expectedCode,
        string $expectedReason,
    ): void {
        $previous = new \RuntimeException(
            'unsafe previous payload Authorization Bearer token Cookie session_id raw SQL SELECT * FROM users /tmp/coretsia-secret',
        );

        $exception = self::exceptionFromFactory($factory, $previous);

        self::assertSame($expectedCode, $exception->code());
        self::assertSame($exception->code(), $exception->errorCode());
        self::assertSame($expectedReason, $exception->reason());
        self::assertSame($expectedReason, $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());

        self::assertSafeReasonOnlyMessage($exception, $expectedReason);
    }

    #[DataProvider('resetExceptionFactoryProvider')]
    public function testWithoutPreviousPreservesStableShapeAndStripsPreviousThrowable(
        string $factory,
        string $expectedCode,
        string $expectedReason,
    ): void {
        $previous = new \RuntimeException(
            'unsafe previous payload Authorization Bearer token Cookie session_id raw SQL SELECT * FROM users /tmp/coretsia-secret',
        );

        $exception = self::exceptionFromFactory($factory, $previous);
        $withoutPrevious = $exception->withoutPrevious();

        self::assertNotSame($exception, $withoutPrevious);

        self::assertSame($expectedCode, $withoutPrevious->code());
        self::assertSame($exception->code(), $withoutPrevious->code());

        self::assertSame($withoutPrevious->code(), $withoutPrevious->errorCode());
        self::assertSame($exception->errorCode(), $withoutPrevious->errorCode());

        self::assertSame($expectedReason, $withoutPrevious->reason());
        self::assertSame($exception->reason(), $withoutPrevious->reason());

        self::assertSame($expectedReason, $withoutPrevious->getMessage());
        self::assertSame($exception->getMessage(), $withoutPrevious->getMessage());

        self::assertSame(0, $withoutPrevious->getCode());
        self::assertNull($withoutPrevious->getPrevious());

        self::assertSafeReasonOnlyMessage($withoutPrevious, $expectedReason);
    }

    #[DataProvider('resetExceptionFactoryProvider')]
    public function testExceptionMessagesRemainStableSafeReasonTokensOnly(
        string $factory,
        string $expectedCode,
        string $expectedReason,
    ): void {
        $exception = self::exceptionFromFactory(
            $factory,
            new \RuntimeException(
                'service.internal.id Authorization Cookie token password raw SQL SELECT * FROM users /home/user/project/.env',
            ),
        );

        self::assertSame($expectedCode, $exception->code());
        self::assertSame($expectedReason, $exception->getMessage());
        self::assertSafeReasonOnlyMessage($exception, $expectedReason);
    }

    private static function exceptionFromFactory(
        string $factory,
        ?\Throwable $previous = null,
    ): ResetException {
        return match ($factory) {
            'metaInvalid' => ResetException::metaInvalid($previous),
            'serviceNotResettable' => ResetException::serviceNotResettable($previous),
            'serviceFailed' => ResetException::serviceFailed($previous),
            'observabilityFailed' => ResetException::observabilityFailed($previous),
            default => throw new \InvalidArgumentException('unknown-reset-exception-factory'),
        };
    }

    private static function assertSafeReasonOnlyMessage(
        ResetException $exception,
        string $expectedReason,
    ): void {
        self::assertSame($expectedReason, $exception->getMessage());

        foreach (self::unsafeDiagnosticsNeedles() as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $exception->getMessage(),
                'ResetException messages must remain stable safe reason tokens only.',
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function unsafeDiagnosticsNeedles(): array
    {
        return [
            'service.internal.id',
            'unsafe previous payload',
            'Authorization',
            'Bearer',
            'Cookie',
            'session_id',
            'token',
            'password',
            'raw SQL',
            'SELECT',
            'users',
            '/tmp/coretsia-secret',
            '/home/user/project/.env',
            '.env',
        ];
    }
}
