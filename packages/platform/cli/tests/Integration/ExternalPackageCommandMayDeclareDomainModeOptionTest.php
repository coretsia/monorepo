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

use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\ExternalOwnerCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\ExternalPackageCliFixture;

final class ExternalPackageCommandMayDeclareDomainModeOptionTest extends CliIntegrationTestCase
{
    public function testDomainModePassesUnchangedAndDoesNotSelectKernelPreset(): void
    {
        $fixture = new ExternalPackageCliFixture();
        self::assertSame([
            [
                'name' => 'mode',
                'repeatable' => false,
                'summary' => 'Owner domain mode.',
                'value' => 'required',
            ],
        ], $fixture->catalog->require(ExternalOwnerCommand::NAME)->options());

        $result = $fixture->cli->run([
            ExternalOwnerCommand::NAME,
            '--mode=owner-special',
            '--format=json',
            '--color=never',
        ]);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('owner-special', ExternalOwnerCommand::$receivedMode);
        self::assertSame(['mode' => 'owner-special'], ExternalOwnerCommand::$receivedOptions);
        self::assertSame(1, $fixture->cli->runtime->calls);
        self::assertSame([
            'operation' => ExternalOwnerCommand::NAME,
            'output_format' => 'json',
        ], $fixture->cli->runtime->invocations[0]['attributes']);
        self::assertArrayNotHasKey('preset', $fixture->cli->runtime->invocations[0]['attributes']);
    }
}
