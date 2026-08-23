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

namespace Coretsia\Kernel\Tests\Fixtures\PreExpansionPackage;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Psr\Log\AbstractLogger;

final class PreExpansionFailingObservability extends AbstractLogger implements TracerPortInterface, MeterPortInterface
{
    private static int $tracerFailures = 0;
    private static int $meterFailures = 0;
    private static int $loggerFailures = 0;

    public static function resetFailures(): void
    {
        self::$tracerFailures = 0;
        self::$meterFailures = 0;
        self::$loggerFailures = 0;
    }

    public static function tracerFailures(): int
    {
        return self::$tracerFailures;
    }

    public static function meterFailures(): int
    {
        return self::$meterFailures;
    }

    public static function loggerFailures(): int
    {
        return self::$loggerFailures;
    }

    public function startSpan(
        string $name,
        array $attributes = [],
    ): SpanInterface {
        ++self::$tracerFailures;

        throw self::failure();
    }

    public function inSpan(
        string $name,
        callable $callback,
        array $attributes = [],
    ): mixed {
        ++self::$tracerFailures;

        throw self::failure();
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }

    public function increment(
        string $name,
        int $delta = 1,
        array $labels = [],
    ): void {
        ++self::$meterFailures;

        throw self::failure();
    }

    public function observe(
        string $name,
        int $value,
        array $labels = [],
    ): void {
        ++self::$meterFailures;

        throw self::failure();
    }

    public function log(
        $level,
        string|\Stringable $message,
        array $context = [],
    ): void {
        ++self::$loggerFailures;

        throw self::failure();
    }

    private static function failure(): \RuntimeException
    {
        return new \RuntimeException('pre-expansion-observability-failure');
    }
}
