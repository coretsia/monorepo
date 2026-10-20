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

namespace Coretsia\Platform\Cli\Workflow;

/**
 * Immutable result of one actually executed workflow step.
 *
 * Records are the unchanged normalized representation from the finalized
 * child CommandOutputBatch. No argv, options, service ids, or runtime context
 * are retained by workflow infrastructure.
 *
 * @internal
 */
final readonly class WorkflowStepResult
{
    /** @param list<array<string, mixed>> $records */
    public function __construct(
        private int $index,
        private string $command,
        private int $exitCode,
        private array $records,
    ) {
        if ($index < 0 || $exitCode < 0 || $exitCode > 255 || !\array_is_list($records)) {
            throw new \InvalidArgumentException('cli-workflow-step-result-invalid');
        }
    }

    public function index(): int
    {
        return $this->index;
    }

    public function command(): string
    {
        return $this->command;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    /** @return list<array<string, mixed>> */
    public function records(): array
    {
        return $this->records;
    }
}
