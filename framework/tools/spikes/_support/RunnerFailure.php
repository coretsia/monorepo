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

namespace Coretsia\Tools\Spikes\_support;

/**
 * @internal
 *
 * Deterministic runner failure carrier:
 * - code is a deterministic string (from ErrorCodes registry)
 * - reasons are stable short tokens (no paths, no env, no captured output)
 */
final class RunnerFailure extends \RuntimeException
{
    private string $errorCode;

    /**
     * @var list<string>
     */
    private array $reasons;

    /**
     * @param list<string> $reasons
     */
    private function __construct(string $errorCode, array $reasons)
    {
        $this->errorCode = $errorCode;
        $this->reasons = $reasons;

        parent::__construct('determinism-runner-failed');
    }

    /**
     * @param list<string> $reasons
     */
    public static function with(string $errorCode, array $reasons): self
    {
        return new self($errorCode, $reasons);
    }

    public function code(): string
    {
        return $this->errorCode;
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
