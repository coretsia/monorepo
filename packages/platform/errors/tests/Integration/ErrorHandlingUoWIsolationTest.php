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

namespace Coretsia\Platform\Errors\Tests\Integration;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ErrorHandlingUoWIsolationTest extends TestCase
{
    public function testHandlingInsideAnExistingKernelUnitOfWorkDoesNotStartNestedWorkOrResetState(): void
    {
        $store = new ContextStore();
        $tags = new TagRegistry();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $idGenerator = new UlidGenerator();
        $correlations = new CorrelationIdProvider($store);
        $runtime = new KernelRuntime(
            $store,
            new ResetOrchestrator($container, $tags),
            new Stopwatch(),
            $idGenerator,
            $correlations,
            new CorrelationIdGenerator($idGenerator),
            new HookInvoker($container, $tags),
            new NoopLogger(),
            new NoopTracer(),
            new NoopMeter(),
            8,
            64,
        );
        $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
        $redactor->method('redactJsonLike')->willReturnArgument(0);
        $handler = new ErrorHandler(
            new ExceptionMapperRegistry($tags, $container, new NoopLogger(), false, false),
            new DefaultExceptionMapper(),
            $redactor,
            new NoopErrorReporter(),
            $correlations,
            new NoopTracer(),
            new NoopMeter(),
            new Stopwatch(),
            false,
            ErrorSeverity::Info,
            [],
            10000,
        );
        $snapshots = [];
        $result = $runtime->runUnitOfWork(
            UnitOfWorkType::CLI,
            static function () use ($handler, $store, &$snapshots): string {
                $before = $store->all();
                $snapshots[] = $before;
                $descriptor = $handler->handle(new \RuntimeException('PRIVATE'));
                $snapshots[] = $store->all();
                self::assertSame($before, $store->all());
                self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
                return $descriptor->message();
            },
        );
        self::assertSame('Unhandled exception.', $result);
        self::assertSame($snapshots[0], $snapshots[1]);
        self::assertNotEmpty($snapshots[0]);
        // Only the Kernel owns reset; this empty reset registry does not clear the context.
        self::assertSame($snapshots[1], $store->all());
    }

    public function testRepeatedHandlesRetainNoInvocationStateAndProviderAddsNoResetTags(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'owner.mapper');
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->expects(self::exactly(2))->method('map')->willReturnOnConsecutiveCalls(
            new ErrorDescriptor('FIRST_ERROR', 'First public reason.'),
            new ErrorDescriptor('SECOND_ERROR', 'Second public reason.'),
        );
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturn($mapper);
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::exactly(2))->method('redactJsonLike')->willReturnArgument(0);
        $handler = new ErrorHandler(
            new ExceptionMapperRegistry($tags, $container, new NoopLogger(), false, false),
            new DefaultExceptionMapper(),
            $redactor,
            new NoopErrorReporter(),
            new CorrelationIdProvider(new ContextStore()),
            new NoopTracer(),
            new NoopMeter(),
            new Stopwatch(),
            false,
            ErrorSeverity::Info,
            [],
            10000,
        );
        self::assertSame('FIRST_ERROR', $handler->handle(new \RuntimeException('PRIVATE_ONE'))->code());
        self::assertSame('SECOND_ERROR', $handler->handle(new \RuntimeException('PRIVATE_TWO'))->code());
        self::assertTrue(new \ReflectionClass($handler)->isReadOnly());
        self::assertTrue(new \ReflectionClass(ExceptionMapperRegistry::class)->isReadOnly());
        $source = \file_get_contents(new \ReflectionClass(ErrorHandler::class)->getFileName());
        self::assertIsString($source);
        self::assertStringNotContainsString('beginUnitOfWork(', $source);
        self::assertStringNotContainsString('runUnitOfWork(', $source);
        self::assertStringNotContainsString('resetAll(', $source);
        $providerSource = \file_get_contents(new \ReflectionClass(ErrorsServiceProvider::class)->getFileName());
        self::assertIsString($providerSource);
        self::assertStringNotContainsString('ReservedTags::KERNEL_RESET', $providerSource);
        self::assertStringNotContainsString('ReservedTags::KERNEL_STATEFUL', $providerSource);
        self::assertCount(0, $tags->all(ReservedTags::KERNEL_RESET));
    }
}
