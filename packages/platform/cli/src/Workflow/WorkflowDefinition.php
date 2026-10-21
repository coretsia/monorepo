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
 * Immutable, canonically named workflow with its declared step order intact.
 *
 * @internal
 */
final readonly class WorkflowDefinition
{
    /** @param non-empty-list<WorkflowStep> $steps */
    public function __construct(
        private string $name,
        private string $summary,
        private array $steps,
    ) {
        if ($steps === [] || !\array_is_list($steps)) {
            throw new \InvalidArgumentException('cli-workflow-steps-invalid');
        }

        foreach ($steps as $step) {
            if (!$step instanceof WorkflowStep) {
                throw new \InvalidArgumentException('cli-workflow-steps-invalid');
            }
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    /** @return non-empty-list<WorkflowStep> */
    public function steps(): array
    {
        return $this->steps;
    }
}
