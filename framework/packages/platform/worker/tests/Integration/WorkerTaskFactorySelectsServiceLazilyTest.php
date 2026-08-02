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

namespace Coretsia\Platform\Worker\Tests\Integration;

use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;

final class WorkerTaskFactorySelectsServiceLazilyTest extends PackageTestCase
{
    public function testFactoryResolvesOnlyTheSelectedTaskFactoryFromContainer(): void
    {
        $source = self::source('src/Provider/WorkerServiceFactory.php');
        self::assertStringContainsString("TASK_TYPE_QUEUE => QueueTaskFactory::class", $source);
        self::assertStringContainsString("TASK_TYPE_HTTP => HttpTaskFactory::class", $source);
        self::assertStringContainsString('$container->get($serviceId)', $source);
    }
}
