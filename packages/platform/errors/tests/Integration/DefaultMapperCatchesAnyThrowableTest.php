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

final class DefaultMapperCatchesAnyThrowableTest extends TestCase
{
    public function testOtherwiseUnhandledThrowableTypesAllReceiveTheSameCanonicalSafeFallback(): void
    {
        $previous = new \RuntimeException('SYNTHETIC_PREVIOUS_PRIVATE');
        $throwables = [
            new \RuntimeException('SYNTHETIC_RUNTIME_PRIVATE', previous: $previous),
            new \LogicException('SYNTHETIC_LOGIC_PRIVATE'),
            new \TypeError('SYNTHETIC_TYPE_PRIVATE'),
            new \Error('SYNTHETIC_ENGINE_PRIVATE'),
            new \ParseError('SYNTHETIC_PARSE_PRIVATE'),
        ];
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::exactly(\count($throwables)))
            ->method('redactJsonLike')->willReturnArgument(0);
        $handler = $this->handler(new TagRegistry(), $container, $redactor);
        $expected = [
            'code' => 'CORETSIA_UNHANDLED_EXCEPTION',
            'extensions' => [],
            'httpStatus' => null,
            'message' => 'Unhandled exception.',
            'schemaVersion' => ErrorDescriptor::SCHEMA_VERSION,
            'severity' => ErrorSeverity::Error->value,
        ];

        foreach ($throwables as $throwable) {
            $descriptor = $handler->handle($throwable);
            self::assertSame($expected, $descriptor->toArray());
            self::assertSame($expected, ErrorDescriptor::fromArray($descriptor->toArray())->toArray());
            $serialized = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);
            foreach ([$throwable->getMessage(), $previous->getMessage(), __FILE__] as $private) {
                self::assertStringNotContainsString($private, $serialized);
            }
        }
    }

    public function testNullRegisteredMapperFallsThroughToTheDefaultMapperWithoutTaggingTheFallback(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'application.null-mapper');
        $registered = $this->createMock(ExceptionMapperInterface::class);
        $registered->expects(self::once())->method('map')->willReturn(null);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with('application.null-mapper')->willReturn($registered);
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::once())->method('redactJsonLike')->willReturnArgument(0);

        self::assertSame(
            ['application.null-mapper'],
            \array_map(
                static fn ($tagged): string => $tagged->id(),
                $tags->all(ReservedTags::ERROR_MAPPER),
            ),
        );
        $descriptor = $this->handler($tags, $container, $redactor)->handle(
            new \Error('SYNTHETIC_UNHANDLED_PRIVATE'),
        );
        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
        self::assertSame('Unhandled exception.', $descriptor->message());
        self::assertSame([], $descriptor->extensions());
    }

    private function handler(
        TagRegistry $tags,
        ContainerInterface $container,
        SensitiveDataRedactorInterface $redactor,
    ): ErrorHandler {
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
