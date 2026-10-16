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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use PHPUnit\Framework\TestCase;

final class CommandCatalogDoesNotInstantiateCommandsForListTest extends TestCase
{
    public function testBuildsCompleteDescriptorsWithoutConstructingTaggedCommands(): void
    {
        LazyListProbeCommand::$constructorCalls = 0;
        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, LazyListProbeCommand::class, meta: [
            'name' => LazyListProbeCommand::NAME,
            'summary' => LazyListProbeCommand::SUMMARY,
            'group' => LazyListProbeCommand::GROUP,
            'hidden' => LazyListProbeCommand::HIDDEN,
            'arguments' => LazyListProbeCommand::ARGUMENTS,
            'options' => LazyListProbeCommand::OPTIONS,
        ]);

        $catalog = new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
        $descriptor = $catalog->require(LazyListProbeCommand::NAME);
        self::assertSame([LazyListProbeCommand::NAME], \array_keys($catalog->descriptors()));
        self::assertSame(LazyListProbeCommand::class, $descriptor->serviceId());
        self::assertSame(LazyListProbeCommand::SUMMARY, $descriptor->summary());
        self::assertSame(LazyListProbeCommand::GROUP, $descriptor->group());
        self::assertSame(0, LazyListProbeCommand::$constructorCalls);
        self::assertSame($descriptor, $catalog->require(LazyListProbeCommand::NAME));
    }
}

final class LazyListProbeCommand implements CommandInterface
{
    public static int $constructorCalls = 0;
    public const string NAME = 'external:lazy';
    public const string SUMMARY = 'Lazy descriptor must not construct the command.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function __construct()
    {
        ++self::$constructorCalls;
        throw new \LogicException('lazy-command-must-not-be-constructed');
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}
