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

final class MapperFailureIsolationTest extends TestCase
{
    public function testMalformedAndThrowingCandidatesDoNotPreventAValidLaterMapperFromWinning(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.invalid-metadata', 80, ['unsupported' => true]);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.unresolvable', 70);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.invalid-service', 60);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.invalid-return', 50);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.throwing', 40);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.null', 30);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.valid', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.unreachable', 10);

        $invalidReturn = $this->createMock(ExceptionMapperInterface::class);
        $invalidReturn->expects(self::once())->method('map')->willReturnCallback(
            static fn (): string => 'SYNTHETIC_INVALID_MAPPER_RESULT',
        );
        $throwing = $this->createMock(ExceptionMapperInterface::class);
        $throwing->expects(self::once())->method('map')
            ->willThrowException(new \RuntimeException('SYNTHETIC_PRIVATE_MAPPER_FAILURE'));
        $nullMapper = $this->createMock(ExceptionMapperInterface::class);
        $nullMapper->expects(self::once())->method('map')->willReturn(null);
        $expected = new ErrorDescriptor('OWNER_APPROVED_ERROR', 'Operation failed.');
        $valid = $this->createMock(ExceptionMapperInterface::class);
        $valid->expects(self::once())->method('map')->willReturn($expected);

        $resolved = [];
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(6))->method('get')->willReturnCallback(
            static function (string $id) use (&$resolved, $invalidReturn, $throwing, $nullMapper, $valid): mixed {
                $resolved[] = $id;

                return match ($id) {
                    'mapper.unresolvable' => throw new \RuntimeException('SYNTHETIC_PRIVATE_RESOLUTION_FAILURE'),
                    'mapper.invalid-service' => new \stdClass(),
                    'mapper.invalid-return' => $invalidReturn,
                    'mapper.throwing' => $throwing,
                    'mapper.null' => $nullMapper,
                    'mapper.valid' => $valid,
                    default => throw new \LogicException('Unreachable mapper must not resolve.'),
                };
            },
        );

        $diagnostics = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(5))->method('warning')->willReturnCallback(
            static function (string $event, array $context) use (&$diagnostics): void {
                $diagnostics[] = [$event, $context];
            },
        );

        $registry = new ExceptionMapperRegistry($tags, $container, $logger, true, true);
        self::assertSame($expected, $registry->map(new \RuntimeException('SYNTHETIC_RAW_PRIVATE')));
        self::assertSame([
            'mapper.unresolvable',
            'mapper.invalid-service',
            'mapper.invalid-return',
            'mapper.throwing',
            'mapper.null',
            'mapper.valid',
        ], $resolved);
        self::assertSame([
            ['error.mapper_failed', ['operation' => 'map', 'reason' => 'invalid_metadata']],
            ['error.mapper_failed', ['operation' => 'map', 'reason' => 'service_resolution_failed']],
            ['error.mapper_failed', ['operation' => 'map', 'reason' => 'invalid_service']],
            ['error.mapper_failed', ['operation' => 'map', 'reason' => 'mapper_failed']],
            ['error.mapper_failed', ['operation' => 'map', 'reason' => 'mapper_failed']],
        ], $diagnostics);
    }

    public function testFailedMapperFollowedByAcceptedMapperProducesFinalOutcomeOk(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.failed', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.success', 10);
        $failed = $this->createMock(ExceptionMapperInterface::class);
        $failed->expects(self::once())->method('map')
            ->willThrowException(new \RuntimeException('SYNTHETIC_PRIVATE_MAPPER_FAILURE'));
        $accepted = $this->createMock(ExceptionMapperInterface::class);
        $accepted->expects(self::once())->method('map')
            ->willReturn(new ErrorDescriptor('OWNER_APPROVED_ERROR', 'Operation failed.'));
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturnCallback(
            static fn (string $id): ExceptionMapperInterface => $id === 'mapper.failed' ? $failed : $accepted,
        );

        $descriptor = $this->handlerWithObservedOutcome($tags, $container, 'ok')->handle(
            new \RuntimeException('SYNTHETIC_RAW_PRIVATE'),
        );

        self::assertSame('OWNER_APPROVED_ERROR', $descriptor->code());
        self::assertSame('Operation failed.', $descriptor->message());
    }

    public function testAllMapperCandidatesExhaustedProducesFinalOutcomeFallback(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.failed', 30);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.invalid', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'mapper.null', 10);
        $failed = $this->createMock(ExceptionMapperInterface::class);
        $failed->expects(self::once())->method('map')
            ->willThrowException(new \RuntimeException('SYNTHETIC_PRIVATE_MAPPER_FAILURE'));
        $nullMapper = $this->createMock(ExceptionMapperInterface::class);
        $nullMapper->expects(self::once())->method('map')->willReturn(null);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(3))->method('get')->willReturnCallback(
            static fn (string $id): mixed => match ($id) {
                'mapper.failed' => $failed,
                'mapper.invalid' => new \stdClass(),
                'mapper.null' => $nullMapper,
                default => throw new \LogicException('Unexpected mapper service id.'),
            },
        );

        $descriptor = $this->handlerWithObservedOutcome($tags, $container, 'fallback')->handle(
            new \RuntimeException('SYNTHETIC_RAW_PRIVATE'),
        );

        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
        self::assertSame('Unhandled exception.', $descriptor->message());
        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertSame([], $descriptor->extensions());
    }

    private function handlerWithObservedOutcome(
        TagRegistry $tags,
        ContainerInterface $container,
        string $outcome,
    ): ErrorHandler {
        $labels = ['operation' => 'map', 'outcome' => $outcome];
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::once())->method('increment')->with('error.map_total', 1, $labels);
        $meter->expects(self::once())->method('observe')->with(
            'error.map_duration_ms',
            self::callback(static fn (mixed $duration): bool => \is_int($duration) && $duration >= 0),
            $labels,
        );
        $span = $this->createMock(SpanInterface::class);
        $span->expects(self::once())->method('setAttributes')->with($labels);
        $span->expects(self::once())->method('end');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::once())->method('startSpan')
            ->with('error.map', ['operation' => 'map'])->willReturn($span);
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::once())->method('redactJsonLike')->willReturnArgument(0);
        $correlations = $this->createMock(CorrelationIdProviderInterface::class);
        $correlations->expects(self::once())->method('correlationId')->willReturn(null);
        $reporter = $this->createMock(ErrorReporterPortInterface::class);
        $reporter->expects(self::never())->method('report');

        return new ErrorHandler(
            new ExceptionMapperRegistry($tags, $container, new NullLogger(), true, true),
            new DefaultExceptionMapper(),
            $redactor,
            $reporter,
            $correlations,
            $tracer,
            $meter,
            new Stopwatch(),
            true,
            ErrorSeverity::Critical,
            ['OWNER_APPROVED_ERROR', 'CORETSIA_UNHANDLED_EXCEPTION'],
            0,
        );
    }
}
