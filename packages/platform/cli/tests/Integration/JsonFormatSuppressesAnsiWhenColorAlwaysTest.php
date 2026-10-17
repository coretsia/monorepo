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

use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use Coretsia\Platform\Cli\Output\ColorResolver;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class JsonFormatSuppressesAnsiWhenColorAlwaysTest extends CliIntegrationTestCase
{
    public function testJsonSuccessAndFailureRemainCanonicalAndAnsiFree(): void
    {
        $fixture = new BoundaryScenarioFixture();
        foreach (
            [
                ['external:boundary', '--format=json', '--color=always'],
                ['external:missing', '--format=json', '--color=always'],
            ] as $index => $arguments
        ) {
            $result = $fixture->run($arguments, new TerminalCapabilities(true, true));
            self::assertSame($index === 0 ? 0 : 1, $result['exit']);
            self::assertSame('', $result['stderr']);
            self::assertStringNotContainsString("\x1B", $result['stdout']);
            self::assertTrue(\str_ends_with($result['stdout'], "\n"));
            self::assertFalse(\str_ends_with($result['stdout'], "\n\n"));
            $record = self::jsonRecord($result['stdout']);
            self::assertSame($result['exit'], $record['meta']['exit_code']);
            self::assertSame($index === 0 ? 'success' : 'failure', $record['meta']['outcome']);
        }
    }

    public function testNonzeroOwnerFailureStillUsesAnsiFreeJsonEnvelope(): void
    {
        $fixture = new BoundaryScenarioFixture();
        BoundaryScenarioCommand::$exitCode = 3;
        $result = $fixture->run(
            ['external:boundary', '--format=json', '--color=always'],
            new TerminalCapabilities(true, true),
        );
        self::assertSame(3, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertStringNotContainsString("\x1B", $result['stdout']);
        self::assertTrue(\str_ends_with($result['stdout'], "\n"));
        self::assertFalse(\str_ends_with($result['stdout'], "\n\n"));
        $decoded = self::jsonRecord($result['stdout']);
        self::assertSame('failure', $decoded['meta']['outcome']);
        self::assertSame(3, $decoded['meta']['exit_code']);
    }

    public function testJsonIgnoresTerminalCapabilitiesAndAlwaysDisablesAnsi(): void
    {
        $policy = new CliOutputPolicy('json', 'table', 'plain', 'always', 100);
        $colors = new ColorResolver($policy);
        $ansi = new AnsiDecorator();
        foreach ([new TerminalCapabilities(true, true), new TerminalCapabilities(false, false)] as $capabilities) {
            self::assertFalse($colors->resolve('always', $capabilities, 'json'));
            self::assertSame('safe', $ansi->decorate('safe', 'success', true, 'json'));
        }
        $interactive = new BoundaryScenarioFixture()->run(
            ['external:boundary', '--format=json', '--color=always'],
            new TerminalCapabilities(true, true),
        );
        $nonInteractive = new BoundaryScenarioFixture()->run(
            ['external:boundary', '--format=json', '--color=always'],
            new TerminalCapabilities(false, false),
        );
        self::assertSame($interactive, $nonInteractive);
    }
}
