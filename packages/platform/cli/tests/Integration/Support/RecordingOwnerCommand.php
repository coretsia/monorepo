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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;

final class RecordingOwnerCommand implements CommandInterface
{
    public const string NAME = 'owner:probe';
    public const string SUMMARY = 'Record owner-package input without changing it.';
    public const string GROUP = 'owner';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [
        [
            'name' => 'subject',
            'summary' => 'Optional subject.',
            'required' => false,
            'variadic' => false,
        ],
    ];
    public const array OPTIONS = [
        ['name' => 'mode', 'summary' => 'Owner mode.', 'value' => 'required', 'repeatable' => false],
        ['name' => 'preset', 'summary' => 'Owner preset.', 'value' => 'required', 'repeatable' => false],
        ['name' => 'item', 'summary' => 'Ordered items.', 'value' => 'required', 'repeatable' => true],
    ];

    public static int $constructed = 0;
    public static int $executed = 0;
    /** @var array{tokens:list<string>,arguments:list<string>,options:array<string,mixed>}|null */
    public static ?array $received = null;

    public function __construct()
    {
        ++self::$constructed;
    }

    public static function reset(): void
    {
        self::$constructed = 0;
        self::$executed = 0;
        self::$received = null;
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        ++self::$executed;
        self::$received = [
            'tokens' => $input->tokens(),
            'arguments' => $input->arguments(),
            'options' => $input->options(),
        ];
        $output->json(['handled' => true, 'items' => $input->option('item') ?? []]);
        return 0;
    }
}
