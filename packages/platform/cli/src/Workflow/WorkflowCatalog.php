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

use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Exception\WorkflowNotFoundException;

/**
 * Immutable finalized catalog of canonically normalized workflow definitions.
 *
 * WorkflowSchema owns definition normalization and ordering. This catalog
 * validates every command reference against the final CommandCatalog without
 * resolving any command service or inspecting descriptor argument schemas.
 *
 * @internal
 */
final readonly class WorkflowCatalog
{
    private const string WORKFLOW_NAME_PATTERN = '/\A[a-z][a-z0-9-]*\z/D';

    /** @var array<string, WorkflowDefinition> */
    private array $definitions;

    /**
     * @param array<string, WorkflowDefinition> $definitions Canonical WorkflowSchema output
     */
    public function __construct(array $definitions, CommandCatalog $commands)
    {
        foreach ($definitions as $definition) {
            foreach ($definition->steps() as $step) {
                $descriptor = $commands->find($step->command());

                if ($descriptor === null) {
                    throw WorkflowDefinitionInvalidException::withReason(
                        WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN,
                    );
                }

                if ($descriptor->executionKind() !== 'normal') {
                    throw WorkflowDefinitionInvalidException::withReason(
                        WorkflowDefinitionInvalidException::REASON_COMMAND_COMPOSITE,
                    );
                }
            }
        }

        $this->definitions = $definitions;
    }

    /** @return array<string, WorkflowDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    public function require(string $name): WorkflowDefinition
    {
        if (
            $name === ''
            || \strlen($name) > 64
            || \preg_match(self::WORKFLOW_NAME_PATTERN, $name) !== 1
        ) {
            throw WorkflowNotFoundException::notFound();
        }

        return $this->definitions[$name] ?? throw WorkflowNotFoundException::notFound();
    }
}
