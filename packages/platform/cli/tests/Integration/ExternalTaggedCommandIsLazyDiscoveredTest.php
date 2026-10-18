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
use Coretsia\Platform\Cli\Tests\Integration\Support\TaggedCommandApplicationFixture;

final class ExternalTaggedCommandIsLazyDiscoveredTest extends CliIntegrationTestCase
{
    public function testCatalogListAndHelpNeverConstructExternalService(): void
    {
        $fixture = new ExternalPackageCliFixture();
        self::assertSame(ExternalOwnerCommand::NAME, $fixture->catalog->require(ExternalOwnerCommand::NAME)->name());
        self::assertSame(0, ExternalOwnerCommand::$constructed);
        $listing = $fixture->cli->run(['list', '--format=json']);
        self::assertSame(0, $listing['exit'], $listing['stderr']);
        self::assertContains(
            ExternalOwnerCommand::NAME,
            \array_column(
                TaggedCommandApplicationFixture::document(
                    $listing,
                )['data']['records'][0]['payload']['groups']['external'],
                'name',
            ),
        );
        $help = $fixture->cli->run(['help', ExternalOwnerCommand::NAME, '--format=json']);
        self::assertSame(0, $help['exit'], $help['stderr']);
        self::assertSame(
            ExternalOwnerCommand::NAME,
            TaggedCommandApplicationFixture::document($help)['data']['records'][0]['payload']['name'],
        );
        self::assertSame(0, ExternalOwnerCommand::$constructed);
        self::assertSame(0, ExternalOwnerCommand::$executed);

        $dispatched = $fixture->cli->run([ExternalOwnerCommand::NAME, '--format=json']);
        self::assertSame(0, $dispatched['exit'], $dispatched['stderr']);
        self::assertSame(1, ExternalOwnerCommand::$constructed);
        self::assertSame(1, ExternalOwnerCommand::$executed);
    }
}
