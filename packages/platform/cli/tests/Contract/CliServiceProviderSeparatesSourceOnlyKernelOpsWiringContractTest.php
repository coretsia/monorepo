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

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Command\CacheVerifyCommand;
use Coretsia\Platform\Cli\Command\ConfigCompileCommand;
use Coretsia\Platform\Cli\Command\ConfigDebugCommand;
use Coretsia\Platform\Cli\Command\ConfigHashCommand;
use Coretsia\Platform\Cli\Command\ConfigValidateCommand;
use Coretsia\Platform\Cli\Command\DebugModulesCommand;
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Cli\Command\WorkflowListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Kernel\KernelOpsRequestResolver;
use Coretsia\Platform\Cli\Mapper\CliExceptionMapper;
use Coretsia\Platform\Cli\Provider\CliServiceFactory;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use Coretsia\Platform\Cli\UX\SmartSuggestor;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use Coretsia\Platform\Cli\Workflow\WorkflowStepInputFactory;
use PHPUnit\Framework\TestCase;

final class CliServiceProviderSeparatesSourceOnlyKernelOpsWiringContractTest extends TestCase
{
    private const array GENERIC_COMMANDS = [
        DoctorCommand::class,
        HelpCommand::class,
        ListCommand::class,
        WorkflowListCommand::class,
        WorkflowRunCommand::class,
    ];

    private const array WORKFLOW_INFRASTRUCTURE = [
        WorkflowSchema::class,
        WorkflowCatalog::class,
        WorkflowStepInputFactory::class,
        WorkflowRunner::class,
        SmartSuggestor::class,
    ];

    private const array OPERATIONS = [
        CacheVerifyCommand::class,
        ConfigCompileCommand::class,
        ConfigDebugCommand::class,
        ConfigHashCommand::class,
        ConfigValidateCommand::class,
        DebugModulesCommand::class,
    ];

    public function testSourceHostRegistersAllOpsCommandsAndTheirTagsWithConstructorInjection(): void
    {
        self::assertTrue(\is_subclass_of(CliServiceProvider::class, ServiceProviderInterface::class));
        self::assertTrue(\is_subclass_of(CliServiceProvider::class, ContainerDefinitionProviderInterface::class));

        $builder = new ContainerBuilder();
        $builder->register(new CliServiceProvider());
        $container = $builder->build();
        self::assertTrue($container->has(KernelOpsRequestResolver::class));
        self::assertTrue($container->has(CliApplication::class));
        self::assertTrue($container->has(CliExceptionMapper::class));
        self::assertFalse($container->has(ErrorHandlerInterface::class));
        self::assertTrue($container->has(ExceptionRenderer::class));
        $tags = $builder->tagRegistry()->all(ReservedTags::CLI_COMMAND);
        $ids = \array_map(static fn ($tag): string => $tag->id(), $tags);
        $expected = [...self::OPERATIONS, ...self::GENERIC_COMMANDS];
        /** @var list<class-string> $serviceIds */
        $serviceIds = [
            ...self::GENERIC_COMMANDS,
            ...self::WORKFLOW_INFRASTRUCTURE,
        ];

        foreach ($serviceIds as $id) {
            self::assertTrue($container->has($id), $id);
        }

        \sort($ids);
        \sort($expected);
        self::assertSame($expected, $ids);

        foreach (self::OPERATIONS as $class) {
            self::assertTrue($container->has($class), $class);
            $constructor = new \ReflectionMethod($class, '__construct');
            self::assertCount(2, $constructor->getParameters(), $class);
            self::assertSame(
                KernelOpsInterface::class,
                $constructor->getParameters()[0]->getType()?->getName(),
                $class,
            );
            self::assertSame(
                KernelOpsRequestResolver::class,
                $constructor->getParameters()[1]->getType()?->getName(),
                $class,
            );
            $source = \file_get_contents(new \ReflectionClass($class)->getFileName());
            self::assertIsString($source);
            self::assertStringNotContainsString('Coretsia\\Kernel\\Ops\\KernelOpsFacade', $source);
        }

        foreach ($tags as $tag) {
            self::assertSame(0, $tag->priority());
            $keys = \array_keys($tag->meta());
            \sort($keys);
            self::assertSame(['arguments', 'group', 'hidden', 'name', 'options', 'summary'], $keys);
        }

        $mappers = $builder->tagRegistry()->all(ReservedTags::ERROR_MAPPER);
        self::assertCount(1, $mappers);
        self::assertSame(CliExceptionMapper::class, $mappers[0]->id());
        self::assertSame(100, $mappers[0]->priority());
        self::assertSame([], $mappers[0]->meta());
    }

