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

namespace Coretsia\Platform\Cli\Application;

use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Diagnostics\CliErrorHandler;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Output\ColorResolver;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\FormatResolver;
use Coretsia\Platform\Cli\Output\FormattedOutput;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Cli\Runner\CommandRunner;

/**
 * One invocation of tag-backed normal CLI dispatch and safe output rendering.
 *
 * The instance holds services only; all command and output state is local to run().
 *
 * @internal
 */
final readonly class CliApplication
{
    public function __construct(
        private ArgvInputParser $parser,
        private CommandCatalog $catalog,
        private CommandInputValidator $validator,
        private FormatResolver $formats,
        private ColorResolver $colors,
        private CommandRunner $runner,
        private CliErrorHandler $errors,
        private ExceptionRenderer $exceptions,
        private OutputFormatter $output,
    ) {
    }

    /** @param array<array-key, mixed> $argv */
    public function run(
        array $argv,
        TerminalCapabilities $capabilities,
        ConsoleOutputWriter $writer,
    ): int {
        $buffer = new CommandOutputBuffer();
        $format = 'plain';
        $color = false;
        $commandName = null;
        $exitCode = 1;

        try {
            try {
                $parsed = $this->parser->parse($argv);
                $format = $this->formats->resolve($parsed->formatOverride, $capabilities);
                $color = $this->colors->resolve($parsed->colorOverride, $capabilities, $format);

                $input = $parsed->commandInput;
                $descriptor = $this->catalog->require($input->commandName());
                $commandName = $descriptor->name();
                $normalized = self::normalizeRepeatableOptions($descriptor, $input);
                $this->validator->validate($descriptor, $normalized);
                $exitCode = $this->runner->run($descriptor, $normalized, $buffer, $format);
            } catch (\Throwable $exception) {
                $exitCode = 1;
                $buffer->discard();
                $this->exceptions->render($this->errors->handle($exception), $buffer);
            }

            $batch = $buffer->finalize();
            $writer->write($this->output->format($batch, $commandName, $exitCode, $format, $color));

            return $exitCode;
        } catch (\Throwable) {
            // Formatter/redactor/diagnostics failures never retry the unsafe batch.
            try {
                $writer->write(new FormattedOutput('', 'CORETSIA_CLI_RENDER_FAILURE: render-failure'));
            } catch (\Throwable) {
                // The final sink is unavailable; no unsafe alternate sink is allowed.
            }

            return 1;
        }
    }

    private static function normalizeRepeatableOptions(
        CommandDescriptor $descriptor,
        ArgvInput $input,
    ): ArgvInput {
        $options = $input->options();

        foreach ($descriptor->options() as $definition) {
            if ($definition['repeatable'] !== true || $definition['value'] !== 'required') {
                continue;
            }

            $name = $definition['name'];
            if (\is_string($options[$name] ?? null)) {
                $options[$name] = [$options[$name]];
            }
        }

        return new ArgvInput(
            tokens: $input->tokens(),
            commandName: $input->commandName(),
            arguments: $input->arguments(),
            options: $options,
        );
    }
}
