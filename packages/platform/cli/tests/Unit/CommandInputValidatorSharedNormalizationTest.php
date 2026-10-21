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

use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand\ExternalModeCommand;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use Coretsia\Platform\Cli\Workflow\WorkflowStepInputFactory;
use PHPUnit\Framework\TestCase;

final class CommandInputValidatorSharedNormalizationTest extends TestCase
{
    public function testSingleRepeatableRequiredValueBecomesOneElementList(): void
    {
        $raw = self::input(['item' => 'only']);
        $normalized = new CommandInputValidator()->normalizeAndValidate(self::descriptor(), $raw);

        self::assertSame(['item' => 'only'], $raw->options());
        self::assertSame(['item' => ['only']], $normalized->options());
        self::assertSame($raw->tokens(), $normalized->tokens());
        self::assertSame($raw->arguments(), $normalized->arguments());
        self::assertSame($raw->commandName(), $normalized->commandName());
    }

    public function testMultipleRepeatableValuesRetainTheirDeclaredOrder(): void
    {
        $raw = self::input(['mode' => 'fast', 'item' => ['third', 'first', 'second']]);
        $normalized = new CommandInputValidator()->normalizeAndValidate(self::descriptor(), $raw);

        self::assertSame(['mode' => 'fast', 'item' => ['third', 'first', 'second']], $normalized->options());
        self::assertSame(['third', 'first', 'second'], $normalized->option('item'));
        self::assertSame(
            $normalized->options(),
            new CommandInputValidator()->normalizeAndValidate(
                self::descriptor(),
                $normalized,
            )->options(),
        );
    }

    public function testNonRepeatableRequiredOptionRetainsExistingRejectionReason(): void
    {
        $input = self::input(['mode' => ['fast', 'safe']]);

        try {
            new CommandInputValidator()->normalizeAndValidate(self::descriptor(), $input);
            self::fail('A repeated non-repeatable option must be rejected.');
        } catch (CliInputInvalidException $exception) {
            self::assertSame(CliInputInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(CliInputInvalidException::REASON_OPTION_REPEATED, $exception->reason());
            self::assertSame(
                CliInputInvalidException::ERROR_CODE . ': ' . CliInputInvalidException::REASON_OPTION_REPEATED,
                $exception->getMessage(),
            );
        }
    }

    public function testNormalArgvAndStructuredWorkflowStepShareOneValidationPolicy(): void
    {
        $descriptor = self::descriptor();
        $validator = new CommandInputValidator();
        $parser = new ArgvInputParser();
        $factory = new WorkflowStepInputFactory();
        $schema = new WorkflowSchema();

        foreach ([['only'], ['third', 'first', 'second']] as $items) {
            $definitions = $schema->normalize([
                'verify' => [
                    'summary' => 'Verify the command input.',
                    'steps' => [
                        [
                            'command' => ExternalModeCommand::NAME,
                            'arguments' => [],
                            'options' => ['item' => \count($items) === 1 ? $items[0] : $items],
                        ],
                    ],
                ],
            ]);
            $workflowInput = $factory->create($definitions['verify']->steps()[0]);
            $normalArgv = [
                'coretsia',
                ExternalModeCommand::NAME,
                ...\array_map(static fn (string $value): string => '--item=' . $value, $items),
            ];
            $normalInput = $parser->parse($normalArgv)->commandInput;

            self::assertSame(
                $validator->normalizeAndValidate($descriptor, $normalInput)->options(),
                $validator->normalizeAndValidate($descriptor, $workflowInput)->options(),
            );
            self::assertSame(
                ['item' => $items],
                $validator->normalizeAndValidate($descriptor, $workflowInput)->options()
            );
        }

        $invalidStep = $schema->normalize([
            'verify' => [
                'summary' => 'Verify rejection parity.',
                'steps' => [
                    [
                        'command' => ExternalModeCommand::NAME,
                        'arguments' => [],
                        'options' => ['mode' => ['fast', 'safe']],
                    ],
                ],
            ],
        ]);
        $workflowInput = $factory->create($invalidStep['verify']->steps()[0]);
        $normalInput = $parser->parse([
            'coretsia',
            ExternalModeCommand::NAME,
            '--mode=fast',
            '--mode=safe',
        ])->commandInput;

        foreach ([$workflowInput, $normalInput] as $input) {
            try {
                $validator->normalizeAndValidate($descriptor, $input);
                self::fail('The shared validator must reject repeated non-repeatable options.');
            } catch (CliInputInvalidException $exception) {
                self::assertSame(CliInputInvalidException::REASON_OPTION_REPEATED, $exception->reason());
            }
        }
    }

    private static function descriptor(): CommandDescriptor
    {
        return CommandDescriptor::fromValidatedMetadata(ExternalModeCommand::class, [
            'name' => ExternalModeCommand::NAME,
            'summary' => ExternalModeCommand::SUMMARY,
            'group' => ExternalModeCommand::GROUP,
            'hidden' => ExternalModeCommand::HIDDEN,
            'arguments' => ExternalModeCommand::ARGUMENTS,
            'options' => ExternalModeCommand::OPTIONS,
        ]);
    }

    /** @param array<string, string|bool|list<string>|null> $options */
    private static function input(array $options): ArgvInput
    {
        return new ArgvInput(
            tokens: [ExternalModeCommand::NAME],
            commandName: ExternalModeCommand::NAME,
            arguments: [],
            options: $options,
        );
    }
}