    public function testSourceHostResolvesCommandsWithTheRegisteredKernelOpsPort(): void
    {
        $ops = $this->createStub(KernelOpsInterface::class);

        $builder = new ContainerBuilder();
        $builder->instance(KernelOpsInterface::class, $ops);
        $builder->register(new CliServiceProvider());

        $container = $builder->build();
        $resolver = $container->get(KernelOpsRequestResolver::class);

        foreach (self::OPERATIONS as $class) {
            $command = $container->get($class);

            self::assertSame($class, $command::class);
            self::assertSame($ops, new \ReflectionProperty($class, 'kernelOps')->getValue($command));
            self::assertSame($resolver, new \ReflectionProperty($class, 'requests')->getValue($command));
        }
    }

    public function testCanonicalRuntimeDefinitionsContainGenericCliOnly(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new CliServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        $set = $definitions->build();
        $operations = $set->toDescriptorStream();
        $bindingIds = [];
        $commandTagIds = [];
        $mapperTags = [];
        $referencedStrings = [];

        foreach ($operations as $operation) {
            if (isset($operation['id'])) {
                $bindingIds[] = $operation['id'];
            }
            if (($operation['tag'] ?? null) === ReservedTags::CLI_COMMAND) {
                $commandTagIds[] = $operation['serviceId'];
            } elseif (($operation['tag'] ?? null) === ReservedTags::ERROR_MAPPER) {
                $mapperTags[] = $operation;
            }
            self::collectStrings($operation, $referencedStrings);
        }

        self::collectStrings($set->requiredServiceIds(), $referencedStrings);

        foreach (
            [
                CliApplication::class,
                CliExceptionMapper::class,
                ExceptionRenderer::class,
                ...self::GENERIC_COMMANDS,
                ...self::WORKFLOW_INFRASTRUCTURE,
            ] as $id
        ) {
            self::assertContains($id, $bindingIds);
        }

        self::assertSame(self::GENERIC_COMMANDS, $commandTagIds);
        self::assertCount(1, $mapperTags);
        self::assertSame(CliExceptionMapper::class, $mapperTags[0]['serviceId']);
        self::assertSame(100, $mapperTags[0]['priority']);
        self::assertSame([], $mapperTags[0]['meta']);

        foreach ($operations as $operation) {
            if (($operation['tag'] ?? null) !== ReservedTags::CLI_COMMAND) {
                continue;
            }
            self::assertSame(0, $operation['priority']);
            $keys = \array_keys($operation['meta']);
            \sort($keys);
            self::assertSame(['arguments', 'group', 'hidden', 'name', 'options', 'summary'], $keys);
        }

        self::assertContains(ErrorHandlerInterface::class, $set->requiredServiceIds());
        self::assertNotContains(ErrorHandlerInterface::class, $bindingIds);
        self::assertNotContains(ErrorHandlerInterface::class, $commandTagIds);
        self::assertNotContains(CliExceptionMapper::class, $commandTagIds);
        self::assertCount(\count(\array_unique($bindingIds)), $bindingIds);
        self::assertSame([], \array_intersect(self::OPERATIONS, $bindingIds));

        foreach (
            [
                WorkflowSchema::class => 'workflowSchema',
                WorkflowCatalog::class => 'workflowCatalog',
                WorkflowStepInputFactory::class => 'workflowStepInputFactory',
                WorkflowRunner::class => 'workflowRunner',
                WorkflowListCommand::class => 'workflowListCommand',
                WorkflowRunCommand::class => 'workflowRunCommand',
            ] as $serviceId => $method
        ) {
            $matches = \array_values(
                \array_filter(
                    $operations,
                    static fn (array $operation): bool => ($operation['id'] ?? null) === $serviceId,
                ),
            );
            self::assertCount(1, $matches);
            self::assertSame('service.factory.class-method', $matches[0]['kind']);
            self::assertSame(CliServiceFactory::class, $matches[0]['factoryClass']);
            self::assertSame($method, $matches[0]['method']);
        }

        foreach (
            [
                KernelOpsRequestResolver::class,
                KernelOpsInterface::class,
                'Coretsia\\Kernel\\Ops\\KernelOpsHostInput',
                'Coretsia\\Kernel\\Ops\\KernelOpsHostBooter',
                ...self::OPERATIONS,
            ] as $forbidden
        ) {
            self::assertNotContains($forbidden, $referencedStrings, $forbidden);
        }

        self::assertNotContains('Coretsia\\Kernel\\Ops\\KernelOpsFacade', $referencedStrings);
    }

    /** @param list<string> $result */
    private static function collectStrings(mixed $value, array &$result): void
    {
        if (\is_string($value)) {
            $result[] = $value;
            return;
        }

        if (!\is_array($value)) {
            return;
        }

        foreach ($value as $key => $entry) {
            if (\is_string($key)) {
                $result[] = $key;
            }
            self::collectStrings($entry, $result);
        }
    }
}
