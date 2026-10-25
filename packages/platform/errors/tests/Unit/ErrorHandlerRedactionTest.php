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
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionMode;
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

final class ErrorHandlerRedactionTest extends TestCase
{
    public function testRedactionUsesOneFixedDescriptorScopeAndPlaceholderMode(): void
    {
        $candidate = self::candidate();
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::once())
            ->method('redactJsonLike')
            ->with(
                self::callback(static fn (mixed $value): bool => $value === $candidate->toArray()),
                self::callback(
                    static fn (RedactionContext $context): bool => $context->scope() === 'errors.descriptor'
                        && $context->mode() === RedactionMode::Placeholder,
                ),
            )
            ->willReturnArgument(0);

        $context = new ErrorHandlingContext(operation: 'caller.operation', metadata: ['note' => 'safe']);
        self::assertSame(
            $candidate->toArray(),
            $this->handler($candidate, $redactor)->handle(
                new \RuntimeException('SYNTHETIC_THROWABLE_PRIVATE'),
                $context,
            )->toArray(),
        );
    }

    public function testReconstructionAcceptsOnlyTheValidatedCanonicalDescriptorShape(): void
    {
        $candidate = self::candidate();
        $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
        $redactor->method('redactJsonLike')->willReturnCallback(
            static function (array $payload): array {
                $payload['message'] = 'Owner-approved normalized public message.';
                $payload['extensions'] = ['attempts' => 2];

                return $payload;
            },
        );

        $descriptor = $this->handler($candidate, $redactor)->handle(new \RuntimeException('SYNTHETIC_PRIVATE'));

        self::assertSame('Owner-approved normalized public message.', $descriptor->message());
        self::assertSame(['attempts' => 2], $descriptor->extensions());
        self::assertSame($descriptor->toArray(), ErrorDescriptor::fromArray($descriptor->toArray())->toArray());
        self::assertSame(
            ['code', 'extensions', 'httpStatus', 'message', 'schemaVersion', 'severity'],
            \array_keys($descriptor->toArray()),
        );
    }

    public function testMalformedRedactorOutputSelectsTheSafeFallbackWithoutReturningTheCandidate(): void
    {
        $candidate = self::candidate('SYNTHETIC_CANDIDATE_PRIVATE');
        $withWrongMessageType = $candidate->toArray();
        $withWrongMessageType['message'] = ['redacted' => true];
        $withForbiddenExtension = $candidate->toArray();
        $withForbiddenExtension['extensions'] = ['authorization' => 'SYNTHETIC_PRIVATE'];

        foreach ([null, 'invalid-output', [], $withWrongMessageType, $withForbiddenExtension] as $output) {
            $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
            $redactor->expects(self::once())->method('redactJsonLike')->willReturn($output);

            self::assertFixedFallback(
                $this->handler($candidate, $redactor)->handle(
                    new \RuntimeException('SYNTHETIC_THROWABLE_PRIVATE'),
                ),
            );
        }
    }

    public function testThrowingRedactorSelectsFallbackWithoutRecursiveRedactionOrRawLeakage(): void
    {
        $candidate = self::candidate('SYNTHETIC_CANDIDATE_PRIVATE');
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::once())
            ->method('redactJsonLike')
            ->willThrowException(new \RuntimeException('SYNTHETIC_REDACTOR_PRIVATE'));

        self::assertFixedFallback(
            $this->handler($candidate, $redactor)->handle(
                new \RuntimeException('SYNTHETIC_THROWABLE_PRIVATE'),
            ),
        );
    }

    public function testWholeValueSensitiveMessageBecomingARedactionMapSelectsSafeFallback(): void
    {
        $secret = 'Bearer SYNTHETIC_WHOLE_VALUE_PRIVATE';
        $descriptor = $this->handler(self::candidate($secret), self::canonicalRedactor())->handle(
            new \RuntimeException('SYNTHETIC_THROWABLE_PRIVATE'),
        );

        // The canonical redactor returns a map for a classified string; it must
        // not be coerced into the contracts-owned string message field.
        self::assertFixedFallback($descriptor);
        self::assertStringNotContainsString($secret, \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR));
    }

    public function testValidOwnerApprovedDescriptorPreservesOriginalCanonicalFieldTypes(): void
    {
        $candidate = new ErrorDescriptor(
            'OWNER_APPROVED_FAILURE',
            'Operation failed.',
            ErrorSeverity::Warning,
            409,
            ['attempts' => 3, 'handled' => true],
        );

        $descriptor = $this->handler($candidate, self::canonicalRedactor())->handle(new \RuntimeException('PRIVATE'));
        $payload = $descriptor->toArray();

        self::assertSame($candidate->toArray(), $payload);
        self::assertIsString($payload['code']);
        self::assertIsString($payload['message']);
        self::assertIsString($payload['severity']);
        self::assertIsInt($payload['httpStatus']);
        self::assertIsInt($payload['schemaVersion']);
        self::assertIsArray($payload['extensions']);
        self::assertSame(3, $payload['extensions']['attempts']);
    }

    public function testNestedClassifiedSensitiveExtensionIsRedactedAndRevalidated(): void
    {
        $email = 'person@example.com';
        $candidate = new ErrorDescriptor(
            'OWNER_APPROVED_FAILURE',
            'Operation failed.',
            extensions: ['details' => ['contact' => $email, 'attempts' => 2]],
        );

        $descriptor = $this->handler($candidate, self::canonicalRedactor())->handle(new \RuntimeException('PRIVATE'));
        $export = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);

        self::assertSame('OWNER_APPROVED_FAILURE', $descriptor->code());
        self::assertSame(true, $descriptor->extensions()['details']['contact']['redacted']);
        self::assertSame('placeholder', $descriptor->extensions()['details']['contact']['mode']);
        self::assertSame(2, $descriptor->extensions()['details']['attempts']);
        self::assertSame($descriptor->toArray(), ErrorDescriptor::fromArray($descriptor->toArray())->toArray());
        self::assertStringNotContainsString($email, $export);
    }

    public function testUnclassifiedEmbeddedStringsAreNotAssumedToBeSanitizedByTheRedactor(): void
    {
        $unclassified = 'Owner-visible text containing SYNTHETIC_EMBEDDED_PRIVATE';
        $descriptor = $this->handler(self::candidate($unclassified), self::canonicalRedactor())->handle(
            new \RuntimeException('PRIVATE'),
        );

        // Classifier non-matches are not declassification. This test documents
        // why mapper owners must derive safe messages before constructing them.
        self::assertSame($unclassified, $descriptor->message());
        self::assertSame('OWNER_APPROVED_FAILURE', $descriptor->code());
    }

    public function testRedactionDoesNotReplaceMapperOwnerSafeMessageDerivation(): void
    {
        $safe = self::candidate('Owner-approved fixed reason.');
        $unsafe = self::candidate('Failure (token SYNTHETIC_EMBEDDED_PRIVATE)');

        $safeResult = $this->handler($safe, self::canonicalRedactor())->handle(new \RuntimeException('PRIVATE'));
        $unsafeResult = $this->handler($unsafe, self::canonicalRedactor())->handle(new \RuntimeException('PRIVATE'));

        self::assertSame('Owner-approved fixed reason.', $safeResult->message());
        self::assertSame($unsafe->message(), $unsafeResult->message());
        self::assertNotSame('CORETSIA_UNHANDLED_EXCEPTION', $unsafeResult->code());
    }

    private static function candidate(string $message = 'Owner-approved fixed reason.'): ErrorDescriptor
    {
        return new ErrorDescriptor('OWNER_APPROVED_FAILURE', $message);
    }

    private static function canonicalRedactor(): DefaultSensitiveDataRedactor
    {
        return new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
    }

    private static function assertFixedFallback(ErrorDescriptor $descriptor): void
    {
        self::assertSame([
            'code' => 'CORETSIA_UNHANDLED_EXCEPTION',
            'extensions' => [],
            'httpStatus' => null,
            'message' => 'Unhandled exception.',
            'schemaVersion' => ErrorDescriptor::SCHEMA_VERSION,
            'severity' => 'error',
        ], $descriptor->toArray());

        $export = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);
        foreach (
            [
                'SYNTHETIC_CANDIDATE_PRIVATE',
                'SYNTHETIC_REDACTOR_PRIVATE',
                'SYNTHETIC_THROWABLE_PRIVATE',
            ] as $secret
        ) {
            self::assertStringNotContainsString($secret, $export);
        }
    }

    private function handler(ErrorDescriptor $candidate, SensitiveDataRedactorInterface $redactor): ErrorHandler
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'owner.mapper');
        $mapper = $this->createStub(ExceptionMapperInterface::class);
        $mapper->method('map')->willReturn($candidate);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($mapper);
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
