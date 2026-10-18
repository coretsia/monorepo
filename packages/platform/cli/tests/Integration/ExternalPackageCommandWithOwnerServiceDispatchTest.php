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

use Coretsia\Platform\Cli\Tests\Integration\Support\ExternalOwnerCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\ExternalOwnerService;
use Coretsia\Platform\Cli\Tests\Integration\Support\ExternalPackageCliFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\TaggedCommandApplicationFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkerComposedTestCase;
use Coretsia\Platform\Worker\Console\WorkerStartCommand;
use Coretsia\Platform\Worker\Provider\WorkerServiceFactory;

final class ExternalPackageCommandWithOwnerServiceDispatchTest extends WorkerComposedTestCase
{
    public function testProviderTaggedCommandResolvesOwnerServiceOnlyOnGenericDispatch(): void
    {
        $fixture = new ExternalPackageCliFixture();
        $descriptor = $fixture->catalog->require(ExternalOwnerCommand::NAME);
        self::assertSame(ExternalOwnerCommand::class, $descriptor->serviceId());
        self::assertSame(0, ExternalOwnerCommand::$constructed);
        self::assertSame(0, ExternalOwnerCommand::$executed);
        $result = $fixture->cli->run([
            ExternalOwnerCommand::NAME,
            '--mode=domain-value',
            '--format=json',
        ]);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(1, ExternalOwnerCommand::$constructed);
        self::assertSame(1, ExternalOwnerCommand::$executed);
        self::assertSame(1, $fixture->cli->runtime->calls);
        self::assertSame(
            ['mode' => 'domain-value', 'owner_service' => 'called'],
            TaggedCommandApplicationFixture::document($result)['data']['records'][0]['payload'],
        );
        self::assertSame(1, $fixture->container->get(ExternalOwnerService::class)->calls);
        self::assertNoOwnerImportsInProduction();
    }

    public function testEnabledWorkerModuleContributesAndResolvesOwnerServices(): void
    {
        $root = self::workerConsumer();
        try {
            [$plan, $catalog, $registry, $host] = self::composedWorkerHost($root);
            self::assertTrue($plan->hasEnabledModule('platform.worker'));
            self::assertWorkerTags($catalog, $registry);
            self::assertSame(
                WorkerStartCommand::class,
                \get_class($host->get(WorkerStartCommand::class)),
            );
            self::assertSame(
                WorkerServiceFactory::class,
                \get_class($host->get(WorkerServiceFactory::class)),
            );
            self::assertNoWorkerImportsInCli();
        } finally {
            self::removeTree($root);
        }
    }

    private static function assertNoOwnerImportsInProduction(): void
    {
        $source = self::packageRoot() . '/src';
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            ) as $file
        ) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            self::assertStringNotContainsString(
                'Tests\\Integration\\Support\\ExternalOwner',
                (string) \file_get_contents($file->getPathname()),
            );
        }
    }
}
