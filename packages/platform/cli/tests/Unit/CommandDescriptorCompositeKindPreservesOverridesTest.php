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

use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use PHPUnit\Framework\TestCase;

final class CommandDescriptorCompositeKindPreservesOverridesTest extends TestCase
{
    public function testCatalogViewOverridesPreserveBothDerivedExecutionKindsAndIdentity(): void
    {
        $catalog = self::catalog([
            WorkflowRunCommand::NAME => [
                'summary' => 'Composite catalog presentation override.',
                'group' => 'tools',
                'hidden' => true,
            ],
            ExternalModeCommand::NAME => [
                'summary' => 'Normal catalog presentation override.',
                'group' => 'tools',
                'hidden' => true,
            ],
        ]);

        foreach (
            [
                WorkflowRunCommand::class => 'composite',
                ExternalModeCommand::class => 'normal',
            ] as $serviceId => $kind
        ) {
            $descriptor = $catalog->require($serviceId::NAME);
            self::assertSame($kind, $descriptor->executionKind());
            self::assertSame($serviceId, $descriptor->serviceId());
            self::assertSame($serviceId::NAME, $descriptor->name());
            self::assertSame(
                CommandTagSchema::normalize(['arguments' => $serviceId::ARGUMENTS])['arguments'],
                $descriptor->arguments(),
            );
            self::assertSame(
                CommandTagSchema::normalize(['options' => $serviceId::OPTIONS])['options'],
                $descriptor->options(),
            );
            self::assertSame('tools', $descriptor->group());
            self::assertTrue($descriptor->hidden());
            self::assertSame($descriptor, $catalog->find($descriptor->name()));
            self::assertSame(
                $kind,
                $descriptor->withCatalogView('Another safe summary.', 'other', false)->executionKind(),
            );
        }

        self::assertSame(
            'Composite catalog presentation override.',
            $catalog->require(WorkflowRunCommand::NAME)->summary(),
        );
        self::assertSame(
            'Normal catalog presentation override.',
            $catalog->require(ExternalModeCommand::NAME)->summary(),
        );
    }

    public function testExecutionKindCannotBeProvidedAsTagMetadataOrCatalogOverride(): void
    {
        $metadata = self::metadata(WorkflowRunCommand::class);
        self::assertSame(['name', 'summary', 'group', 'hidden', 'arguments', 'options'], \array_keys($metadata));

        try {
            CommandDescriptor::fromValidatedMetadata(WorkflowRunCommand::class, [
                ...$metadata,
                'executionKind' => 'normal',
            ]);
            self::fail('Execution kind must never be a seventh tag metadata field.');
        } catch (InvalidCommandTagMetaException $exception) {
            self::assertSame(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID, $exception->reason());
        }

        try {
            self::catalog([WorkflowRunCommand::NAME => ['executionKind' => 'normal']]);
            self::fail('Execution kind must never be a catalog-view override.');
        } catch (CliConfigInvalidException $exception) {
            self::assertSame(CliConfigInvalidException::REASON_COMMAND_OVERRIDES_INVALID, $exception->reason());
        }
    }

    public function testExecutionKindIsNotExportedIntoCommandCatalogOutput(): void
    {
        $catalog = self::catalog();
        $output = new CommandOutputBuffer();
        $input = new ArgvInput([ListCommand::NAME], ListCommand::NAME, [], []);

        self::assertSame(0, new ListCommand($catalog)->run($input, $output));
        $records = $output->finalize()->records();
        self::assertCount(1, $records);
        self::assertSame('json', $records[0]['type']);
        self::assertSame([
            'groups' => [
                'external' => [
                    [
                        'name' => ExternalModeCommand::NAME,
                        'summary' => ExternalModeCommand::SUMMARY,
                    ],
                ],
                'workflow' => [
                    [
                        'name' => WorkflowRunCommand::NAME,
                        'summary' => WorkflowRunCommand::SUMMARY,
                    ],
                ],
            ],
        ], $records[0]['payload']);
        self::assertStringNotContainsString('executionKind', \json_encode($records, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('execution_kind', \json_encode($records, \JSON_THROW_ON_ERROR));
    }

    public function testExecutionKindIsNotExportedInCommandSpecificHelp(): void
    {
        $catalog = self::catalog();

        foreach ([WorkflowRunCommand::NAME, ExternalModeCommand::NAME] as $name) {
            $output = new CommandOutputBuffer();
            $input = new ArgvInput([HelpCommand::NAME, $name], HelpCommand::NAME, [$name], []);

            self::assertSame(0, new HelpCommand($catalog)->run($input, $output));
            $records = $output->finalize()->records();

            self::assertCount(1, $records);
            self::assertSame('json', $records[0]['type']);
            self::assertSame(
                ['arguments', 'group', 'name', 'options', 'summary'],
                \array_keys($records[0]['payload']),
            );
            self::assertSame($name, $records[0]['payload']['name']);
        }
    }

    public function testCatalogAndBothExecutionPathsConsumeOneDerivedClassification(): void
    {
        self::assertSame(
            'composite',
            CommandDescriptor::fromValidatedMetadata(
                WorkflowRunCommand::class,
                self::metadata(WorkflowRunCommand::class),
            )->executionKind(),
        );
        self::assertSame(
            'normal',
            CommandDescriptor::fromValidatedMetadata(
                ExternalModeCommand::class,
                self::metadata(ExternalModeCommand::class),
            )->executionKind(),
        );

        foreach ([CommandRunner::class, WorkflowCatalog::class, WorkflowRunner::class] as $class) {
            $sourcePath = new \ReflectionClass($class)->getFileName();
            self::assertIsString($sourcePath);

            $source = \file_get_contents($sourcePath);
            self::assertIsString($source);
            self::assertStringContainsString('->executionKind()', $source);
            self::assertStringNotContainsString('CommandTagSchema::isCompositeServiceId(', $source);
        }
        self::assertSame('composite', self::catalog()->require(WorkflowRunCommand::NAME)->executionKind());
        self::assertSame('normal', self::catalog()->require(ExternalModeCommand::NAME)->executionKind());
    }

    /** @param array<string, array<string, mixed>> $overrides */
    private static function catalog(array $overrides = []): CommandCatalog
    {
        $registry = new TagRegistry();
        foreach ([WorkflowRunCommand::class, ExternalModeCommand::class] as $class) {
            $registry->add(ReservedTags::CLI_COMMAND, $class, meta: self::metadata($class));
        }

        return new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), $overrides, []);
    }

    /** @param class-string $class @return array<string, mixed> */
    private static function metadata(string $class): array
    {
        return [
            'name' => $class::NAME,
            'summary' => $class::SUMMARY,
            'group' => $class::GROUP,
            'hidden' => $class::HIDDEN,
            'arguments' => $class::ARGUMENTS,
            'options' => $class::OPTIONS,
        ];
    }
}
