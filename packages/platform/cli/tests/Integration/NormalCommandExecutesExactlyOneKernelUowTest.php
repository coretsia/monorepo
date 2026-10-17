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

use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class NormalCommandExecutesExactlyOneKernelUowTest extends CliIntegrationTestCase
{
    public function testSuccessfulCommandResolvesAndExecutesInsideExactlyOneUow(): void
    {
        $fixture = new BoundaryScenarioFixture();
        $result = $fixture->run(['external:boundary', '--format=json']);
        self::assertSame(0, $result['exit']);
        self::assertSame(1, $fixture->runtime->calls);
        self::assertSame(['enter', 'resolve', 'construct', 'run', 'leave'], $fixture->runtime->events);
        self::assertTrue(BoundaryScenarioCommand::$constructedInUow);
        self::assertTrue(BoundaryScenarioCommand::$ranInUow);
        self::assertFalse($fixture->runtime->active);
        self::assertSame('', $result['stderr']);
    }

    public function testThrownCommandAndInvalidExitCodeNeverStartAnotherUow(): void
    {
        foreach (['throw', 'invalid-code'] as $case) {
            $fixture = new BoundaryScenarioFixture();
            BoundaryScenarioCommand::$throw = $case === 'throw';
            BoundaryScenarioCommand::$exitCode = $case === 'invalid-code' ? 256 : 0;
            $result = $fixture->run(['external:boundary', '--format=json']);
            self::assertSame(1, $result['exit']);
            self::assertSame(1, $fixture->runtime->calls);
            self::assertSame(['enter', 'resolve', 'construct', 'run', 'leave'], $fixture->runtime->events);
            self::assertTrue(BoundaryScenarioCommand::$ranInUow);
            self::assertFalse($fixture->runtime->active);
            self::assertSame('', $result['stderr']);
            $records = self::jsonRecord($result['stdout'])['data']['records'];
            self::assertSame('error', $records[0]['type']);
            self::assertCount(1, $records);
        }
    }

    public function testIdentityVerificationOccursInsideSameUowBeforeCommandRun(): void
    {
        $fixture = new BoundaryScenarioFixture();
        BoundaryScenarioCommand::$identityMismatch = true;
        $result = $fixture->run(['external:boundary', '--format=json']);
        self::assertSame(1, $result['exit']);
        self::assertSame(1, $fixture->runtime->calls);
        self::assertSame(['enter', 'resolve', 'construct', 'leave'], $fixture->runtime->events);
        self::assertTrue(BoundaryScenarioCommand::$constructedInUow);
        self::assertFalse(BoundaryScenarioCommand::$ran);
        self::assertSame('', $result['stderr']);
        self::assertSame(
            'CORETSIA_CLI_INVALID_COMMAND_META',
            self::jsonRecord($result['stdout'])['data']['records'][0]['code'],
        );
    }

    public function testStructuralFailureNeverStartsUowOrConstructsCommand(): void
    {
        $fixture = new BoundaryScenarioFixture();
        $result = $fixture->run(['external:boundary', '--undeclared=private', '--format=json']);
        self::assertSame(1, $result['exit']);
        self::assertSame(0, $fixture->runtime->calls);
        self::assertSame([], $fixture->runtime->events);
        self::assertFalse(BoundaryScenarioCommand::$constructed);
        self::assertFalse(BoundaryScenarioCommand::$ran);
    }
}
