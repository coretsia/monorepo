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

namespace Coretsia\Platform\Cli\Exception;

/**
 * Base deterministic CLI exception.
 *
 * Note:
 * - We keep the built-in \Throwable::getCode() as `0` (int), because PHP reserves it for ints.
 * - The deterministic string code is exposed via code().
 */
abstract class CliException extends \RuntimeException implements CliExceptionInterface
{
    public const string REASON_INVALID_REASON_TOKEN = 'invalid-reason-token';

    private readonly string $cliCode;
    private readonly string $cliReason;

    public function __construct(string $code, string $reason, ?\Throwable $previous = null)
    {
        $this->cliCode = $code;
        $this->cliReason = $reason !== '' ? $reason : self::REASON_INVALID_REASON_TOKEN;

        // Message is safe (reason token only). Application MUST render code()+reason() explicitly.
        parent::__construct($this->cliReason, 0, $previous);
    }

    final public function code(): string
    {
        return $this->cliCode;
    }

    final public function reason(): string
    {
        return $this->cliReason;
    }

    /**
     * Normalize reason tokens deterministically:
     * - empty or not-allowlisted => REASON_INVALID_REASON_TOKEN
     *
     * @param list<string> $allowlist
     */
    final protected static function normalizeReason(string $reason, array $allowlist): string
    {
        if ($reason === '') {
            return self::REASON_INVALID_REASON_TOKEN;
        }

        if ($allowlist === []) {
            return $reason;
        }

        return \in_array($reason, $allowlist, true)
            ? $reason
            : self::REASON_INVALID_REASON_TOKEN;
    }
}
