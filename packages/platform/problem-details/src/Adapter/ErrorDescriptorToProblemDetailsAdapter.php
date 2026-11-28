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
 * The public Problem Details model consumes only the optional HTTP status.
 * The Allow header is derived separately from validated 405 method metadata.
 * Error messages, codes, extensions, and severity never reach the public model.
 */
final class ErrorDescriptorToProblemDetailsAdapter
{
    /** @var list<non-empty-string> */
    private const array ALLOWED_METHODS = [
        'GET',
        'HEAD',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
        'TRACE',
        'CONNECT',
    ];

    public function map(ErrorDescriptor $descriptor): ProblemDetails
    {
        $status = $descriptor->httpStatus();

        if ($status === null || $status < 400 || $status > 599) {
            $status = 500;
        }

        return new ProblemDetails($status);
    }

    /**
     * Derives the Allow header only for a 405 descriptor.
     *
     * @throws \InvalidArgumentException
     */
    public function allowHeader(ErrorDescriptor $descriptor): ?string
    {
        if ($descriptor->httpStatus() !== 405) {
            return null;
        }

        $methods = $descriptor->extensions()['allowed_methods'] ?? null;
        if (!\is_array($methods) || !\array_is_list($methods) || $methods === [] || \count($methods) > 9) {
            throw new \InvalidArgumentException('problem-details-allow-invalid');
        }

        $previous = null;
        foreach ($methods as $method) {
            if (!\is_string($method) || !\in_array($method, self::ALLOWED_METHODS, true)) {
                throw new \InvalidArgumentException('problem-details-allow-invalid');
            }

            if ($previous !== null && \strcmp($previous, $method) >= 0) {
                throw new \InvalidArgumentException('problem-details-allow-invalid');
            }

            $previous = $method;
        }

        return \implode(', ', $methods);
    }
}
