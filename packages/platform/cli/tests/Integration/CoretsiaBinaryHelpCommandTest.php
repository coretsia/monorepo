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
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
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
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CoretsiaBinaryHelpCommandTest extends CliIntegrationTestCase
{
    public function testGeneralAndCommandSpecificHelpAreDescriptorDrivenAndDeterministic(): void
    {
        $root = self::consumer();
        try {
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $catalog = $host->get(CommandCatalog::class);

            foreach (['json', 'table', 'plain'] as $format) {
                $result = self::execute(
                    $root . '/vendor/bin/coretsia',
                    ['help', '--format=' . $format, '--color=never'],
                );
                self::assertSame(0, $result['exit'], $result['stderr']);
                self::assertSame('', $result['stderr']);
                if ($format === 'json') {
                    $envelope = self::jsonRecord($result['stdout']);
                    $commands = $envelope['data']['records'][0]['payload']['commands'];
                    $expected = [];

                    foreach ($catalog->descriptors() as $descriptor) {
                        if ($descriptor->hidden()) {
                            continue;
                        }

                        $expected[] = [
                            'group' => $descriptor->group(),
                            'name' => $descriptor->name(),
                            'summary' => $descriptor->summary(),
                        ];
                    }

                    self::assertSame($expected, $commands);
                } else {
                    self::assertStringContainsString('doctor', $result['stdout']);
                }
                if ($format === 'table') {
                    foreach (\explode("\n", \rtrim($result['stdout'], "\n")) as $line) {
                        self::assertLessThanOrEqual(120, \strlen($line), $line);
                    }
                }
            }
            $specific = self::execute($root . '/vendor/bin/coretsia', ['help', 'config:compile', '--format=json']);
            self::assertSame(0, $specific['exit'], $specific['stderr']);
            $payload = self::jsonRecord($specific['stdout'])['data']['records'][0]['payload'];
            self::assertSame('config:compile', $payload['name']);
            $descriptor = $catalog->require('config:compile');

            self::assertSame($descriptor->group(), $payload['group']);
            self::assertSame($descriptor->summary(), $payload['summary']);
            self::assertSame($descriptor->arguments(), $payload['arguments']);
            self::assertSame($descriptor->options(), $payload['options']);
            $table = self::execute($root . '/vendor/bin/coretsia', ['help', 'config:compile', '--format=table']);
            self::assertSame(0, $table['exit'], $table['stderr']);
            self::assertStringContainsString('target', $table['stdout']);
            $invalid = self::execute($root . '/vendor/bin/coretsia', ['help', 'not:a:command', '--format=plain']);
            self::assertSafeFailure(
                $invalid,
                'CORETSIA_CLI_INPUT_INVALID: cli-command-unknown',
            );
            self::assertStringNotContainsString($root, $invalid['stderr']);
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testDescriptorOnlyHelpKeepsLongSummariesAndOptionsAcrossTableRendering(): void
    {
        $registry = new TagRegistry();
        foreach ([DoctorCommand::class, HelpCommand::class, ListCommand::class, ExternalModeCommand::class] as $class) {
            $registry->add(ReservedTags::CLI_COMMAND, $class, meta: [
                'name' => $class::NAME,
                'summary' => $class::SUMMARY,
                'group' => $class::GROUP,
                'hidden' => $class::HIDDEN,
                'arguments' => $class::ARGUMENTS,
                'options' => $class::OPTIONS,
            ]);
        }
        $summary = \str_repeat('COMPLETE-SUMMARY-PART-', 5);
        $catalog = new CommandCatalog(
            $registry,
            new CommandTagSchema(),
            new CommandOverrides(),
            ['external:mode' => ['summary' => $summary], 'doctor' => ['hidden' => true]],
            ['doctor' => DoctorCommand::class, 'help' => HelpCommand::class, 'list' => ListCommand::class],
        );
        $snapshot = $catalog->descriptors();
        $generalBuffer = new CommandOutputBuffer();
        self::assertSame(0, new HelpCommand($catalog)->run(new ArgvInput(['help'], 'help', [], []), $generalBuffer));
        $payload = $generalBuffer->finalize()->records()[0]['payload']['commands'];
        self::assertNotContains('doctor', \array_column($payload, 'name'));
        self::assertContains('external:mode', \array_column($payload, 'name'));
        $summaryValues = \array_column($payload, 'summary');
        self::assertContains($summary, $summaryValues);
        self::assertSame(1, \count(\array_keys($summaryValues, $summary, true)));
        $canonicalNames = [];
        foreach ($catalog->descriptors() as $descriptor) {
            if (!$descriptor->hidden()) {
                $canonicalNames[] = $descriptor->name();
            }
        }
        self::assertSame($canonicalNames, \array_column($payload, 'name'));
        $renderBuffer = new CommandOutputBuffer();
        $renderBuffer->json(['commands' => $payload]);
        $rendered = new TableFormatter(60, new AnsiDecorator())->format(
            $renderBuffer->finalize(),
            false,
        )->stdoutBytes();
        self::assertSame(0, \preg_match('/\x1B/', $rendered));
        $collected = '';
        foreach (\explode("\n", $rendered) as $line) {
            self::assertLessThanOrEqual(60, \strlen($line), $line);
            if (\str_starts_with($line, '|')) {
                $parts = \explode('|', \trim($line, '|'));
                $collected .= \trim($parts[2] ?? '');
            }
        }
        self::assertStringContainsString($summary, $collected);
        $specific = new CommandOutputBuffer();
        $input = new ArgvInput(['help', 'external:mode'], 'help', ['external:mode'], []);
        self::assertSame(0, new HelpCommand($catalog)->run($input, $specific));
        $specificRecord = $specific->finalize()->records()[0]['payload'];
        self::assertSame(['mode', 'item'], \array_column($specificRecord['options'], 'name'));
        $renderBuffer = new CommandOutputBuffer();
        $renderBuffer->json($specificRecord);
        $table = new TableFormatter(60, new AnsiDecorator())->format($renderBuffer->finalize(), false)->stdoutBytes();
        self::assertStringContainsString('mode', $table);
        self::assertStringContainsString('item', $table);
        foreach (\explode("\n", $table) as $line) {
            self::assertLessThanOrEqual(60, \strlen($line), $line);
        }
        self::assertSame($snapshot, $catalog->descriptors());
    }
}
