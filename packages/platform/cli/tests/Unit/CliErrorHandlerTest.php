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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Platform\Cli\Diagnostics\CliErrorHandler;
use PHPUnit\Framework\TestCase;

final class CliErrorHandlerTest extends TestCase
{
    public function testKernelOpsFailureUsesOnlyStablePublicErrorCodeAndReason(): void
    {
        foreach (
            [
                KernelOpsFailedException::REASON_OPERATION_FAILED,
                KernelOpsFailedException::REASON_HOST_BOOT_FAILED,
            ] as $reason
        ) {
            $throwable = new KernelOpsFailedException($reason);
            $descriptor = new CliErrorHandler()->handle(
                $throwable,
                new ErrorHandlingContext(
                    operation: 'owner:run',
                    correlationId: 'corr-123',
                    metadata: ['detail' => 'PRIVATE_CONTEXT_VALUE'],
                ),
            );

            self::assertSame($throwable->errorCode(), $descriptor->code());
            self::assertSame($throwable->reason(), $descriptor->message());
            self::assertSame([], $descriptor->extensions());
            self::assertNull($descriptor->httpStatus());
            self::assertStringNotContainsString($throwable::class, $descriptor->message());
            self::assertStringNotContainsString(__FILE__, $descriptor->message());
        }
    }

    public function testUnexpectedThrowableDoesNotLeakCauseMessageOrMetadata(): void
    {
        $secret = 'SECRET_TOKEN_DO_NOT_LOG';
        $path = '/private/credentials/config.php';
        $root = new \RuntimeException($secret . ' ' . $path);
        $throwable = new \RuntimeException('Failure at ' . __FILE__, 0, $root);
        $descriptor = new CliErrorHandler()->handle($throwable);

        self::assertSame('CORETSIA_CLI_INTERNAL_ERROR', $descriptor->code());
        self::assertSame('Internal CLI error.', $descriptor->message());
        self::assertSame([], $descriptor->extensions());
        $export = \var_export([
            $descriptor->code(),
            $descriptor->message(),
            $descriptor->extensions(),
            $descriptor->httpStatus(),
        ], true);
        foreach ([$secret, $path, __FILE__, $throwable::class, $root::class, 'getTrace', 'previous'] as $value) {
            self::assertStringNotContainsString($value, $export);
        }
        self::assertNull($descriptor->httpStatus());
    }
}
