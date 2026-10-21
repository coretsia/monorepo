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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\WorkflowListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Runner\CompositeCommandInterface;
use PHPUnit\Framework\TestCase;

final class OnlyWorkflowRunMayUseCompositeCommandInterfaceTest extends TestCase
{
    public function testInternalCompositeInterfaceHasExactlyOneProductionCommandImplementation(): void
    {
        self::assertTrue(\is_subclass_of(WorkflowRunCommand::class, CompositeCommandInterface::class));
        self::assertFalse(\is_subclass_of(WorkflowRunCommand::class, CommandInterface::class));
        self::assertTrue(\is_subclass_of(WorkflowListCommand::class, CommandInterface::class));
        self::assertFalse(\is_subclass_of(WorkflowListCommand::class, CompositeCommandInterface::class));
        self::assertFalse(\is_subclass_of(CompositeCommandInterface::class, CommandInterface::class));

        $commandRoot = \dirname(__DIR__, 2) . '/src/Command';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($commandRoot, \FilesystemIterator::SKIP_DOTS),
        );
        $checked = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$checked;
            $relative = \substr($file->getPathname(), \strlen($commandRoot) + 1, -4);
            $class = 'Coretsia\\Platform\\Cli\\Command\\' . \str_replace(['/', '\\'], '\\', $relative);

            self::assertTrue(\class_exists($class), $file->getPathname());
            self::assertFalse(
                \is_subclass_of($class, CompositeCommandInterface::class)
                && \is_subclass_of($class, CommandInterface::class),
                $class,
            );
            self::assertSame(
                $class === WorkflowRunCommand::class,
                \is_subclass_of($class, CompositeCommandInterface::class),
                $class,
            );
        }
        self::assertGreaterThan(10, $checked);

        $interfaces = \class_implements(WorkflowRunCommand::class);
        self::assertIsArray($interfaces);
        self::assertSame([CompositeCommandInterface::class], \array_values($interfaces));
        self::assertSame(
            [WorkflowRunCommand::class => true],
            new \ReflectionClassConstant(CommandTagSchema::class, 'COMPOSITE_SERVICE_IDS')->getValue()
        );
    }

    public function testExternalCompositeAndDualInterfaceServiceCannotEnterCanonicalDescriptorPath(): void
    {
        $schema = new CommandTagSchema();
        foreach ([ExternalWorkflowCompositeProbe::class, DualExecutionProbe::class] as $class) {
            $metadata = self::metadata($class);
            self::assertFalse(CommandTagSchema::isCompositeServiceId($class));
            foreach (['validate', 'descriptor'] as $path) {
                try {
                    if ($path === 'validate') {
                        $schema->validate(new TaggedService($class, meta: $metadata));
                    } else {
                        CommandDescriptor::fromValidatedMetadata($class, $metadata);
                    }
                    self::fail('External or dual-interface composite service must be rejected.');
                } catch (InvalidCommandTagMetaException $exception) {
                    self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
                    self::assertSame(InvalidCommandTagMetaException::REASON_SERVICE_INVALID, $exception->reason());
                }
            }
        }

        self::assertTrue(CommandTagSchema::isCompositeServiceId(WorkflowRunCommand::class));
        $descriptor = CommandDescriptor::fromValidatedMetadata(
            WorkflowRunCommand::class,
            self::metadata(WorkflowRunCommand::class),
        );
        self::assertSame('composite', $descriptor->executionKind());
        self::assertSame(
            'composite',
            $descriptor->withCatalogView('Safe summary.', 'workflow', false)->executionKind(),
        );
    }

    public function testCompositeEligibilityCannotBeConfiguredThroughMetadataOrOverrides(): void
    {
        $metadata = self::metadata(WorkflowRunCommand::class);
        foreach (['composite', 'executionKind', 'execution_kind'] as $key) {
            try {
                CommandDescriptor::fromValidatedMetadata(WorkflowRunCommand::class, [...$metadata, $key => true]);
                self::fail('An additional command tag key must fail.');
            } catch (InvalidCommandTagMetaException $exception) {
                self::assertSame(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID, $exception->reason());
            }
            try {
                new CommandOverrides()->apply(
                    [
                        WorkflowRunCommand::NAME => CommandDescriptor::fromValidatedMetadata(
                            WorkflowRunCommand::class,
                            $metadata,
                        ),
                    ],
                    [WorkflowRunCommand::NAME => [$key => true]],
                );
                self::fail('Composite eligibility must not be an override.');
            } catch (CliConfigInvalidException $exception) {
                self::assertSame(CliConfigInvalidException::REASON_COMMAND_OVERRIDES_INVALID, $exception->reason());
            }
        }
    }

    public function testNoCrossPackageCompositeExtensionPointExists(): void
    {
        $contracts = \dirname(__DIR__, 5) . '/packages/core/contracts/src';
        self::assertDirectoryExists($contracts);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($contracts, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $code = '';

            foreach (\token_get_all($source) as $token) {
                if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= \is_array($token) ? $token[1] : $token;
            }

            self::assertSame(
                0,
                \preg_match('/CompositeCommandInterface|WorkflowCompositeCommand/', $code),
                $file->getPathname(),
            );
        }
    }

    /** @return array<string, mixed> */
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

final class ExternalWorkflowCompositeProbe implements CompositeCommandInterface
{
    public const string NAME = 'external:composite';
    public const string SUMMARY = 'External composite command is forbidden.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output, string $outputFormat): int
    {
        return 0;
    }
}

final class DualExecutionProbe implements CompositeCommandInterface, CommandInterface
{
    public const string NAME = 'external:both';
    public const string SUMMARY = 'Both command interfaces are forbidden.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [];

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output, string $outputFormat = 'plain'): int
    {
        return 0;
    }
}
