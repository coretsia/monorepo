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

namespace Coretsia\Foundation\Logging;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * No-op PSR-3 logger implementation for the Foundation baseline.
 *
 * PSR-3 context is accepted as-is and intentionally ignored without
 * validation, storage, interpolation, or emission.
 */
final class NoopLogger implements LoggerInterface
{
    public function emergency(Stringable|string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        unset($message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        unset($level, $message, $context);
    }
}
