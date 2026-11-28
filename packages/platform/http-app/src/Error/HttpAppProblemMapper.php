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

namespace Coretsia\Platform\HttpApp\Error;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Platform\HttpApp\Exception\HttpMethodNotAllowedException;
use Coretsia\Platform\HttpApp\Exception\HttpNotFoundException;
use Throwable;

/**
 * Format-neutral error descriptors for HttpApp-owned routing misses.
 *
 * Error normalization and reporting remain owned by the canonical errors
 * module. HTTP rendering and Allow header construction belong to the outer
 * Problem Details boundary.
 *
 * @internal
 */
final class HttpAppProblemMapper implements ExceptionMapperInterface
{
    public function map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
    {
        if ($throwable instanceof HttpNotFoundException) {
            $status = 404;
            $extensions = [];
        } elseif ($throwable instanceof HttpMethodNotAllowedException) {
            $status = 405;
            $extensions = ['allowed_methods' => $throwable->allowedMethods()];
        } else {
            return null;
        }

        return new ErrorDescriptor(
            code: $throwable->errorCode(),
            message: $throwable->reason(),
            severity: ErrorSeverity::Error,
            httpStatus: $status,
            extensions: $extensions,
        );
    }
}
