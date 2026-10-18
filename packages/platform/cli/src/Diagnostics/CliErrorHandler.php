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

namespace Coretsia\Platform\Cli\Diagnostics;

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Throwable;

/**
 * Format-neutral allowlisted exception-to-error mapping.
 *
 * @internal
 */
final class CliErrorHandler implements ErrorHandlerInterface
{
    public function handle(Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor
    {
        // Context metadata and arbitrary Throwable details are not rendered.
        return match (true) {
            $throwable instanceof CliInputInvalidException,
            $throwable instanceof CliConfigInvalidException,
            $throwable instanceof InvalidCommandTagMetaException,
            $throwable instanceof CliCommandFailedException,
            $throwable instanceof KernelOpsFailedException => new ErrorDescriptor(
                $throwable->errorCode(),
                $throwable->reason(),
            ),
            default => new ErrorDescriptor(
                'CORETSIA_CLI_INTERNAL_ERROR',
                'Internal CLI error.',
            ),
        };
    }
}
