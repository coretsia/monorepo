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

namespace Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

/**
 * External owner command for generic tag discovery and option-shape tests.
 */
final readonly class ExternalModeCommand implements CommandInterface
{
    public const string NAME = 'external:mode';
    public const string SUMMARY = 'Exercise external owner-domain options.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [
        [
            'name' => 'mode',
            'summary' => 'External owner-domain mode.',
            'value' => 'required',
            'repeatable' => false,
        ],
        [
            'name' => 'item',
            'summary' => 'Ordered external owner item.',
            'value' => 'required',
            'repeatable' => true,
        ],
    ];

    public function __construct(private ExternalOwnerService $ownerService)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $mode = $input->option('mode');
        $items = $input->option('item');

        if ($mode !== null && !\is_string($mode)) {
            return 1;
        }

        if ($items === null) {
            $items = [];
        }

        if (!\is_array($items) || !\array_is_list($items)) {
            return 1;
        }

        foreach ($items as $item) {
            if (!\is_string($item)) {
                return 1;
            }
        }

        $this->ownerService->record($mode, $items);
        $output->json(['handled' => true]);

        return 0;
    }
}
