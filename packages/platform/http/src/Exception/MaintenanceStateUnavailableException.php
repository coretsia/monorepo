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
 * Deterministic maintenance state-access or state-mutation failure.
 *
 * The public message contains only the fixed package error code and a stable
 * reason token. It MUST NOT contain filesystem paths, operating-system errors,
 * original exception messages, or arbitrary previous Throwables.
 * No caller-controlled message or previous Throwable is accepted.
 */
final class MaintenanceStateUnavailableException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_MAINTENANCE_STATE_UNAVAILABLE';

    public const string REASON_STATE_UNAVAILABLE = 'http-maintenance-state-unavailable';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_STATE_UNAVAILABLE);
    }

    public static function unavailable(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_STATE_UNAVAILABLE;
    }
}
