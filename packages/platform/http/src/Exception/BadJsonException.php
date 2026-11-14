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
 * Deterministic HTTP JSON request validation failure.
 *
 * The public message contains only the fixed package error code and a stable
 * reason token. It MUST NOT contain JSON parser messages,
 * input bytes, request payload fragments, and arbitrary previous Throwables.
 *
 * No caller-controlled message or previous Throwable is accepted.
 */
final class BadJsonException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_HTTP_BAD_JSON';

    public const string REASON_BAD_JSON = 'http-bad-json';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_BAD_JSON);
    }

    public static function invalid(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_BAD_JSON;
    }
}
