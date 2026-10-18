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

final class CommandInputValidatorPreservesRepeatableOptionOrderTest extends CliIntegrationTestCase
{
    public function testRepeatableRequiredValuesReachOwnerInOriginalOrderExactlyOnce(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = new CliDispatchIntegrationFixture();
        $result = $fixture->run([
            'owner:probe',
            '--item=third',
            '--mode=domain',
            '--item=first',
            '--item=second',
            '--format=json',
        ]);

        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame(['third', 'first', 'second'], RecordingOwnerCommand::$received['options']['item']);
        self::assertSame('domain', RecordingOwnerCommand::$received['options']['mode']);
        self::assertSame(1, RecordingOwnerCommand::$executed);
        self::assertSame([RecordingOwnerCommand::class], $fixture->container->resolved);
        $document = self::jsonRecord(\rtrim($result['stdout'], "\n"));
        self::assertSame(
            ['third', 'first', 'second'],
            $document['data']['records'][0]['payload']['items'],
        );
    }

    public function testRepeatedNonrepeatableOptionIsRejectedBeforeOwnerResolution(): void
    {
        RecordingOwnerCommand::reset();
        $fixture = new CliDispatchIntegrationFixture();
        $result = $fixture->run(['owner:probe', '--mode=first', '--mode=second', '--format=plain']);

        self::assertSafeFailure($result, 'CORETSIA_CLI_INPUT_INVALID: cli-option-repeated');
        self::assertSame([], $fixture->container->resolved);
        self::assertSame(0, $fixture->runtime->calls);
        self::assertSame(0, RecordingOwnerCommand::$constructed);
        self::assertSame(0, RecordingOwnerCommand::$executed);
    }
}
