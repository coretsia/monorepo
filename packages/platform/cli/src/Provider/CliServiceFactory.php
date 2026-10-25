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
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Cli\Command\WorkflowListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use Coretsia\Platform\Cli\Output\ColorResolver;
use Coretsia\Platform\Cli\Output\FormatResolver;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use Coretsia\Platform\Cli\Runner\CommandRunner;
use Coretsia\Platform\Cli\UX\SmartSuggestor;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use Coretsia\Platform\Cli\Workflow\WorkflowStepInputFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Stateless construction helper for canonical CLI container definitions.
 *
 * No invocation state or configuration defaults are owned here.
 *
 * @internal
 */
final class CliServiceFactory
{
    public static function argvInputParser(): ArgvInputParser
    {
        return new ArgvInputParser();
    }

    public static function commandInputValidator(): CommandInputValidator
    {
        return new CommandInputValidator();
    }

    public static function commandTagSchema(): CommandTagSchema
    {
        return new CommandTagSchema();
    }

    public static function commandOverrides(): CommandOverrides
    {
        return new CommandOverrides();
    }

    public static function commandCatalog(
        ConfigRepositoryInterface $config,
        TagRegistry $tags,
        CommandTagSchema $schema,
        CommandOverrides $overrides,
    ): CommandCatalog {
        $root = self::cliConfigRoot($config);
        $commands = self::map($root['commands'] ?? null);
        $overrideMap = self::map($commands['overrides'] ?? null);

        return new CommandCatalog(
            registry: $tags,
            schema: $schema,
            overlay: $overrides,
            overrides: $overrideMap,
            reservedBuiltInServiceIds: [
                DoctorCommand::NAME => DoctorCommand::class,
                HelpCommand::NAME => HelpCommand::class,
                ListCommand::NAME => ListCommand::class,
            ],
        );
    }

    public static function workflowSchema(): WorkflowSchema
    {
        return new WorkflowSchema();
    }

    public static function workflowCatalog(
        ConfigRepositoryInterface $config,
        WorkflowSchema $schema,
        CommandCatalog $commands,
    ): WorkflowCatalog {
        $root = self::cliConfigRoot($config);
        $workflows = self::map($root['workflows'] ?? null);
        $definitions = self::map($workflows['definitions'] ?? null);

        return new WorkflowCatalog($schema->normalize($definitions), $commands);
    }

    public static function workflowStepInputFactory(): WorkflowStepInputFactory
    {
        return new WorkflowStepInputFactory();
    }

    public static function workflowRunner(
        CommandCatalog $commands,
        CommandInputValidator $validator,
        CommandRunner $runner,
        WorkflowStepInputFactory $inputs,
    ): WorkflowRunner {
        return new WorkflowRunner($commands, $validator, $runner, $inputs);
    }

    public static function cliOutputPolicy(ConfigRepositoryInterface $config): CliOutputPolicy
    {
        $root = self::cliConfigRoot($config);
        $output = self::map($root['output'] ?? null);
        $adaptive = self::map($output['adaptive'] ?? null);
        $table = self::map($output['table'] ?? null);

        $formatDefault = $output['format_default'] ?? null;
        $interactive = $adaptive['interactive'] ?? null;
        $nonInteractive = $adaptive['non_interactive'] ?? null;
        $colorDefault = $output['color_default'] ?? null;
        $tableMaxWidth = $table['max_width'] ?? null;

        if (
            !\is_string($formatDefault)
            || !\is_string($interactive)
            || !\is_string($nonInteractive)
            || !\is_string($colorDefault)
            || !\is_int($tableMaxWidth)
        ) {
            throw self::invalidConfig();
        }

        return new CliOutputPolicy(
            formatDefault: $formatDefault,
            interactiveFormat: $interactive,
            nonInteractiveFormat: $nonInteractive,
            colorDefault: $colorDefault,
            tableMaxWidth: $tableMaxWidth,
        );
    }

    public static function formatResolver(CliOutputPolicy $policy): FormatResolver
    {
        return new FormatResolver($policy);
    }

    public static function colorResolver(CliOutputPolicy $policy): ColorResolver
    {
        return new ColorResolver($policy);
    }

    public static function ansiDecorator(): AnsiDecorator
    {
        return new AnsiDecorator();
    }

    public static function jsonFormatter(): JsonFormatter
    {
        return new JsonFormatter();
    }

    public static function tableFormatter(CliOutputPolicy $policy, AnsiDecorator $ansi): TableFormatter
    {
        return new TableFormatter($policy->tableMaxWidth(), $ansi);
    }

    public static function plainFormatter(AnsiDecorator $ansi): PlainFormatter
    {
        return new PlainFormatter($ansi);
    }

    public static function outputFormatter(
        SensitiveDataRedactorInterface $redactor,
        JsonFormatter $json,
        TableFormatter $table,
        PlainFormatter $plain,
    ): OutputFormatter {
        return new OutputFormatter($redactor, $json, $table, $plain);
    }

    public static function commandRunner(
        KernelRuntimeInterface $runtime,
        ContextAccessorInterface $context,
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        LoggerInterface $logger,
        ContainerInterface $container,
        Stopwatch $stopwatch,
    ): CommandRunner {
        return new CommandRunner(
            $runtime,
            $context,
            $tracer,
            $meter,
            $logger,
            $container,
            $stopwatch,
        );
    }

    public static function smartSuggestor(): SmartSuggestor
    {
        return new SmartSuggestor();
    }

    public static function exceptionRenderer(): ExceptionRenderer
    {
        return new ExceptionRenderer();
    }

    public static function cliApplication(
        ArgvInputParser $parser,
        CommandCatalog $catalog,
        CommandInputValidator $validator,
        FormatResolver $formats,
        ColorResolver $colors,
        CommandRunner $runner,
        ErrorHandlerInterface $errors,
        ExceptionRenderer $exceptions,
        OutputFormatter $output,
        SmartSuggestor $suggestions,
    ): CliApplication {
        return new CliApplication(
            $parser,
            $catalog,
            $validator,
            $formats,
            $colors,
            $runner,
            $errors,
            $exceptions,
            $output,
            $suggestions,
        );
    }

    public static function doctorCommand(): DoctorCommand
    {
        return new DoctorCommand();
    }

    public static function helpCommand(CommandCatalog $catalog): HelpCommand
    {
        return new HelpCommand($catalog);
    }

    public static function listCommand(CommandCatalog $catalog): ListCommand
    {
        return new ListCommand($catalog);
    }

    public static function workflowListCommand(WorkflowCatalog $catalog): WorkflowListCommand
    {
        return new WorkflowListCommand($catalog);
    }

    public static function workflowRunCommand(
        WorkflowCatalog $catalog,
        WorkflowRunner $runner,
    ): WorkflowRunCommand {
        return new WorkflowRunCommand($catalog, $runner);
    }

    /** @return array<string, mixed> */
    private static function cliConfigRoot(ConfigRepositoryInterface $config): array
    {
        try {
            if (!$config->has('cli')) {
                throw self::invalidConfig();
            }

            return self::map($config->get('cli'));
        } catch (\Throwable) {
            throw self::invalidConfig();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value): array
    {
        if (!\is_array($value) || ($value !== [] && \array_is_list($value))) {
            throw self::invalidConfig();
        }

        foreach ($value as $key => $_item) {
            if (!\is_string($key)) {
                throw self::invalidConfig();
            }
        }

        return $value;
    }

    private static function invalidConfig(): CliConfigInvalidException
    {
        return CliConfigInvalidException::withReason(CliConfigInvalidException::REASON_SERVICE_CONFIG_INVALID);
    }
}
