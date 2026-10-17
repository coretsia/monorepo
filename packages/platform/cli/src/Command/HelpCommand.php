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
 * Descriptor-driven help without eager command service resolution.
 *
 * @internal
 */
final readonly class HelpCommand implements CommandInterface
{
    public const string NAME = 'help';
    public const string SUMMARY = 'Show general or command-specific help.';
    public const string GROUP = 'core';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [
        [
            'name' => 'command',
            'summary' => 'Command name.',
            'required' => false,
            'variadic' => false,
        ],
    ];
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
        $arguments = $input->arguments();

        if ($arguments !== []) {
            $descriptor = $this->catalog->require($arguments[0]);
            $help = [
                'arguments' => $descriptor->arguments(),
                'group' => $descriptor->group(),
                'name' => $descriptor->name(),
                'options' => $descriptor->options(),
                'summary' => $descriptor->summary(),
            ];

            if ($descriptor->name() === ConfigHashCommand::NAME) {
                $help['note'] = 'Calculates the expected generation ID without writing artifacts or inspecting current.';
            }

            $output->json($help);

            return 0;
        }

        $commands = [];
        foreach ($this->catalog->descriptors() as $descriptor) {
            if ($descriptor->hidden()) {
                continue;
            }

            $commands[] = [
                'group' => $descriptor->group(),
                'name' => $descriptor->name(),
                'summary' => $descriptor->summary(),
            ];
        }

        $output->json(['commands' => $commands]);

        return 0;
    }
}
