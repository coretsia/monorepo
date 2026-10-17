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
use Coretsia\Platform\Cli\Catalog\CommandCatalog;

/**
 * Lists visible catalog descriptors while preserving canonical command order.
 *
 * @internal
 */
final readonly class ListCommand implements CommandInterface
{
    public const string NAME = 'list';
    public const string SUMMARY = 'List available commands.';
    public const string GROUP = 'core';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct(private CommandCatalog $catalog)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $groups = [];

        foreach ($this->catalog->descriptors() as $descriptor) {
            if ($descriptor->hidden()) {
                continue;
            }

            $groups[$descriptor->group()][] = [
                'name' => $descriptor->name(),
                'summary' => $descriptor->summary(),
            ];
        }

        $output->json(['groups' => $groups]);

        return 0;
    }
}
