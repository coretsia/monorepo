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

final class ExternalPackageCommandExitCodeIsPreservedTest extends CliIntegrationTestCase
{
    public function testEveryPortableOwnerExitCodeIsPreservedUnmodified(): void
    {
        foreach ([0, 2, 3, 255] as $code) {
            $fixture = new BoundaryScenarioFixture();
            BoundaryScenarioCommand::$exitCode = $code;
            $result = $fixture->run(['external:boundary', '--format=json']);
            self::assertSame($code, $result['exit']);
            self::assertSame(1, $fixture->runtime->calls);
            self::assertSame('', $result['stderr']);
            $record = self::jsonRecord($result['stdout']);
            self::assertSame($code, $record['meta']['exit_code']);
            self::assertSame($code === 0 ? 'success' : 'failure', $record['meta']['outcome']);
            self::assertSame(
                ['payload' => ['status' => 'owner-handled'], 'type' => 'json'],
                $record['data']['records'][0]
            );
        }
    }

    public function testOutOfRangeExitCodesAndThrownErrorsHaveTheirOwnFixedBoundary(): void
    {
        foreach ([-1, 256] as $code) {
            $fixture = new BoundaryScenarioFixture();
            BoundaryScenarioCommand::$exitCode = $code;
            $result = $fixture->run(['external:boundary', '--format=json']);
            self::assertSame(1, $result['exit']);
            self::assertSame(1, $fixture->runtime->calls);
            self::assertSame('', $result['stderr']);
            $records = self::jsonRecord($result['stdout'])['data']['records'];
            self::assertSame([
                [
                    'code' => 'CORETSIA_CLI_COMMAND_FAILED',
                    'message' => 'cli-command-failed',
                    'type' => 'error',
                ],
            ], $records);
        }
        $fixture = new BoundaryScenarioFixture();
        BoundaryScenarioCommand::$throw = true;
        $error = $fixture->run(['external:boundary', '--format=json']);
        self::assertSame(1, $error['exit']);
        self::assertSame(
            'CORETSIA_CLI_INTERNAL_ERROR',
            self::jsonRecord($error['stdout'])['data']['records'][0]['code'],
        );
        self::assertStringNotContainsString('PRIVATE_OWNER_THROWABLE', $error['stdout']);
    }

    public function testGlobalParsingFailureCannotBeMistakenForOwnerExitCode(): void
    {
        $fixture = new BoundaryScenarioFixture();
        BoundaryScenarioCommand::$exitCode = 255;
        $result = $fixture->run(['external:boundary', '--format=PRIVATE_INVALID'], null);
        self::assertSame(1, $result['exit']);
        self::assertSame(0, $fixture->runtime->calls);
        self::assertFalse(BoundaryScenarioCommand::$constructed);
        self::assertFalse(BoundaryScenarioCommand::$ran);
        self::assertStringNotContainsString('PRIVATE_INVALID', $result['stderr']);
        self::assertStringNotContainsString('PRIVATE_INVALID', $result['stdout']);
    }
}
