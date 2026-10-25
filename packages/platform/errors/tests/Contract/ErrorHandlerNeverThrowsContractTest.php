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
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ErrorHandlerNeverThrowsContractTest extends TestCase
{
    public function testServiceResolutionFailureDoesNotPreventLaterMapperFromSelectingDescriptor(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'missing.mapper', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'valid.mapper', 10);
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->expects(self::once())->method('map')->willReturn(self::approved());
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturnCallback(
            static fn (string $id): mixed => $id === 'valid.mapper'
                ? $mapper
                : throw new \RuntimeException('PRIVATE_RESOLUTION_FAILURE'),
        );

        self::assertSame(
            self::approved()->toArray(),
            $this->handler($tags, $container)->handle(self::raw())->toArray(),
        );
    }

    public function testMapperExecutionFailureDoesNotPreventLaterMapperFromSelectingDescriptor(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'throwing.mapper', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'valid.mapper', 10);
        $bad = $this->createMock(ExceptionMapperInterface::class);
        $bad->expects(self::once())->method('map')->willThrowException(new \RuntimeException('PRIVATE_MAPPER_FAILURE'));
        $good = $this->createMock(ExceptionMapperInterface::class);
        $good->expects(self::once())->method('map')->willReturn(self::approved());
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturnCallback(
            static fn (string $id): mixed => $id === 'throwing.mapper' ? $bad : $good,
        );

        self::assertSame(
            self::approved()->toArray(),
            $this->handler($tags, $container)->handle(self::raw())->toArray(),
        );
    }

    public function testMalformedRedactionAndReconstructionFailuresSelectFixedFallbackWithoutRetry(): void
    {
        foreach ([null, ['message' => ['redacted' => true]], ['unexpected' => 'field']] as $output) {
            $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
            $redactor->expects(self::once())->method('redactJsonLike')->willReturn($output);

            self::assertFallback($this->handlerWithApprovedMapper(redactor: $redactor)->handle(self::raw()));
        }
    }

    public function testRedactorExceptionSelectsFallbackWithoutRecursiveRedaction(): void
    {
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::once())->method('redactJsonLike')
            ->willThrowException(new \RuntimeException('PRIVATE_REDACTION_FAILURE'));

        self::assertFallback($this->handlerWithApprovedMapper(redactor: $redactor)->handle(self::raw()));
    }

    public function testRegistryLevelEnumerationFailureUsesOuterThrowableBoundaryWithoutRetry(): void
    {
        $tags = new TagRegistry();
        // Corrupt only this isolated test instance: Foundation production code stays unchanged.
        // TagRegistry::all() must throw before any service can be resolved.
        new \ReflectionProperty(TagRegistry::class, 'servicesByTag')->setValue($tags, [
            ReservedTags::ERROR_MAPPER => ['invalid' => new \stdClass()],
        ]);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::once())->method('redactJsonLike')->willReturnArgument(0);

        self::assertFallback($this->handler($tags, $container, redactor: $redactor)->handle(self::raw()));
    }

    public function testCorrelationProviderFailureDoesNotChangeSelectedDescriptor(): void
    {
        $correlations = $this->createMock(CorrelationIdProviderInterface::class);
        $correlations->expects(self::once())->method('correlationId')
            ->willThrowException(new \RuntimeException('PRIVATE_CORRELATION_FAILURE'));

        self::assertSame(
            self::approved()->toArray(),
            $this->handlerWithApprovedMapper(correlations: $correlations)->handle(self::raw())->toArray(),
        );
    }

    public function testOptionalReporterTracerMeterAndLoggerFailuresDoNotReplaceSelectedDescriptor(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'invalid.mapper', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'valid.mapper', 10);
        $mapper = $this->createStub(ExceptionMapperInterface::class);
        $mapper->method('map')->willReturn(self::approved());
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): mixed => $id === 'valid.mapper' ? $mapper : new \stdClass(),
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->willThrowException(new \RuntimeException('PRIVATE_LOG_FAILURE'));
        $reporter = $this->createMock(ErrorReporterPortInterface::class);
        $reporter->expects(self::once())->method('report')
            ->willThrowException(new \RuntimeException('PRIVATE_REPORT_FAILURE'));
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')
            ->willThrowException(new \RuntimeException('PRIVATE_TRACE_FAILURE'));
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')
            ->willThrowException(new \RuntimeException('PRIVATE_COUNTER_FAILURE'));
        $meter->expects(self::once())->method('observe')
            ->willThrowException(new \RuntimeException('PRIVATE_DURATION_FAILURE'));

        self::assertSame(
            self::approved()->toArray(),
            $this->handler(
                tags: $tags,
                container: $container,
                reporter: $reporter,
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
                reportingEnabled: true,
                mapperFailureLoggingEnabled: true,
            )->handle(self::raw())->toArray(),
        );
    }

    public function testFailedSpanFinalizationStillReturnsSelectedDescriptorAndEndsSpanOnce(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::once())->method('setAttributes')
            ->willThrowException(new \RuntimeException('PRIVATE_SPAN_ATTRIBUTES'));
        $span->expects(self::once())->method('end')
            ->willThrowException(new \RuntimeException('PRIVATE_SPAN_END'));
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')->willReturn($span);

        self::assertSame(
            self::approved()->toArray(),
            $this->handlerWithApprovedMapper(tracer: $tracer, reportingEnabled: true)->handle(self::raw())->toArray(),
        );
    }

    private static function raw(): \RuntimeException
    {
        return new \RuntimeException('SYNTHETIC_THROWABLE_SECRET');
    }

    private static function approved(): ErrorDescriptor
    {
        return new ErrorDescriptor('OWNER_APPROVED_ERROR', 'Operation failed.');
    }

    private static function assertFallback(ErrorDescriptor $descriptor): void
    {
        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
        self::assertSame('Unhandled exception.', $descriptor->message());
        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertSame([], $descriptor->extensions());
        self::assertStringNotContainsString('PRIVATE', \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR));
    }

    private function identityRedactor(): SensitiveDataRedactorInterface
    {
        $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
        $redactor->method('redactJsonLike')->willReturnArgument(0);

        return $redactor;
    }

    private function handlerWithApprovedMapper(
        ?SensitiveDataRedactorInterface $redactor = null,
        ?CorrelationIdProviderInterface $correlations = null,
        ?TracerPortInterface $tracer = null,
        bool $reportingEnabled = false,
    ): ErrorHandler {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'approved.mapper');
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->expects(self::once())->method('map')->willReturn(self::approved());
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->willReturn($mapper);

        return $this->handler(
            $tags,
            $container,
            $redactor,
            correlations: $correlations,
            tracer: $tracer,
            reportingEnabled: $reportingEnabled,
        );
    }

    private function handler(
        TagRegistry $tags,
        ContainerInterface $container,
        ?SensitiveDataRedactorInterface $redactor = null,
        ?ErrorReporterPortInterface $reporter = null,
        ?CorrelationIdProviderInterface $correlations = null,
        ?TracerPortInterface $tracer = null,
        ?MeterPortInterface $meter = null,
        ?LoggerInterface $logger = null,
        bool $reportingEnabled = false,
        bool $mapperFailureLoggingEnabled = false,
    ): ErrorHandler {
        $redactor ??= $this->identityRedactor();
        $reporter ??= $this->createStub(ErrorReporterPortInterface::class);
        $correlations ??= $this->createStub(CorrelationIdProviderInterface::class);
        $tracer ??= $this->createStub(TracerPortInterface::class);
        $meter ??= $this->createStub(MeterPortInterface::class);
        $logger ??= new NullLogger();

        return new ErrorHandler(
            new ExceptionMapperRegistry($tags, $container, $logger, $reportingEnabled, $mapperFailureLoggingEnabled),
            new DefaultExceptionMapper(),
            $redactor,
            $reporter,
            $correlations,
            $tracer,
            $meter,
            new Stopwatch(),
            $reportingEnabled,
            ErrorSeverity::Info,
            [],
            10000,
        );
    }
}
