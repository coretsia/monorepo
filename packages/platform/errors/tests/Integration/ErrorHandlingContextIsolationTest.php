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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
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

final class ErrorHandlingContextIsolationTest extends TestCase
{
    public function testCanonicalCorrelationProviderSnapshotsSuccessAndMissingValueWithoutWritingContext(): void
    {
        $valid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        foreach ([$valid, null] as $value) {
            $store = new ContextStore();
            if ($value !== null) {
                $store->set(ContextKeys::CORRELATION_ID, $value);
            }
            $before = $store->all();
            $mapping = [];
            $reporting = [];
            $handler = $this->handler(
                new CorrelationIdProvider($store),
                $mapping,
                $reporting,
            );
            self::assertSame('OWNER_APPROVED_ERROR', $handler->handle(new \RuntimeException('PRIVATE'))->code());
            self::assertCount(1, $mapping);
            self::assertCount(1, $reporting);
            self::assertSame(
                ['correlationId' => $value, 'metadata' => [], 'operation' => 'map'],
                $mapping[0]->toArray(),
            );
            self::assertSame(
                ['correlationId' => $value, 'metadata' => [], 'operation' => 'map'],
                $reporting[0]->toArray(),
            );
            self::assertSame($before, $store->all());
        }
    }

    public function testFailingOrMalformedCorrelationProviderCannotChangeDescriptorOrReportingContext(): void
    {
        foreach (['throws', 'malformed'] as $case) {
            $provider = $this->createMock(CorrelationIdProviderInterface::class);
            $provider->expects(self::once())->method('correlationId')
                ->willReturnCallback(static function () use ($case): ?string {
                    if ($case === 'throws') {
                        throw new \RuntimeException('SYNTHETIC_CORRELATION_FAILURE');
                    }
                    return ' BAD CORRELATION ';
                });
            $mapping = [];
            $reporting = [];
            $result = $this->handler($provider, $mapping, $reporting)->handle(new \RuntimeException('PRIVATE'));
            self::assertSame('OWNER_APPROVED_ERROR', $result->code());
            self::assertSame('map', $mapping[0]->operation());
            self::assertNull($mapping[0]->correlationId());
            self::assertNull($reporting[0]->correlationId());
        }
    }

    public function testExplicitMappingContextRemainsAuthoritativeWhileReporterReceivesSanitizedSnapshot(): void
    {
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())->method('correlationId')
            ->willReturn('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $caller = new ErrorHandlingContext(
            operation: 'caller.operation',
            correlationId: 'CALLER_SAFE_TOKEN',
            metadata: ['note' => 'CALLER_ONLY_METADATA'],
        );
        $mapping = [];
        $reporting = [];
        $handler = $this->handler($provider, $mapping, $reporting);
        $result = $handler->handle(new \RuntimeException('PRIVATE'), $caller);
        self::assertSame('OWNER_APPROVED_ERROR', $result->code());
        self::assertSame($caller, $mapping[0]);
        self::assertSame(['note' => 'CALLER_ONLY_METADATA'], $caller->metadata());
        self::assertSame([
            'correlationId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'metadata' => [],
            'operation' => 'map',
        ], $reporting[0]->toArray());
        self::assertStringNotContainsString(
            'CALLER_ONLY_METADATA',
            \json_encode($reporting[0]->toArray(), \JSON_THROW_ON_ERROR),
        );
    }

    public function testMapperContextDoesNotInfluenceReportingCorrelationOrMetadata(): void
    {
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())->method('correlationId')->willReturn('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $mapping = [];
        $reporting = [];
        $handler = $this->handler($provider, $mapping, $reporting);
        $context = new ErrorHandlingContext(operation: 'untrusted.source', metadata: ['note' => 'PRIVATE_NOTE']);
        $handler->handle(new \RuntimeException('PRIVATE'), $context);
        self::assertSame($context, $mapping[0]);
        self::assertSame([], $reporting[0]->metadata());
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $reporting[0]->correlationId());
    }

    /** @param list<ErrorHandlingContext|null> $mapped @param list<ErrorHandlingContext|null> $reported */
    private function handler(
        CorrelationIdProviderInterface $correlations,
        array &$mapped,
        array &$reported,
    ): ErrorHandler {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'owner.mapper');
        $mapper = $this->createStub(ExceptionMapperInterface::class);
        $mapper->method('map')->willReturnCallback(
            static function (\Throwable $throwable, ?ErrorHandlingContext $context) use (&$mapped): ErrorDescriptor {
                $mapped[] = $context;
                return new ErrorDescriptor('OWNER_APPROVED_ERROR', 'Operation failed.');
            },
        );
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($mapper);
        $reporter = $this->createMock(ErrorReporterPortInterface::class);
        $reporter->expects(self::once())->method('report')->willReturnCallback(
            static function (ErrorDescriptor $descriptor, ?ErrorHandlingContext $context) use (&$reported): void {
                $reported[] = $context;
            },
        );
        $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
        $redactor->method('redactJsonLike')->willReturnArgument(0);

        return new ErrorHandler(
            new ExceptionMapperRegistry($tags, $container, new NullLogger(), true, false),
            new DefaultExceptionMapper(),
            $redactor,
            $reporter,
            $correlations,
            new NoopTracer(),
            new NoopMeter(),
            new Stopwatch(),
            true,
            ErrorSeverity::Info,
            [],
            10000,
        );
    }
}
