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

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\WorkflowListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use PHPUnit\Framework\TestCase;

final class WorkflowCommandsUseExactTagMetadataTest extends TestCase
{
    private const array METADATA_KEYS = ['arguments', 'group', 'hidden', 'name', 'options', 'summary'];
    private const array CONSTANT_NAMES = ['ARGUMENTS', 'GROUP', 'HIDDEN', 'NAME', 'OPTIONS', 'SUMMARY'];

    public function testBothCommandsExposeExactCanonicalMetadataConstantsAndShapes(): void
    {
        foreach (
            [
                WorkflowListCommand::class => 'workflow:list',
                WorkflowRunCommand::class => 'workflow:run',
            ] as $class => $name
        ) {
            $constants = \array_map(
                static fn (\ReflectionClassConstant $constant): string => $constant->getName(),
                \array_values(
                    \array_filter(
                        new \ReflectionClass($class)->getReflectionConstants(\ReflectionClassConstant::IS_PUBLIC),
                        static fn (
                            \ReflectionClassConstant $constant,
                        ): bool => $constant->getDeclaringClass()->getName() === $class,
                    ),
                ),
            );
            \sort($constants);
            self::assertSame(self::CONSTANT_NAMES, $constants);
            self::assertSame($name, $class::NAME);
            self::assertSame('workflow', $class::GROUP);
            self::assertFalse($class::HIDDEN);
            self::assertSame([], $class::OPTIONS);
        }
        self::assertSame('List configured CLI workflows.', WorkflowListCommand::SUMMARY);
        self::assertSame([], WorkflowListCommand::ARGUMENTS);
        self::assertSame('Run one configured CLI workflow.', WorkflowRunCommand::SUMMARY);
        self::assertSame([
            ['name' => 'workflow', 'summary' => 'Workflow name.', 'required' => true, 'variadic' => false],
        ], WorkflowRunCommand::ARGUMENTS);
    }

    public function testSourceAndCompiledDefinitionsUseIdenticalSixKeyTagsAtPriorityZero(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(new CliServiceProvider());
        $sourceTags = $builder->tagRegistry()->all(ReservedTags::CLI_COMMAND);

        $definitions = new ContainerDefinitionBuilder();
        new CliServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        $stream = $definitions->build()->toDescriptorStream();
        $compiled = \array_values(
            \array_filter(
                $stream,
                static fn (array $operation): bool => ($operation['kind'] ?? null) === 'tag'
                    && \in_array(
                        $operation['serviceId'] ?? null,
                        [WorkflowListCommand::class, WorkflowRunCommand::class],
                        true,
                    ),
            ),
        );
        self::assertCount(2, $compiled);
        $schema = new CommandTagSchema();

        foreach ([WorkflowListCommand::class, WorkflowRunCommand::class] as $class) {
            $matched = \array_values(
                \array_filter(
                    $sourceTags,
                    static fn (TaggedService $tagged): bool => $tagged->id() === $class,
                ),
            );
            self::assertCount(1, $matched);
            $tag = $matched[0];
            self::assertSame(0, $tag->priority());
            $meta = $tag->meta();
            $keys = \array_keys($meta);
            \sort($keys);
            self::assertSame(self::METADATA_KEYS, $keys);
            $expected = CommandTagSchema::normalize([
                'name' => $class::NAME,
                'summary' => $class::SUMMARY,
                'group' => $class::GROUP,
                'hidden' => $class::HIDDEN,
                'arguments' => $class::ARGUMENTS,
                'options' => $class::OPTIONS,
            ]);
            self::assertSame($expected, CommandTagSchema::normalize($meta));
            $descriptor = $schema->validate($tag);
            self::assertInstanceOf(CommandDescriptor::class, $descriptor);
            self::assertSame($class::NAME, $descriptor->name());
            self::assertSame($class, $descriptor->serviceId());
            self::assertSame($expected, $schema->canonicalMetadata($tag));

            $matches = \array_values(
                \array_filter(
                    $compiled,
                    static fn (array $operation): bool => $operation['serviceId'] === $class,
                ),
            );
            self::assertCount(1, $matches);
            self::assertSame(ReservedTags::CLI_COMMAND, $matches[0]['tag']);
            self::assertSame(0, $matches[0]['priority']);
            $compiledKeys = \array_keys($matches[0]['meta']);
            \sort($compiledKeys);
            self::assertSame(self::METADATA_KEYS, $compiledKeys);
            self::assertSame($expected, CommandTagSchema::normalize($matches[0]['meta']));
        }
    }
}
