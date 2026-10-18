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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkerComposedTestCase;

final class WorkerCommandMetadataCompatibilityTest extends WorkerComposedTestCase
{
    public function testComposedWorkerTagsMatchConstantsAndRejectOwnerUndeclaredOptions(): void
    {
        $root = self::workerConsumer();
        try {
            [$plan, $catalog, $registry] = self::composedWorkerHost($root);
            self::assertWorkerTags($catalog, $registry);
            $schema = new CommandTagSchema();
            $validator = new CommandInputValidator();
            $parser = new ArgvInputParser();
            foreach (self::workerCommands() as $command) {
                $descriptor = $catalog->require($command::NAME);
                self::assertSame([], $command::ARGUMENTS);
                self::assertSame([], $command::OPTIONS);
                self::assertSame([], $descriptor->arguments());
                self::assertSame([], $descriptor->options());
                self::assertFalse(new \ReflectionClass($command)->hasConstant('MODE'));
                $tags = \array_values(
                    \array_filter(
                        $registry->all(ReservedTags::CLI_COMMAND),
                        static fn ($entry): bool => $entry->id() === $command,
                    ),
                );
                self::assertCount(1, $tags);
                $validated = $schema->validate($tags[0]);
                self::assertSame($command::NAME, $validated->name());
                self::assertArrayNotHasKey('mode', $tags[0]->meta());

                foreach (['--target=worker', '--mode=domain'] as $option) {
                    $parsed = $parser->parse(['coretsia', $command::NAME, $option]);
                    try {
                        $validator->validate($descriptor, $parsed->commandInput);
                        self::fail('Undeclared Worker option must be rejected before resolution.');
                    } catch (CliInputInvalidException $error) {
                        self::assertNotSame('', $error->reason());
                    }
                }
                $parsed = $parser->parse([
                    'coretsia',
                    $command::NAME,
                    '--format=json',
                    '--color=never',
                ]);
                $validator->validate($descriptor, $parsed->commandInput);
                self::assertSame([], $parsed->commandInput->options());
                self::assertSame([$command::NAME], $parsed->commandInput->tokens());
                self::assertSame('json', $parsed->formatOverride);
                self::assertSame('never', $parsed->colorOverride);
            }
            self::assertNoWorkerImportsInCli();
        } finally {
            self::removeTree($root);
        }
    }
}
