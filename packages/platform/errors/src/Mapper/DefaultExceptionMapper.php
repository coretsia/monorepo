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

namespace Coretsia\Platform\Errors\Mapper;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Throwable;

/**
 * Fixed safe fallback for unhandled exceptions.
 *
 * This stateless mapper handles every Throwable without inspecting its
 * message, trace, class, location, previous exception, or caller context.
 *
 * The Errors ErrorHandler invokes this mapper only after registered mapper
 * candidates are exhausted or dispatch fails. It MUST NOT be registered
 * under the `error.mapper` discovery tag.
 */
final class DefaultExceptionMapper implements ExceptionMapperInterface
{
    public function map(Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor
    {
        return new ErrorDescriptor(
            code: 'CORETSIA_UNHANDLED_EXCEPTION',
            message: 'Unhandled exception.',
            severity: ErrorSeverity::Error,
            httpStatus: null,
            extensions: [],
        );
    }
}
