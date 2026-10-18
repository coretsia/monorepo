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

use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;

/**
 * Immutable CLI output policy constructed from already-validated configuration.
 *
 * The policy stores only canonical presentation settings. It never probes the
 * terminal, selects invocation-local overrides, or controls redaction.
 *
 * @internal
 */
final readonly class CliOutputPolicy
{
    public function __construct(
        private string $formatDefault,
        private string $interactiveFormat,
        private string $nonInteractiveFormat,
        private string $colorDefault,
        private int $tableMaxWidth,
    ) {
        if (
            !\in_array($this->formatDefault, ['adaptive', 'json', 'table', 'plain'], true)
            || !\in_array($this->interactiveFormat, ['json', 'table', 'plain'], true)
            || !\in_array($this->nonInteractiveFormat, ['json', 'table', 'plain'], true)
            || !\in_array($this->colorDefault, ['auto', 'always', 'never'], true)
            || $this->tableMaxWidth < 40
            || $this->tableMaxWidth > 240
        ) {
            throw CliConfigInvalidException::withReason(
                CliConfigInvalidException::REASON_OUTPUT_POLICY_INVALID,
            );
        }
    }

    public function formatDefault(): string
    {
        return $this->formatDefault;
    }

    public function interactiveFormat(): string
    {
        return $this->interactiveFormat;
    }

    public function nonInteractiveFormat(): string
    {
        return $this->nonInteractiveFormat;
    }

    public function colorDefault(): string
    {
        return $this->colorDefault;
    }

    public function tableMaxWidth(): int
    {
        return $this->tableMaxWidth;
    }
}
