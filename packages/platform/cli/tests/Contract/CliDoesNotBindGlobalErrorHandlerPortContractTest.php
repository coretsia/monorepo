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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Diagnostics\CliErrorHandler;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use PHPUnit\Framework\TestCase;

final class CliDoesNotBindGlobalErrorHandlerPortContractTest extends TestCase
{
    public function testCliOwnsConcreteHandlerWithoutClaimingGlobalErrorHandlerPort(): void
    {
        self::assertTrue(\is_subclass_of(CliErrorHandler::class, ErrorHandlerInterface::class));
        $definitions = new ContainerDefinitionBuilder();
        new CliServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        $stream = $definitions->build()->toDescriptorStream();
        $services = [];
        foreach ($stream as $operation) {
            if (isset($operation['id'])) {
                $services[$operation['id']] = $operation;
            }
            self::assertNotSame(ErrorHandlerInterface::class, $operation['alias'] ?? null);
            self::assertNotSame(ErrorHandlerInterface::class, $operation['serviceId'] ?? null);
        }
        self::assertArrayHasKey(CliErrorHandler::class, $services);
        self::assertArrayHasKey(CliApplication::class, $services);
        self::assertArrayNotHasKey(ErrorHandlerInterface::class, $services);
        self::assertNotContains(ErrorHandlerInterface::class, $definitions->build()->requiredServiceIds());
        self::assertContains(
            ['id' => CliErrorHandler::class, 'type' => 'service'],
            $services[CliApplication::class]['arguments'],
        );
    }

    public function testSourceProviderDoesNotIntroduceGlobalHandlerAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(new CliServiceProvider());
        self::assertFalse($builder->build()->has(ErrorHandlerInterface::class));
        self::assertTrue($builder->build()->has(CliErrorHandler::class));
    }
}
