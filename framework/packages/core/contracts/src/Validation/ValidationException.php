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

namespace Coretsia\Contracts\Validation;

/**
 * Deterministic contracts exception for failed validation.
 *
 * The canonical validation error code is the stable string code exposed by
 * ValidationException::CODE. It is intentionally separate from PHP's native
 * integer Throwable::getCode() value.
 *
 * This exception carries a failed ValidationResult only. It does not construct,
 * own, or require ErrorDescriptor. Mapping to normalized errors is runtime-owned.
 *
 * The exception message is intentionally generic and MUST NOT contain raw input
 * values, raw request data, raw queue messages, raw SQL, credentials, tokens,
 * private customer data, or absolute local paths.
 */
final class ValidationException extends \RuntimeException
{
    public const string CODE = 'CORETSIA_VALIDATION_FAILED';

    public const string MESSAGE = 'Validation failed.';

    private readonly ValidationResult $result;

    public function __construct(
        ValidationResult $result,
        ?\Throwable $previous = null,
    ) {
        if ($result->isSuccess()) {
            throw new \InvalidArgumentException('Validation exception requires a failed validation result.');
        }

        $this->result = $result;

        parent::__construct(self::MESSAGE, 0, $previous);
    }

    public function errorCode(): string
    {
        return self::CODE;
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}
