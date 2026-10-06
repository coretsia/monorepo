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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Ops\KernelOpsExecutionServices;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;

final class KernelServiceProviderDoesNotRegisterKernelOpsOutsideOpsHostTest extends TestCase
{
    public function testNormalFoundationAndKernelContainerDoesNotRegisterKernelOps(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());
        $builder->register(
            new FoundationServiceProvider(),
            new KernelServiceProvider(),
        );

        $container = $builder->build();

        $serviceIds = $container->serviceIds();

        foreach (
            [
                KernelOpsExecutionServices::class,
                KernelOpsFacade::class,
                KernelOpsInterface::class,
            ] as $opsId
        ) {
            self::assertNotContains($opsId, $serviceIds);
        }

        self::assertInstanceOf(
            KernelRuntimeInterface::class,
            $container->get(KernelRuntimeInterface::class),
        );
        self::assertInstanceOf(
            ModuleResolutionOrchestrator::class,
            $container->get(ModuleResolutionOrchestrator::class),
        );

        $kernelProvider = \file_get_contents(__DIR__ . '/../../src/Provider/KernelServiceProvider.php');
        $hostBooter = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsHostBooter.php');

        self::assertIsString($kernelProvider);
        self::assertIsString($hostBooter);

        foreach ([KernelOpsExecutionServices::class, KernelOpsFacade::class, KernelOpsInterface::class] as $opsId) {
            self::assertStringNotContainsString($opsId, $kernelProvider);
        }

        foreach (
            [
                'KernelOpsExecutionServices::class',
                'KernelOpsFacade::class',
                'KernelOpsInterface::class',
            ] as $opsId
        ) {
            self::assertStringContainsString($opsId, $hostBooter);
        }
    }

    private static function validConfig(): array
    {
        $kernel = require \dirname(__DIR__, 2) . '/config/kernel.php';
        $foundation = require \dirname(__DIR__, 3) . '/foundation/config/foundation.php';

        self::assertIsArray($kernel);
        self::assertIsArray($foundation);

        return [
            'foundation' => $foundation,
            'kernel' => $kernel,
        ];
    }
}
