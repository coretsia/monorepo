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
 * Immutable normalized definition of one CLI workflow step.
 *
 * The command service and descriptor remain owned by the final CommandCatalog.
 * Descriptor-dependent input validation is performed only by CommandInputValidator.
 *
 * @internal
 */
final readonly class WorkflowStep
{
    /**
     * @param list<string> $arguments
     * @param array<string, string|true|list<string>> $options
     */
    public function __construct(
        private string $command,
        private array $arguments,
        private array $options,
    ) {
    }

    public function command(): string
    {
        return $this->command;
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return array<string, string|true|list<string>> */
    public function options(): array
    {
        return $this->options;
    }
}
