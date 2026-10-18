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

use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Command\CacheVerifyCommand;
use Coretsia\Platform\Cli\Command\ConfigCompileCommand;
use Coretsia\Platform\Cli\Command\ConfigDebugCommand;
use Coretsia\Platform\Cli\Command\ConfigHashCommand;
use Coretsia\Platform\Cli\Command\ConfigValidateCommand;
use Coretsia\Platform\Cli\Command\DebugModulesCommand;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Input\ArgvInputParser;
use Coretsia\Platform\Cli\Input\CommandInputValidator;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliDispatchIntegrationFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingOwnerCommand;

final class KernelCommandsRejectUndeclaredModeAndPresetOptionsTest extends CliIntegrationTestCase
{
    private const array COMMANDS = [
        CacheVerifyCommand::class,
        ConfigCompileCommand::class,
        ConfigDebugCommand::class,
        ConfigHashCommand::class,
        ConfigValidateCommand::class,
        DebugModulesCommand::class,
    ];

    public function testEveryBuiltInDeclaresOnlyTargetAndRejectsUndeclaredOptions(): void
    {
        foreach (self::COMMANDS as $class) {
            $descriptor = CommandDescriptor::fromValidatedMetadata($class, [
                'name' => $class::NAME,
                'summary' => $class::SUMMARY,
                'group' => $class::GROUP,
                'hidden' => $class::HIDDEN,
                'arguments' => $class::ARGUMENTS,
                'options' => $class::OPTIONS,
            ]);
            self::assertSame(['target'], \array_column($descriptor->options(), 'name'));
            foreach (['mode', 'preset'] as $name) {
                $parsed = new ArgvInputParser()->parse([
                    'coretsia',
                    $class::NAME,
                    '--target=console',
                    '--' . $name . '=forbidden',
                ]);
                try {
                    new CommandInputValidator()->validate($descriptor, $parsed->commandInput);
                    self::fail('Undeclared option must be rejected before service resolution.');
                } catch (CliInputInvalidException $exception) {
                    self::assertSame(CliInputInvalidException::REASON_OPTION_UNKNOWN, $exception->reason());
                }
            }
        }
    }

    public function testBinaryRejectsBothOptionsWhileExternalOwnerModeRemainsLegal(): void
    {
        $root = self::consumer();
        try {
            foreach (['mode', 'preset'] as $name) {
                self::assertSafeFailure(
                    self::execute($root . '/vendor/bin/coretsia', [
                        'config:hash',
                        '--target=console',
                        '--' . $name . '=private',
                        '--format=plain',
                    ]),
                    'CORETSIA_CLI_INPUT_INVALID: cli-option-unknown',
                );
            }
        } finally {
            self::removeTree($root);
        }
        RecordingOwnerCommand::reset();
        $result = new CliDispatchIntegrationFixture()->run(['owner:probe', '--mode=domain', '--format=json']);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame(['mode' => 'domain'], RecordingOwnerCommand::$received['options']);
    }
}
