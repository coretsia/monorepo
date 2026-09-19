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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Kernel\Provider\KernelServiceFactory;
use PHPUnit\Framework\TestCase;

final class KernelServiceFactoryUnitOfWorkAttributeLimitsContractTest extends TestCase
{
    public function testUnitOfWorkAttributeLimitsRequireExplicitConfiguredValues(): void
    {
        self::assertSame(
            [
                'maxDepth' => 3,
                'maxKeys' => 20,
            ],
            KernelServiceFactory::unitOfWorkAttributeLimits([
                'uow' => [
                    'attributes' => [
                        'max_depth' => 3,
                        'max_keys' => 20,
                    ],
                ],
            ]),
        );
    }

    public function testUnitOfWorkAttributeLimitsRejectMissingMaxDepthWithoutDefault(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('kernel-uow-attributes-max-depth-missing');

        KernelServiceFactory::unitOfWorkAttributeLimits([
            'uow' => [
                'attributes' => [
                    'max_keys' => 200,
                ],
            ],
        ]);
    }

    public function testUnitOfWorkAttributeLimitsRejectMissingMaxKeysWithoutDefault(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('kernel-uow-attributes-max-keys-missing');

        KernelServiceFactory::unitOfWorkAttributeLimits([
            'uow' => [
                'attributes' => [
                    'max_depth' => 10,
                ],
            ],
        ]);
    }

    public function testUnitOfWorkAttributeLimitsRejectMissingAttributesConfigWithoutDefault(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('kernel-uow-attributes-config-invalid');

        KernelServiceFactory::unitOfWorkAttributeLimits([
            'uow' => [],
        ]);
    }
}
