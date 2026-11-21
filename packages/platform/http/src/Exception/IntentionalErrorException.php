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

namespace Coretsia\Platform\Http\Exception;

/**
 * Deterministic intentional system endpoint failure.
 *
 * The public message contains only the fixed package error code and a stable
 * reason token. No caller-controlled message, request data, or previous
 * Throwable is accepted.
 */
final class IntentionalErrorException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_HTTP_INTENTIONAL_ERROR';

    public const string REASON_INTENTIONAL_ERROR = 'http-intentional-error';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_INTENTIONAL_ERROR);
    }

    public static function intentional(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_INTENTIONAL_ERROR;
    }
}
