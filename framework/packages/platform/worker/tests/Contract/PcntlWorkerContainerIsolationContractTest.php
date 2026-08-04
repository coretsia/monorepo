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

namespace Coretsia\Platform\Worker\Tests\Contract;

use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;

final class PcntlWorkerContainerIsolationContractTest extends PackageTestCase
{
    public function testPcntlDriverUsesForkDetachExecWithoutContainerCapture(): void
    {
        $driver = self::source('src/Process/Driver/PcntlWorkerProcessDriver.php');
        $factory = self::source('src/Provider/WorkerServiceFactory.php');

        $driverCode = \preg_replace(
            '/\/\*.*?\*\/|\/\/[^\n]*/s',
            '',
            $driver,
        ) ?? $driver;

        self::assertStringContainsString('pcntl_exec', $driverCode);
        self::assertStringNotContainsString('ContainerInterface', $driverCode);
        self::assertStringNotContainsString('ApplicationWorker', $driverCode);
        self::assertStringNotContainsString('childBootstrap', $driverCode);

        $factoryMethod = \strstr(
            $factory,
            'public function pcntlWorkerProcessDriver',
        );
        self::assertIsString($factoryMethod);
        $factoryMethod = \strstr(
            $factoryMethod,
            'public function procWorkerProcessDriver',
            true,
        );
        self::assertIsString($factoryMethod);

        self::assertStringNotContainsString('ContainerInterface $container', $factoryMethod);
        self::assertStringNotContainsString('use ($container)', $factoryMethod);
        self::assertStringNotContainsString('ApplicationWorker::class', $factoryMethod);

        $childBranch = \strstr(
            $driverCode,
            'if ($pid === 0)',
        );
        self::assertIsString($childBranch);

        $close = \strpos($childBranch, '$readinessEndpoint->close()');
        $detach = \strpos($childBranch, 'prepareForkedChild()');
        $exec = \strpos($childBranch, '@\pcntl_exec(');

        self::assertNotFalse($close);
        self::assertNotFalse($detach);
        self::assertNotFalse($exec);
        self::assertTrue($close < $detach && $detach < $exec);
    }
}
