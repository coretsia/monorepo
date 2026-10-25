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

namespace Coretsia\Platform\Errors\Tests\Unit;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
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
use Psr\Log\NullLogger;

final class ErrorHandlerFallbackTest extends TestCase
{
    public function testEmptyRegistrySelectsTheCanonicalSafeFallback(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');

        $handler = $this->handler(new TagRegistry(), $container);
        self::assertSame(self::fallback(), $handler->handle(self::rawThrowable())->toArray());
    }

    public function testAllNullRegistryExhaustsEveryCandidateBeforeFallback(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'first', 20);
        $tags->add(ReservedTags::ERROR_MAPPER, 'second', 10);
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->expects(self::exactly(2))->method('map')->willReturn(null);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturn($mapper);

        self::assertSame(self::fallback(), $this->handler($tags, $container)->handle(self::rawThrowable())->toArray());
    }

    public function testThrowingMapperCannotReplaceTheFixedFallbackOrLeakItsException(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'throwing-mapper');
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->expects(self::once())
            ->method('map')
            ->willThrowException(new \RuntimeException('SYNTHETIC_MAPPER_PRIVATE'));
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($mapper);

        $descriptor = $this->handler($tags, $container)->handle(self::rawThrowable());

        self::assertSame(self::fallback(), $descriptor->toArray());
        self::assertStringNotContainsString(
            'SYNTHETIC_MAPPER_PRIVATE',
            \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR),
        );
    }

    public function testInvalidMapperServiceIsSkippedWithoutAnotherFallbackRegistry(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'invalid-mapper');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->willReturn(new \stdClass());

        self::assertSame(self::fallback(), $this->handler($tags, $container)->handle(self::rawThrowable())->toArray());
    }

    public function testInvalidMapperReturnTypeIsIsolatedAsACandidateFailure(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'invalid-return');
        $mapper = $this->createStub(ExceptionMapperInterface::class);
        $mapper->method('map')->willReturnCallback(static fn (): string => 'SYNTHETIC_INVALID_RESULT');
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($mapper);

        $descriptor = $this->handler($tags, $container)->handle(self::rawThrowable());

        self::assertSame(self::fallback(), $descriptor->toArray());
        self::assertStringNotContainsString(
            'SYNTHETIC_INVALID_RESULT',
            \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string,mixed> */
    private static function fallback(): array
    {
        return [
            'code' => 'CORETSIA_UNHANDLED_EXCEPTION',
            'extensions' => [],
            'httpStatus' => null,
            'message' => 'Unhandled exception.',
            'schemaVersion' => ErrorDescriptor::SCHEMA_VERSION,
            'severity' => 'error',
        ];
    }

    private static function rawThrowable(): \RuntimeException
    {
        return new \RuntimeException('SYNTHETIC_RAW_THROWABLE_PRIVATE');
    }

    private function handler(TagRegistry $tags, ContainerInterface $container): ErrorHandler
    {
        $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
        $redactor->method('redactJsonLike')->willReturnArgument(0);
        $correlations = $this->createStub(CorrelationIdProviderInterface::class);
        $correlations->method('correlationId')->willReturn(null);

        return new ErrorHandler(
            new ExceptionMapperRegistry($tags, $container, new NullLogger(), false, false),
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
    }
}
