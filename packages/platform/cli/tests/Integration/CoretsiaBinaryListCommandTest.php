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

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CoretsiaBinaryListCommandTest extends CliIntegrationTestCase
{
    public function testComposerProxyAndSkeletonWrapperProduceIdenticalJsonAndTableOutput(): void
    {
        $root = self::consumer();
        try {
            foreach ([['--format=json'], ['--format=table', '--color=never']] as $arguments) {
                $argv = ['list', ...$arguments];
                $proxy = self::execute($root . '/vendor/bin/coretsia', $argv);
                $wrapper = self::execute($root . '/coretsia', $argv, self::repositoryRoot());
                self::assertSame(0, $proxy['exit'], $proxy['stderr']);
                self::assertSame($proxy, $wrapper, 'Skeleton and proxy must preserve exactly the same command input.');
                self::assertSame('', $proxy['stderr']);
                if ($arguments[0] === '--format=json') {
                    $record = self::jsonRecord($proxy['stdout']);
                    $groups = $record['data']['records'][0]['payload']['groups'];
                    self::assertArrayHasKey('core', $groups);
                    foreach (['doctor', 'help', 'list'] as $command) {
                        self::assertContains($command, \array_column($groups['core'], 'name'));
                    }
                } else {
                    self::assertStringNotContainsString("\x1B", $proxy['stdout']);
                    foreach (\explode("\n", \rtrim($proxy['stdout'], "\n")) as $line) {
                        self::assertLessThanOrEqual(120, \strlen($line), $line);
                    }
                    foreach (['doctor', 'help', 'list', 'config:compile', 'cache:verify'] as $command) {
                        self::assertStringContainsString($command, $proxy['stdout']);
                    }
                }
            }
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testListFailsClosedWhenConsoleModuleIsExplicitlyExcluded(): void
    {
        $root = self::consumer();
        try {
            self::excludeCli($root);
            $result = self::execute($root . '/vendor/bin/coretsia', ['list']);
            self::assertSafeFailure($result, 'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed');
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testExternalCommandsKeepCatalogOrderAndCompleteSummariesInWrappedTables(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(new CliServiceProvider());
        $meta = [
            'name' => ExternalModeCommand::NAME,
            'summary' => ExternalModeCommand::SUMMARY,
            'group' => ExternalModeCommand::GROUP,
            'hidden' => ExternalModeCommand::HIDDEN,
            'arguments' => ExternalModeCommand::ARGUMENTS,
            'options' => ExternalModeCommand::OPTIONS,
        ];
        $builder->tagRegistry()->add(ReservedTags::CLI_COMMAND, ExternalModeCommand::class, meta: $meta);
        $longSummary = \str_repeat('COMPLETE-SUMMARY-PART-', 5);
        $catalog = new CommandCatalog(
            $builder->tagRegistry(),
            new CommandTagSchema(),
            new CommandOverrides(),
            [
                'external:mode' => ['summary' => $longSummary, 'group' => 'utilities'],
                'debug:modules' => ['hidden' => true],
            ],
            ['doctor' => DoctorCommand::class, 'help' => HelpCommand::class, 'list' => ListCommand::class],
        );
        $before = $catalog->descriptors();
        $buffer = new CommandOutputBuffer();
        $code = new ListCommand($catalog)->run(new ArgvInput(['list'], 'list', [], []), $buffer);
        self::assertSame(0, $code);
        $record = $buffer->finalize()->records()[0]['payload']['groups'];
        self::assertArrayHasKey('utilities', $record);
        self::assertSame([['name' => 'external:mode', 'summary' => $longSummary]], $record['utilities']);
        self::assertNotContains(
            'debug:modules',
            \array_merge(
                ...\array_map(
                    static fn (array $entries): array => \array_column($entries, 'name'),
                    \array_values($record),
                ),
            ),
        );
        foreach ($record as $group => $entries) {
            $canonical = [];
            foreach ($catalog->descriptors() as $descriptor) {
                if (!$descriptor->hidden() && $descriptor->group() === $group) {
                    $canonical[] = $descriptor->name();
                }
            }
            self::assertSame($canonical, \array_column($entries, 'name'));
        }
        $output = new CommandOutputBuffer();
        $output->json(['groups' => $record]);
        $table = new TableFormatter(60, new AnsiDecorator())->format($output->finalize(), false)->stdoutBytes();
        foreach (\explode("\n", $table) as $line) {
            self::assertLessThanOrEqual(60, \strlen($line), $line);
        }
        $summary = '';
        foreach (\explode("\n", $table) as $line) {
            if (\str_starts_with($line, '|')) {
                $cells = \explode('|', \trim($line, '|'));
                $summary .= \trim($cells[1] ?? '');
            }
        }
        self::assertStringContainsString($longSummary, $summary);
        self::assertSame(1, \substr_count($summary, $longSummary));
        self::assertStringNotContainsString('...', $summary);
        self::assertSame($before, $catalog->descriptors());
    }
}
