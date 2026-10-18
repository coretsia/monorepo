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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Worker\Console\WorkerHealthCommand;
use Coretsia\Platform\Worker\Console\WorkerStartCommand;
use Coretsia\Platform\Worker\Console\WorkerStatusCommand;
use Coretsia\Platform\Worker\Console\WorkerStopCommand;

/** Real canonical source operations host with Worker explicitly enabled for Console. */
abstract class WorkerComposedTestCase extends CliIntegrationTestCase
{
    /** @return list<class-string> */
    protected static function workerCommands(): array
    {
        return [
            WorkerStartCommand::class,
            WorkerStopCommand::class,
            WorkerStatusCommand::class,
            WorkerHealthCommand::class,
        ];
    }

    protected static function workerConsumer(): string
    {
        $root = self::consumer();
        self::write(
            $root . '/config/app.php',
            '<?php return ' . \var_export([
                'moduleOverrides' => [
                    'console' => ['include' => ['platform.worker'], 'exclude' => []],
                ],
            ], true) . ';' . "\n",
        );
        self::write(
            $root . '/config/worker.php',
            '<?php return ' . \var_export([
                'driver' => 'proc',
                'control' => ['transport' => 'tcp'],
            ], true) . ';' . "\n",
        );
        return $root;
    }

    /** @return array{0:ModulePlan,1:CommandCatalog,2:TagRegistry,3:object} */
    protected static function composedWorkerHost(string $root): array
    {
        $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
        $plan = $host->get(ModulePlan::class);
        self::assertTrue($plan->hasEnabledModule('platform.cli'));
        self::assertTrue($plan->hasEnabledModule('platform.worker'));
        self::assertSame('console', $plan->app());
        $catalog = $host->get(CommandCatalog::class);
        $registry = $host->get(TagRegistry::class);
        return [$plan, $catalog, $registry, $host];
    }

    protected static function assertWorkerTags(CommandCatalog $catalog, TagRegistry $registry): void
    {
        $tagged = $registry->all(ReservedTags::CLI_COMMAND);
        $ids = \array_map(static fn ($service): string => $service->id(), $tagged);
        foreach (self::workerCommands() as $command) {
            self::assertContains($command, $ids);
            self::assertSame($command, $catalog->require($command::NAME)->serviceId());
            self::assertSame($command::NAME, $catalog->require($command::NAME)->name());
        }
    }

    protected static function assertNoWorkerImportsInCli(): void
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
            $php = (string) \file_get_contents($file->getPathname());
            self::assertStringNotContainsString('Coretsia\\Platform\\Worker\\', $php, $file->getFilename());
        }
    }
}
