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

namespace Coretsia\Platform\Cli\Mapper;

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Exception\WorkflowNotFoundException;
use Throwable;

/**
 * Format-neutral mapping for the CLI-owned exception allowlist.
 *
 * Known CLI and Kernel Ops failures retain their existing public error codes
 * and reason tokens. Arbitrary Throwable messages, previous exceptions, and
 * caller context metadata are never copied into the descriptor.
 *
 * Unsupported exceptions return null so the canonical mapper registry may
 * continue in its existing tag-defined order. This mapper does not own
 * discovery priority, final fallback selection, or transport rendering.
 *
 * @internal
 */
final class CliExceptionMapper implements ExceptionMapperInterface
{
    public function map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
    {
        if (
            !$throwable instanceof CliInputInvalidException
            && !$throwable instanceof CliConfigInvalidException
            && !$throwable instanceof InvalidCommandTagMetaException
            && !$throwable instanceof CliCommandFailedException
            && !$throwable instanceof WorkflowDefinitionInvalidException
            && !$throwable instanceof WorkflowNotFoundException
            && !$throwable instanceof KernelOpsFailedException
        ) {
            return null;
        }

        return new ErrorDescriptor(
            code: $throwable->errorCode(),
            message: $throwable->reason(),
            severity: ErrorSeverity::Error,
            httpStatus: null,
            extensions: [],
        );
    }
}
