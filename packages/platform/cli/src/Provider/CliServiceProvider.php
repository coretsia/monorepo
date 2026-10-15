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

namespace Coretsia\Platform\Cli\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
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
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Kernel\KernelOpsRequestResolver;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use Coretsia\Platform\Cli\Output\ColorResolver;
use Coretsia\Platform\Cli\Output\FormatResolver;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Declarative generic CLI definitions with source-host-only Kernel operations.
 *
 * @internal
 */
final class CliServiceProvider implements ServiceProviderInterface, ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();

        $builder->factory(
            KernelOpsRequestResolver::class,
            static fn (Container $container): KernelOpsRequestResolver => new KernelOpsRequestResolver(),
        );

        foreach (self::kernelOperationCommands() as $commandClass) {
            $builder->factory(
                $commandClass,
                static fn (Container $container): object => new $commandClass(
                    $container->get(KernelOpsInterface::class),
                    $container->get(KernelOpsRequestResolver::class),
                ),
            );
            $builder->tag(
                ReservedTags::CLI_COMMAND,
                $commandClass,
                meta: self::commandMeta($commandClass),
            );
        }

        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(ContainerInterface::class)
            ->requireService(TagRegistry::class)
            ->requireService(KernelRuntimeInterface::class)
            ->requireService(ContextAccessorInterface::class)
            ->requireService(TracerPortInterface::class)
            ->requireService(MeterPortInterface::class)
            ->requireService(LoggerInterface::class)
            ->requireService(Stopwatch::class)
            ->requireService(SensitiveDataRedactorInterface::class);

        $this->factory($definitions, ArgvInputParser::class, 'argvInputParser');
        $this->factory($definitions, CommandInputValidator::class, 'commandInputValidator');
        $this->factory($definitions, CommandTagSchema::class, 'commandTagSchema');
        $this->factory($definitions, CommandOverrides::class, 'commandOverrides');
        $this->factory($definitions, CommandCatalog::class, 'commandCatalog', [
            self::ref(ConfigRepositoryInterface::class),
            self::ref(TagRegistry::class),
            self::ref(CommandTagSchema::class),
            self::ref(CommandOverrides::class),
        ]);
        $this->factory($definitions, CliOutputPolicy::class, 'cliOutputPolicy', [
            self::ref(ConfigRepositoryInterface::class),
        ]);
        $this->factory($definitions, FormatResolver::class, 'formatResolver', [
            self::ref(CliOutputPolicy::class),
        ]);
        $this->factory($definitions, ColorResolver::class, 'colorResolver', [
            self::ref(CliOutputPolicy::class),
        ]);
        $this->factory($definitions, AnsiDecorator::class, 'ansiDecorator');
        $this->factory($definitions, JsonFormatter::class, 'jsonFormatter');
        $this->factory($definitions, TableFormatter::class, 'tableFormatter', [
            self::ref(CliOutputPolicy::class),
            self::ref(AnsiDecorator::class),
        ]);
        $this->factory($definitions, PlainFormatter::class, 'plainFormatter', [
            self::ref(AnsiDecorator::class),
        ]);
        $this->factory($definitions, OutputFormatter::class, 'outputFormatter', [
            self::ref(SensitiveDataRedactorInterface::class),
            self::ref(JsonFormatter::class),
            self::ref(TableFormatter::class),
            self::ref(PlainFormatter::class),
        ]);
        $this->factory($definitions, CommandRunner::class, 'commandRunner', [
            self::ref(KernelRuntimeInterface::class),
            self::ref(ContextAccessorInterface::class),
            self::ref(TracerPortInterface::class),
            self::ref(MeterPortInterface::class),
            self::ref(LoggerInterface::class),
            self::ref(ContainerInterface::class),
            self::ref(Stopwatch::class),
        ]);
        $this->factory($definitions, CliErrorHandler::class, 'cliErrorHandler');
        $this->factory($definitions, ExceptionRenderer::class, 'exceptionRenderer');
        $this->factory($definitions, CliApplication::class, 'cliApplication', [
            self::ref(ArgvInputParser::class),
            self::ref(CommandCatalog::class),
            self::ref(CommandInputValidator::class),
            self::ref(FormatResolver::class),
            self::ref(ColorResolver::class),
            self::ref(CommandRunner::class),
            self::ref(CliErrorHandler::class),
            self::ref(ExceptionRenderer::class),
            self::ref(OutputFormatter::class),
        ]);

        $this->factory($definitions, DoctorCommand::class, 'doctorCommand');
        $this->factory($definitions, HelpCommand::class, 'helpCommand', [
            self::ref(CommandCatalog::class),
        ]);
        $this->factory($definitions, ListCommand::class, 'listCommand', [
            self::ref(CommandCatalog::class),
        ]);

        foreach ([DoctorCommand::class, HelpCommand::class, ListCommand::class] as $commandClass) {
            $definitions->tag(
                ReservedTags::CLI_COMMAND,
                $commandClass,
                meta: self::commandMeta($commandClass),
            );
        }
    }

    /**
     * @param list<mixed> $arguments
     */
    private function factory(
        ContainerDefinitionBuilder $definitions,
        string $serviceId,
        string $method,
        array $arguments = [],
    ): void {
        $definitions->classMethodFactory(
            $serviceId,
            CliServiceFactory::class,
            $method,
            $arguments,
        );
    }

    private static function ref(string $serviceId): ContainerValueReference
    {
        return ContainerValueReference::service($serviceId);
    }

    /**
     * @return list<class-string<CacheVerifyCommand|ConfigCompileCommand|ConfigDebugCommand|ConfigHashCommand|ConfigValidateCommand|DebugModulesCommand>>
     */
    private static function kernelOperationCommands(): array
    {
        return [
            DebugModulesCommand::class,
            ConfigValidateCommand::class,
            ConfigDebugCommand::class,
            ConfigCompileCommand::class,
            ConfigHashCommand::class,
            CacheVerifyCommand::class,
        ];
    }

    /**
     * @param class-string<CacheVerifyCommand|ConfigCompileCommand|ConfigDebugCommand|ConfigHashCommand|ConfigValidateCommand|DebugModulesCommand|DoctorCommand|HelpCommand|ListCommand> $class
     * @return array{name: string, summary: string, group: string, hidden: bool, arguments: list<array<string, mixed>>, options: list<array<string, mixed>>}
     */
    private static function commandMeta(string $class): array
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
