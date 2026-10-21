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

namespace Coretsia\Platform\Cli\Command;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;

/**
 * Lists validated workflow names and summaries without exposing step inputs.
 *
 * @internal
 */
final readonly class WorkflowListCommand implements CommandInterface
{
    public const string NAME = 'workflow:list';
    public const string SUMMARY = 'List configured CLI workflows.';
    public const string GROUP = 'workflow';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct(private WorkflowCatalog $catalog)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $workflows = [];

        foreach ($this->catalog->definitions() as $definition) {
            $workflows[] = [
                'name' => $definition->name(),
                'summary' => $definition->summary(),
            ];
        }

        $output->json(['workflows' => $workflows]);

        return 0;
    }
}
