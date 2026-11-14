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
 * Deterministic HTTP trusted-host rejection.
 *
 * The public message contains only the fixed package error code and a stable
 * reason token. It MUST NOT contain rejected host values,
 * authority values, forwarded headers, private request metadata, and
 * arbitrary previous Throwables.
 *
 * No caller-controlled message or previous Throwable is accepted.
 */
final class TrustedHostRejectedException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_HTTP_TRUSTED_HOST_REJECTED';

    public const string REASON_TRUSTED_HOST_REJECTED = 'http-trusted-host-rejected';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_TRUSTED_HOST_REJECTED);
    }

    public static function rejected(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_TRUSTED_HOST_REJECTED;
    }
}
