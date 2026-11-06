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

namespace Coretsia\Platform\ProblemDetails\Adapter;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Platform\ProblemDetails\ProblemDetails;

/**
 * Stateless adaptation of a normalized error descriptor to safe HTTP output.
 *
 * The optional HTTP status is the only descriptor field consumed. Error
 * messages, codes, extensions, and severity never reach the public model.
 */
final class ErrorDescriptorToProblemDetailsAdapter
{
    public function map(ErrorDescriptor $descriptor): ProblemDetails
    {
        $status = $descriptor->httpStatus();

        if ($status === null || $status < 400 || $status > 599) {
            $status = 500;
        }

        return new ProblemDetails($status);
    }
}
