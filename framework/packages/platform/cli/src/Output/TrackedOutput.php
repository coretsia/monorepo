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

namespace Coretsia\Platform\Cli\Output;

use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * @internal
 * Lightweight decorator that tracks whether error(code, message) was emitted.
 *
 * Purpose:
 * - enable Application-level enforcement: if a command fails (exit != 0)
 *   but never emitted an error-record => Application prints a deterministic fallback error-record.
 */
final class TrackedOutput implements OutputInterface
{
    private readonly OutputInterface $inner;

    private bool $errorEmitted = false;

    public function __construct(OutputInterface $inner)
    {
        $this->inner = $inner;
    }

    public function text(string $text): void
    {
        $this->inner->text($text);
    }

    public function json(array $payload): void
    {
        $this->inner->json($payload);
    }

    public function error(string $code, string $message): void
    {
        $this->errorEmitted = true;
        $this->inner->error($code, $message);
    }

    public function errorEmitted(): bool
    {
        return $this->errorEmitted;
    }
}
