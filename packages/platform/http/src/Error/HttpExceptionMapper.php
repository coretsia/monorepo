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

namespace Coretsia\Platform\Http\Error;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\Exception\BodyTooLargeException;
use Coretsia\Platform\Http\Exception\TrustedHostRejectedException;
use Throwable;

/**
 * Format-neutral error descriptors for the three HTTP-owned failures.
 *
 * The canonical errors module owns mapper discovery and resolution through
 * ReservedTags::ERROR_MAPPER. HTTP's declarative provider contributes this
 * mapper at priority 100 with empty metadata. HTTP rendering remains owned
 * exclusively by an explicitly selected Problem Details boundary.
 *
 * @internal
 */
final class HttpExceptionMapper implements ExceptionMapperInterface
{
    public function map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
    {
        if ($throwable instanceof BadJsonException) {
            $status = 400;
        } elseif ($throwable instanceof BodyTooLargeException) {
            $status = 413;
        } elseif ($throwable instanceof TrustedHostRejectedException) {
            $status = 400;
        } else {
            return null;
        }

        return new ErrorDescriptor(
            code: $throwable->errorCode(),
            message: $throwable->reason(),
            severity: ErrorSeverity::Error,
            httpStatus: $status,
            extensions: [],
        );
    }
}
