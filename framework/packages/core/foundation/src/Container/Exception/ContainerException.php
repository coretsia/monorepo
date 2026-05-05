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

namespace Coretsia\Foundation\Container\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Deterministic Foundation container failure.
 *
 * Native PHP exception codes are integers, so the Coretsia string error code is
 * exposed separately through `errorCode()`.
 *
 * Messages are expected to be stable machine-readable strings and must not
 * contain secrets, raw config payloads, constructor arguments, environment
 * values, tokens, or absolute local paths.
 */
class ContainerException extends \RuntimeException implements ContainerExceptionInterface
{
    public const string ERROR_CODE = 'CORETSIA_CONTAINER_ERROR';

    public function __construct(
        string $message = 'container-error',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return static::ERROR_CODE;
    }
}
