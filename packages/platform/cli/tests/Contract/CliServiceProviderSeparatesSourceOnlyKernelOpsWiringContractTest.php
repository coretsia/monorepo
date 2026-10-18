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
use Coretsia\Platform\Cli\Diagnostics\CliErrorHandler;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Kernel\KernelOpsRequestResolver;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use PHPUnit\Framework\TestCase;

final class CliServiceProviderSeparatesSourceOnlyKernelOpsWiringContractTest extends TestCase
{
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
        self::assertTrue($container->has(CliErrorHandler::class));
        self::assertTrue($container->has(ExceptionRenderer::class));
        $tags = $builder->tagRegistry()->all(ReservedTags::CLI_COMMAND);
        $ids = \array_map(static fn ($tag): string => $tag->id(), $tags);
        $expected = [...self::OPERATIONS, DoctorCommand::class, HelpCommand::class, ListCommand::class];
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
        $tagIds = [];
        $referencedStrings = [];
        foreach ($operations as $operation) {
            if (isset($operation['id'])) {
                $bindingIds[] = $operation['id'];
            }
            if (($operation['kind'] ?? null) === 'tag') {
                $tagIds[] = $operation['serviceId'];
            }
            self::collectStrings($operation, $referencedStrings);
        }
        self::collectStrings($set->requiredServiceIds(), $referencedStrings);
        foreach (
            [
                CliApplication::class,
                CliErrorHandler::class,
                ExceptionRenderer::class,
                DoctorCommand::class,
                HelpCommand::class,
                ListCommand::class,
            ] as $id
        ) {
            self::assertContains($id, $bindingIds);
        }
        self::assertSame([DoctorCommand::class, HelpCommand::class, ListCommand::class], $tagIds);
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
