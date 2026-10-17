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

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandDescriptor;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CliRejectsInvalidCommandOverridesDeterministicallyTest extends CliIntegrationTestCase
{
    public function testDynamicOverridesRejectUnknownNamesWrongValuesAndUnknownFields(): void
    {
        $descriptor = CommandDescriptor::fromValidatedMetadata(ListCommand::class, [
            'name' => ListCommand::NAME,
            'summary' => ListCommand::SUMMARY,
            'group' => ListCommand::GROUP,
            'hidden' => ListCommand::HIDDEN,
            'arguments' => ListCommand::ARGUMENTS,
            'options' => ListCommand::OPTIONS,
        ]);
        $base = ['list' => $descriptor];
        foreach (
            [
                ['PRIVATE BAD NAME' => ['summary' => 'invalid']],
                ['owner:unknown' => ['summary' => 'missing']],
                ['list' => 'not-a-map'],
                ['list' => ['summary' => 123]],
                ['list' => ['hidden' => 'true']],
                ['list' => ['group' => ['bad']]],
                ['list' => ['unknown' => 'PRIVATE_UNKNOWN_FIELD']],
            ] as $invalid
        ) {
            try {
                new CommandOverrides()->apply($base, $invalid);
                self::fail('Invalid dynamic command override was accepted.');
            } catch (CliConfigInvalidException $exception) {
                self::assertSame(CliConfigInvalidException::REASON_COMMAND_OVERRIDES_INVALID, $exception->reason());
                self::assertStringNotContainsString('PRIVATE_', $exception->getMessage());
            }
        }
    }

    public function testInvalidConfigMapAndDynamicOverridesFailAtHostBootBoundary(): void
    {
        foreach (
            [
                ['commands' => ['overrides' => 'PRIVATE_NOT_A_MAP']],
                ['commands' => ['overrides' => ['list' => ['undeclared' => 'PRIVATE_SENTINEL']]]],
                ['commands' => ['overrides' => ['owner:missing' => ['summary' => 'PRIVATE_SENTINEL']]]],
            ] as $invalid
        ) {
            $root = self::consumer();
            try {
                self::write($root . '/config/cli.php', '<?php return ' . \var_export($invalid, true) . ';');
                $result = self::execute($root . '/vendor/bin/coretsia', ['list']);
                self::assertSafeFailure($result, 'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed');
                self::assertStringNotContainsString('PRIVATE_', $result['stderr']);
                self::assertStringNotContainsString($root, $result['stderr']);
                self::assertStringNotContainsString('Throwable', $result['stderr']);
            } finally {
                self::removeTree($root);
            }
        }
    }

    public function testStructuralAndDynamicOverridesFailAtTheirRespectiveBoundaries(): void
    {
        $defaults = require self::packageRoot() . '/config/cli.php';
        $rules = require self::packageRoot() . '/config/rules.php';

        self::assertIsArray($defaults);
        self::assertIsArray($rules);

        $defaults['commands']['overrides'] = 'PRIVATE_NOT_A_MAP';

        $validation = new ConfigValidator()->validate(
            ['cli' => $defaults],
            [ConfigRuleset::fromArray('cli', $rules)],
        );

        self::assertTrue($validation->isFailure());
        self::assertSame('commands.overrides', $validation->violations()[0]->path());
        self::assertSame('type', $validation->violations()[0]->reason());

        $root = self::consumer();
        try {
            self::write(
                $root . '/config/cli.php',
                '<?php return ' . \var_export([
                    'commands' => [
                        'overrides' => [
                            'owner:missing' => ['summary' => 'PRIVATE_SENTINEL'],
                        ],
                    ],
                ], true) . ';',
            );

            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));

            try {
                $host->get(CliApplication::class);
                self::fail('Invalid dynamic override must fail before CliApplication resolution.');
            } catch (ContainerException $exception) {
                self::assertSame('container-definition-factory-failed', $exception->reason());

                $previous = $exception->getPrevious();
                self::assertInstanceOf(CliConfigInvalidException::class, $previous);
                self::assertSame(
                    CliConfigInvalidException::REASON_COMMAND_OVERRIDES_INVALID,
                    $previous->reason(),
                );
            }
        } finally {
            self::removeTree($root);
        }
    }
}
