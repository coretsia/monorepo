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

namespace Coretsia\Platform\HttpApp\Exception;

/**
 * Deterministic application route miss.
 *
 * The message contains only the fixed reason token. No request matching
 * input, handler identifier, caller-controlled message, or previous
 * Throwable is accepted or retained as custom exception state.
 */
final class HttpNotFoundException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_HTTP_404';

    private const string REASON = 'http-app-not-found';

    private function __construct()
    {
        parent::__construct(self::REASON);
    }

    public static function notFound(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON;
    }
}
