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

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
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
use Coretsia\Platform\Cli\Kernel\KernelOpsRequestResolver;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class KernelCommandsRequireExplicitTargetTest extends CliIntegrationTestCase
{
    private const array COMMANDS = [
        CacheVerifyCommand::class => 'verifyCache',
        ConfigCompileCommand::class => 'compileConfig',
        ConfigDebugCommand::class => 'debugConfig',
        ConfigHashCommand::class => 'hashConfig',
        ConfigValidateCommand::class => 'validateConfig',
        DebugModulesCommand::class => 'debugModules',
    ];

    public function testMissingTargetIsRejectedByRequestResolverBeforeAnyKernelOperation(): void
    {
        foreach (self::COMMANDS as $class => $method) {
            $ops = $this->createMock(KernelOpsInterface::class);
            $ops->expects(self::never())->method($method);
            $command = new $class($ops, new KernelOpsRequestResolver());
            $parsed = new ArgvInputParser()->parse(['coretsia', $class::NAME]);
            new CommandInputValidator()->validate(self::descriptor($class), $parsed->commandInput);

            try {
                $command->run($parsed->commandInput, new CommandOutputBuffer());
                self::fail('Kernel operations require explicit --target.');
            } catch (CliInputInvalidException $exception) {
                self::assertSame(CliInputInvalidException::REASON_TARGET_INVALID, $exception->reason(), $class);
            }
        }
    }

    public function testEmptyTargetFailsStructuralValidationBeforeKernelOperationDispatch(): void
    {
        foreach (\array_keys(self::COMMANDS) as $class) {
            $parsed = new ArgvInputParser()->parse(['coretsia', $class::NAME, '--target=']);
            try {
                new CommandInputValidator()->validate(self::descriptor($class), $parsed->commandInput);
                self::fail('Empty target must fail before resolving a command.');
            } catch (CliInputInvalidException $exception) {
                self::assertSame(CliInputInvalidException::REASON_OPTION_INVALID, $exception->reason(), $class);
            }
        }
    }

    public function testPackagedBinaryNeverInfersTargetOrPreset(): void
    {
        $root = self::consumer();
        try {
            self::assertSafeFailure(
                self::execute($root . '/vendor/bin/coretsia', ['config:validate', '--format=plain']),
                'CORETSIA_CLI_INPUT_INVALID: cli-target-invalid',
            );
            self::assertSafeFailure(
                self::execute($root . '/vendor/bin/coretsia', ['config:validate', '--target=', '--format=plain']),
                'CORETSIA_CLI_INPUT_INVALID: cli-option-invalid',
            );
        } finally {
            self::removeTree($root);
        }
    }

    /** @param class-string $class */
    private static function descriptor(string $class): CommandDescriptor
    {
        return CommandDescriptor::fromValidatedMetadata($class, [
            'name' => $class::NAME,
            'summary' => $class::SUMMARY,
            'group' => $class::GROUP,
            'hidden' => $class::HIDDEN,
            'arguments' => $class::ARGUMENTS,
            'options' => $class::OPTIONS,
        ]);
    }
}
