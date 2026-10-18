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

use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliDispatchIntegrationFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingOwnerCommand;

final class GlobalFormatOptionIsNotPassedToPackageCommandTest extends CliIntegrationTestCase
{
    public function testGlobalPresentationFlagsAreRemovedWithoutChangingOwnerInput(): void
    {
        foreach ([['--format=plain', '--color=never'], ['--format=json', '--color=always']] as $globals) {
            RecordingOwnerCommand::reset();
            $fixture = new CliDispatchIntegrationFixture();
            $result = $fixture->run([
                'owner:probe',
                ...$globals,
                '--mode=domain',
                '--preset=owner',
                '--item=one',
                'subject',
                '--item=two',
            ], new TerminalCapabilities(true, true));

            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame('', $result['stderr']);
            self::assertSame(['subject'], RecordingOwnerCommand::$received['arguments']);
            self::assertSame(
                ['mode' => 'domain', 'preset' => 'owner', 'item' => ['one', 'two']],
                RecordingOwnerCommand::$received['options'],
            );
            self::assertSame([
                'owner:probe',
                '--mode=domain',
                '--preset=owner',
                '--item=one',
                'subject',
                '--item=two',
            ], RecordingOwnerCommand::$received['tokens']);
            self::assertSame(1, RecordingOwnerCommand::$executed);
            self::assertSame(1, $fixture->runtime->calls);
            if ($globals[0] === '--format=json') {
                self::assertSame('coretsia.cli-output@1', self::jsonRecord(\rtrim($result['stdout'], "\n"))['schema']);
                self::assertStringNotContainsString("\x1B", $result['stdout']);
            } else {
                self::assertStringNotContainsString('coretsia.cli-output@1', $result['stdout']);
            }
        }
    }

    public function testDomainModeAndPresetAreNotKernelBootstrapSelections(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = new CliDispatchIntegrationFixture();
        $result = $fixture->run([
            'owner:probe',
            '--mode=domain',
            '--preset=owner',
            '--format=json',
        ]);
        self::assertSame(0, $result['exit']);
        self::assertSame(
            ['mode' => 'domain', 'preset' => 'owner'],
            RecordingOwnerCommand::$received['options'],
        );
        self::assertSame(1, $fixture->runtime->calls);
        $source = (string) \file_get_contents(self::packageRoot() . '/src/Input/ArgvInputParser.php');
        self::assertStringNotContainsString('BootstrapConfig', $source);
        self::assertStringNotContainsString('KernelOpsRequest', $source);
    }
}
