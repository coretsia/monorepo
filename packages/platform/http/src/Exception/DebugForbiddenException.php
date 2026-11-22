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
 * Deterministic denial of access to an eligible development endpoint.
 *
 * The exception accepts no request data, caller-controlled messages, guard
 * failure details, or previous Throwable.
 */
final class DebugForbiddenException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_HTTP_DEBUG_FORBIDDEN';

    public const string REASON_FORBIDDEN = 'http-debug-forbidden';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_FORBIDDEN);
    }

    public static function forbidden(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_FORBIDDEN;
    }
}
