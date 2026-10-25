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
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class ErrorDescriptorIsRedactedTest extends TestCase
{
    public function testRealSharedRedactorReplacesClassifiedNestedValuesWithoutLeakingSecrets(): void
    {
        $email = 'person@example.com';
        $token = 'Bearer SYNTHETIC_SECRET_TOKEN';
        $candidate = new ErrorDescriptor(
            'OWNER_APPROVED_ERROR',
            'Operation failed.',
            extensions: [
                'details' => ['recipient' => $email, 'reference' => $token],
                'attempts' => 2,
            ],
        );

        $descriptor = $this->handler($candidate, self::realRedactor())->handle(
            new \RuntimeException('SYNTHETIC_RAW_THROWABLE_PRIVATE'),
        );

        self::assertSame('OWNER_APPROVED_ERROR', $descriptor->code());
        self::assertSame('Operation failed.', $descriptor->message());
        self::assertSame(2, $descriptor->extensions()['attempts']);
        self::assertSame(true, $descriptor->extensions()['details']['recipient']['redacted']);
        self::assertSame('placeholder', $descriptor->extensions()['details']['recipient']['mode']);
        self::assertSame(true, $descriptor->extensions()['details']['reference']['redacted']);
        self::assertSame($descriptor->toArray(), ErrorDescriptor::fromArray($descriptor->toArray())->toArray());

        $encoded = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);
        foreach ([$email, $token, 'SYNTHETIC_RAW_THROWABLE_PRIVATE'] as $private) {
            self::assertStringNotContainsString($private, $encoded);
        }
    }

    public function testRealRedactorChangingCanonicalMessageTypeFailsClosed(): void
    {
        $secret = 'Bearer SYNTHETIC_WHOLE_MESSAGE';
        $descriptor = $this->handler(
            new ErrorDescriptor('OWNER_APPROVED_ERROR', $secret),
            self::realRedactor(),
        )->handle(new \RuntimeException('SYNTHETIC_RAW_PRIVATE'));

        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
        self::assertSame('Unhandled exception.', $descriptor->message());
        self::assertSame([], $descriptor->extensions());
        self::assertStringNotContainsString($secret, \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR));
    }

    public function testMalformedRedactorOutputAndThrownRedactionExceptionCannotExposeCandidate(): void
    {
        $candidate = new ErrorDescriptor('OWNER_PRIVATE_ERROR', 'SYNTHETIC_OWNER_PRIVATE');
        foreach (
            [
                'malformed',
                ['message' => ['redacted' => true]],
                new \RuntimeException('PRIVATE_REDACTOR_FAILURE'),
            ] as $failure
        ) {
            $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
            $expectation = $redactor->expects(self::once())->method('redactJsonLike');
            if ($failure instanceof \Throwable) {
                $expectation->willThrowException($failure);
            } else {
                $expectation->willReturn($failure);
            }
            $descriptor = $this->handler($candidate, $redactor)->handle(new \RuntimeException('PRIVATE_THROWABLE'));
            self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
            self::assertSame('Unhandled exception.', $descriptor->message());
            self::assertStringNotContainsString(
                'SYNTHETIC_OWNER_PRIVATE',
                \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR),
            );
        }
    }

    private static function realRedactor(): DefaultSensitiveDataRedactor
    {
        return new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
    }

    private function handler(ErrorDescriptor $candidate, SensitiveDataRedactorInterface $redactor): ErrorHandler
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'owner.mapper');
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->expects(self::once())->method('map')->willReturn($candidate);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with('owner.mapper')->willReturn($mapper);
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
