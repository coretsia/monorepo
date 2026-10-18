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

use Coretsia\Platform\Cli\Tests\Integration\Support\CliDispatchIntegrationFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingOwnerCommand;

final class CommandInputValidatorRejectsUnknownOptionBeforeResolutionTest extends CliIntegrationTestCase
{
    public function testUnknownOptionCannotConstructOrRunSelectedOwnerCommand(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = new CliDispatchIntegrationFixture();
        $secret = 'PRIVATE_OPTION_VALUE_' . \bin2hex(\random_bytes(8));
        $result = $fixture->run(['owner:probe', '--unexpected=' . $secret, '--format=plain']);

        self::assertSafeFailure($result, 'CORETSIA_CLI_INPUT_INVALID: cli-option-unknown');
        self::assertSame([], $fixture->container->resolved);
        self::assertSame(0, $fixture->runtime->calls);
        self::assertSame(0, RecordingOwnerCommand::$constructed);
        self::assertSame(0, RecordingOwnerCommand::$executed);
        self::assertNull(RecordingOwnerCommand::$received);
        self::assertStringNotContainsString($secret, $result['stderr']);
    }

    public function testKnownOptionStillRunsCommandThroughSingleKernelUnitOfWork(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = new CliDispatchIntegrationFixture();
        $result = $fixture->run(['owner:probe', '--mode=domain', '--format=json']);

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(1, $fixture->runtime->calls);
        self::assertSame([RecordingOwnerCommand::class], $fixture->container->resolved);
        self::assertSame(1, RecordingOwnerCommand::$constructed);
        self::assertSame(1, RecordingOwnerCommand::$executed);
        self::assertSame(['mode' => 'domain'], RecordingOwnerCommand::$received['options']);
    }
}
