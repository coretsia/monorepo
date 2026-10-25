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

namespace Coretsia\Platform\Errors\Tests\Contract;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testFoundationNoopReporterLoggerTracerAndMeterAcceptCanonicalOperations(): void
    {
        $descriptor = new ErrorDescriptor('OWNER_APPROVED_ERROR', 'Operation failed.');
        new NoopErrorReporter()->report($descriptor);
        $logger = new NoopLogger();
        $logger->warning('error.mapper_failed', ['operation' => 'map', 'reason' => 'mapper_failed']);
        $meter = new NoopMeter();
        $meter->increment('error.map_total', 1, ['operation' => 'map', 'outcome' => 'ok']);
        $meter->observe('error.map_duration_ms', 0, ['operation' => 'map', 'outcome' => 'ok']);
        $tracer = new NoopTracer();
        $span = $tracer->startSpan('error.map', ['operation' => 'map']);
        $span->setAttributes(['operation' => 'map', 'outcome' => 'ok']);
        $span->end();
        self::assertSame('noop', $span->name());
        self::assertSame('completed', $tracer->inSpan('error.map', static fn (): string => 'completed'));
    }

    public function testCanonicalHandlerWorksWithAllFoundationNoopObservabilityBindings(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'failed.mapper', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'approved.mapper', 10);
        $failed = $this->createStub(ExceptionMapperInterface::class);
        $failed->method('map')->willThrowException(new \RuntimeException('PRIVATE_MAPPER_ERROR'));
        $approved = $this->createStub(ExceptionMapperInterface::class);
        $approved->method('map')->willReturn(new ErrorDescriptor('OWNER_APPROVED_ERROR', 'Operation failed.'));
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): mixed => $id === 'failed.mapper' ? $failed : $approved,
        );
        $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
        $redactor->method('redactJsonLike')->willReturnArgument(0);
        $correlations = $this->createStub(CorrelationIdProviderInterface::class);
        $correlations->method('correlationId')->willReturn(null);

        $handler = new ErrorHandler(
            new ExceptionMapperRegistry($tags, $container, new NoopLogger(), true, true),
            new DefaultExceptionMapper(),
            $redactor,
            new NoopErrorReporter(),
            $correlations,
            new NoopTracer(),
            new NoopMeter(),
            new Stopwatch(),
            true,
            ErrorSeverity::Info,
            [],
            10000,
        );

        for ($invocation = 0; $invocation < 2; ++$invocation) {
            $descriptor = $handler->handle(new \RuntimeException('PRIVATE_THROWABLE_ERROR'));
            self::assertSame('OWNER_APPROVED_ERROR', $descriptor->code());
            self::assertSame('Operation failed.', $descriptor->message());
        }
    }
}
