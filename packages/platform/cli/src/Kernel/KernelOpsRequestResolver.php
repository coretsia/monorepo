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

namespace Coretsia\Platform\Cli\Kernel;

use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;

/**
 * Converts structurally validated built-in Kernel command input to an Ops port request.
 *
 * The target is required by Kernel operation commands, but allowed target values
 * remain Kernel-owned. No descriptor, Bootstrap preset, or artifact is inspected.
 *
 * @internal
 */
final class KernelOpsRequestResolver
{
    public function resolve(InputInterface $input): KernelOpsRequest
    {
        $value = $input->option('target');

        if (
            !$input->hasOption('target')
            || !\is_string($value)
            || \preg_match('/\A[A-Za-z][A-Za-z0-9_-]*\z/D', $value) !== 1
        ) {
            throw CliInputInvalidException::withReason(
                CliInputInvalidException::REASON_TARGET_INVALID,
            );
        }

        return new KernelOpsRequest($value);
    }
}
