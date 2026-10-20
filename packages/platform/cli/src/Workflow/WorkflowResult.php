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
 * Immutable completed workflow result, including only executed steps.
 *
 * Child output records remain canonical internally. toArray() changes only
 * the child JSON-record envelope key `payload` to `data`, allowing the
 * existing parent output redactor to inspect the original nested values.
 * No normalization, redaction, or output formatting occurs here.
 *
 * @internal
 */
final readonly class WorkflowResult
{
    private string $outcome;

    /** @param list<WorkflowStepResult> $steps */
    public function __construct(
        private string $workflow,
        private int $exitCode,
        private array $steps,
    ) {
        if ($exitCode < 0 || $exitCode > 255 || !\array_is_list($steps) || $steps === []) {
            throw new \InvalidArgumentException('cli-workflow-result-invalid');
        }

        $lastIndex = \count($steps) - 1;

        foreach ($steps as $index => $step) {
            if (
                !$step instanceof WorkflowStepResult
                || $step->index() !== $index
                || ($index !== $lastIndex && $step->exitCode() !== 0)
            ) {
                throw new \InvalidArgumentException('cli-workflow-result-invalid');
            }
        }

        if ($steps[$lastIndex]->exitCode() !== $exitCode) {
            throw new \InvalidArgumentException('cli-workflow-result-invalid');
        }

        $this->outcome = $exitCode === 0 ? 'success' : 'failure';
    }

    public function workflow(): string
    {
        return $this->workflow;
    }

    /** @return 'success'|'failure' */
    public function outcome(): string
    {
        return $this->outcome;
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    /** @return list<WorkflowStepResult> */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * Projects normalized child records for the parent CommandOutputBuffer::json()
     * boundary, which retains ownership of all output budgets and redaction.
     *
     * @return array{
     *     exit_code: int,
     *     outcome: 'success'|'failure',
     *     steps: list<array{command: string, exit_code: int, index: int, records: list<array<string, mixed>>}>,
     *     workflow: string,
     * }
     */
    public function toArray(): array
    {
        $steps = [];

        foreach ($this->steps as $step) {
            $records = [];

            foreach ($step->records() as $record) {
                $records[] = match ($record['type']) {
                    'text' => [
                        'text' => $record['text'],
                        'type' => 'text',
                    ],
                    'json' => [
                        'data' => $record['payload'],
                        'type' => 'json',
                    ],
                    'error' => [
                        'code' => $record['code'],
                        'message' => $record['message'],
                        'type' => 'error',
                    ],
                    default => throw new \LogicException('cli-workflow-child-record-invalid'),
                };
            }

            $steps[] = [
                'command' => $step->command(),
                'exit_code' => $step->exitCode(),
                'index' => $step->index(),
                'records' => $records,
            ];
        }

        return [
            'exit_code' => $this->exitCode,
            'outcome' => $this->outcome,
            'steps' => $steps,
            'workflow' => $this->workflow,
        ];
    }
}
