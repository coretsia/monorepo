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

namespace Coretsia\Platform\Http\Tests\Unit;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpResponseFinalizerTest extends TestCase
{
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string REQUEST_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAA';
    private const string NEXT_REQUEST_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAB';
    private const string REQUEST_HEADER = 'X-Edge-Request-Id';

    #[DataProvider('completedStatuses')]
    public function testAllCompletedResponsesUseTheSameCanonicalHeaderPolicy(int $status): void
    {
        $input = $this->createMock(ResponseInterface::class);
        $output = $this->createMock(ResponseInterface::class);
        $body = $this->createStub(StreamInterface::class);
        foreach ([$input, $output] as $response) {
            $response->expects(self::never())->method('withStatus');
            $response->expects(self::never())->method('withBody');
            $response->expects(self::never())->method('withoutHeader');
            $response->expects(self::never())->method('withAddedHeader');
        }
        $input->method('getStatusCode')->willReturn($status);
        $input->method('getBody')->willReturn($body);
        $input->method('getHeaderLine')->willReturnCallback(static fn (string $name): string => match ($name) {
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
            'X-Application' => 'retained',
            default => '',
        });
        $input->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Correlation-Id', self::CORRELATION, $output));
        $output->method('getStatusCode')->willReturn($status);
        $output->method('getBody')->willReturn($body);
        $output->method('getHeaderLine')->willReturnCallback(static fn (string $name): string => match ($name) {
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
            'X-Application' => 'retained',
            'X-Correlation-Id' => self::CORRELATION,
            default => '',
        });
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())->method('correlationId')->willReturn(self::CORRELATION);

        $actual = new HttpResponseFinalizer(
            $provider,
            true,
            'X-Correlation-Id',
            self::disabledRequestIds(),
            self::securityHeaders(false),
        )->finalize($input);

        self::assertSame($output, $actual);
        self::assertSame($status, $actual->getStatusCode());
        self::assertSame($body, $actual->getBody());
        foreach (['Content-Type', 'Cache-Control', 'X-Application'] as $name) {
            self::assertTrue(
                $input->getHeaderLine($name) === $actual->getHeaderLine($name),
                'unrelated-response-header-changed',
            );
        }
    }

    /** @return iterable<string, array{int}> */
    public static function completedStatuses(): iterable
    {
        yield 'successful-200' => [200];
        yield 'short-circuit-403' => [403];
        yield 'problem-json-400' => [400];
        yield 'problem-payload-too-large-413' => [413];
        yield 'problem-emergency-500' => [500];
    }

    public function testDisabledCorrelationPreservesResponseIdentityAndNeverReadsProvider(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::never())->method('withHeader');
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::never())->method('correlationId');

        self::assertSame(
            $response,
            new HttpResponseFinalizer(
                $provider,
                false,
                'X-Correlation-Id',
                self::disabledRequestIds(),
                self::securityHeaders(false),
            )->finalize($response),
        );
    }

    public function testRepeatingFinalizationReplacesHeaderThroughPsr7WithHeader(): void
    {
        $initial = $this->createMock(ResponseInterface::class);
        $first = $this->createMock(ResponseInterface::class);
        $second = $this->createStub(ResponseInterface::class);
        $initial->method('getHeader')->with('X-Correlation-Id')->willReturn(['untrusted']);
        $initial->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Correlation-Id', self::CORRELATION, $first));
        $first->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Correlation-Id', self::CORRELATION, $second));
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::exactly(2))->method('correlationId')->willReturn(self::CORRELATION);
        $finalizer = new HttpResponseFinalizer(
            $provider,
            true,
            'X-Correlation-Id',
            self::disabledRequestIds(),
            self::securityHeaders(false),
        );

        self::assertSame($second, $finalizer->finalize($finalizer->finalize($initial)));
        // PSR-7 withHeader() REPLACES existing values; withAddedHeader() is never used.
    }

    public function testReusedFinalizerReadsTheCurrentIdentityWithoutRetainingPreviousResponse(): void
    {
        $nextCorrelation = '01ARZ3NDEKTSV4RRFFQ69G5FAW';
        $first = $this->createMock(ResponseInterface::class);
        $firstFinal = $this->createStub(ResponseInterface::class);
        $second = $this->createMock(ResponseInterface::class);
        $secondFinal = $this->createStub(ResponseInterface::class);
        $first->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Correlation-Id', self::CORRELATION, $firstFinal));
        $second->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Correlation-Id', $nextCorrelation, $secondFinal));

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::exactly(2))->method('correlationId')
            ->willReturnOnConsecutiveCalls(self::CORRELATION, $nextCorrelation);
        $finalizer = new HttpResponseFinalizer(
            $provider,
            true,
            'X-Correlation-Id',
            self::disabledRequestIds(),
            self::securityHeaders(false),
        );

        self::assertSame($firstFinal, $finalizer->finalize($first));
        self::assertSame($secondFinal, $finalizer->finalize($second));

        $properties = \array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            new \ReflectionObject($finalizer)->getProperties(),
        );
        \sort($properties);
        self::assertSame(['correlationIds', 'enabled', 'headerName', 'requestIds', 'securityHeaders'], $properties);
    }

    #[DataProvider('invalidCorrelations')]
    public function testAbsentOrInvalidCorrelationNeverEmitsHeader(?string $correlation): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::never())->method('withHeader');
        $provider = $this->createStub(CorrelationIdProviderInterface::class);
        $provider->method('correlationId')->willReturn($correlation);

        self::assertSame(
            $response,
            new HttpResponseFinalizer(
                $provider,
                true,
                'X-Correlation-Id',
                self::disabledRequestIds(),
                self::securityHeaders(false),
            )->finalize($response),
        );
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidCorrelations(): iterable
    {
        yield 'absent' => [null];
        yield 'raw-request-value' => ['from-request-header'];
        yield 'control' => ["01ARZ3NDEKTSV4RRFFQ69G5FAV\r\nInjected: true"];
        yield 'noncanonical-ulid' => ['01arz3ndektsv4rrffq69g5fav'];
        yield 'oversized' => [\str_repeat('A', 200)];
    }

    public function testResponseFinalizerPropagatesTheOriginalPsr7Failure(): void
    {
        $failure = new \RuntimeException('test-finalizer-failure');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willThrowException($failure);
        $provider = $this->createStub(CorrelationIdProviderInterface::class);
        $provider->method('correlationId')->willReturn(self::CORRELATION);

        try {
            new HttpResponseFinalizer(
                $provider,
                true,
                'X-Correlation-Id',
                self::disabledRequestIds(),
                self::securityHeaders(false),
            )->finalize($response);
            self::fail('Response header failure must propagate.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testKernelCompletesExactlyOnceAndPreservesFinalizerFailureIdentity(): void
    {
        $failure = new \RuntimeException('header-mutation-failed');
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())
            ->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'fatal_error', self::identicalTo($failure))
            ->willThrowException(new \RuntimeException('kernel-after-failed'));
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willThrowException($failure);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);
        $provider = $this->createStub(CorrelationIdProviderInterface::class);
        $provider->method('correlationId')->willReturn(self::CORRELATION);
        $kernel = self::kernel($runtime, new Pipeline([], $terminal, false, [], false), $provider);

        try {
            $kernel->handle($request);
            self::fail('HttpKernel must not return intermediate response after failed finalization.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testKernelSuccessfulFinalizationAlsoCompletesExactlyOnce(): void
    {
        $handle = self::handle();
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::once())->method('beginUnitOfWork')->with('http')->willReturn($handle);
        $runtime->expects(self::once())->method('afterUnitOfWork')
            ->with(self::identicalTo($handle), 'handled_error', null)->willReturn([]);
        $response = $this->createStub(ResponseInterface::class);
        $final = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturn($final);
        $final->method('getStatusCode')->willReturn(413);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturn($response);
        $provider = $this->createStub(CorrelationIdProviderInterface::class);
        $provider->method('correlationId')->willReturn(self::CORRELATION);

        self::assertSame(
            $final,
            self::kernel($runtime, new Pipeline([], $terminal, false, [], false), $provider)->handle($request),
        );
    }

    #[DataProvider('identityHeaderConfigurations')]
    public function testIdentityHeadersAreFinalizedIndependentlyInCanonicalOrder(
        bool $correlationEnabled,
        bool $requestIdEnabled,
    ): void {
        $context = new ContextStore();
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects($requestIdEnabled ? self::once() : self::never())
            ->method('generate')
            ->willReturn(self::REQUEST_ID);

        $requestIds = new RequestIdPolicy(
            $context,
            $context,
            $generator,
            $requestIdEnabled,
            self::REQUEST_HEADER,
            false,
            128,
        );

        $selected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        self::assertTrue(
            $selected === ($requestIdEnabled ? self::REQUEST_ID : null),
            'selected-request-identity-mismatch',
        );

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects($correlationEnabled ? self::once() : self::never())
            ->method('correlationId')
            ->willReturn(self::CORRELATION);

        $input = $this->createMock(ResponseInterface::class);
        $correlated = $this->createMock(ResponseInterface::class);
        $final = $this->createMock(ResponseInterface::class);
        $body = $this->createStub(StreamInterface::class);

        foreach ([$input, $correlated, $final] as $response) {
            $response->method('getStatusCode')->willReturn(202);
            $response->method('getBody')->willReturn($body);
            $response->expects(self::never())->method('withStatus');
            $response->expects(self::never())->method('withBody');
            $response->expects(self::never())->method('withoutHeader');
            $response->expects(self::never())->method('withAddedHeader');
        }

        $input->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                self::REQUEST_HEADER => 'application-owned',
                'X-Application' => 'retained',
                default => '',
            },
        );
        $correlated->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                self::REQUEST_HEADER => 'application-owned',
                'X-Application' => 'retained',
                'X-Correlation-Id' => self::CORRELATION,
                default => '',
            },
        );
        $final->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                self::REQUEST_HEADER => self::REQUEST_ID,
                'X-Application' => 'retained',
                'X-Correlation-Id' => $correlationEnabled ? self::CORRELATION : '',
                default => '',
            },
        );

        $beforeRequestId = $input;
        if ($correlationEnabled) {
            $input->expects(self::once())
                ->method('withHeader')
                ->willReturnCallback(self::headerMutation('X-Correlation-Id', self::CORRELATION, $correlated));
            $beforeRequestId = $correlated;
        } else {
            $correlated->expects(self::never())->method('withHeader');
        }

        $expected = $beforeRequestId;
        if ($requestIdEnabled) {
            $beforeRequestId->expects(self::once())
                ->method('withHeader')
                ->willReturnCallback(self::headerMutation(self::REQUEST_HEADER, self::REQUEST_ID, $final));
            $expected = $final;
        } else {
            $beforeRequestId->expects(self::never())->method('withHeader');
        }

        $final->expects(self::never())->method('withHeader');

        $actual = new HttpResponseFinalizer(
            $provider,
            $correlationEnabled,
            'X-Correlation-Id',
            $requestIds,
            self::securityHeaders(false),
        )->finalize($input, $selected);

        self::assertSame($expected, $actual);
        self::assertSame(202, $actual->getStatusCode());
        self::assertSame($body, $actual->getBody());

        self::assertTrue(
            $actual->getHeaderLine(self::REQUEST_HEADER)
            === ($requestIdEnabled ? self::REQUEST_ID : 'application-owned'),
            'request-id-response-header-mismatch',
        );
        self::assertTrue(
            $actual->getHeaderLine('X-Correlation-Id')
            === ($correlationEnabled ? self::CORRELATION : ''),
            'correlation-response-header-mismatch',
        );
        self::assertTrue(
            $actual->getHeaderLine('X-Application') === 'retained',
            'unrelated-response-header-changed',
        );
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function identityHeaderConfigurations(): iterable
    {
        yield 'both-disabled' => [false, false];
        yield 'correlation-only' => [true, false];
        yield 'request-id-only' => [false, true];
        yield 'both-enabled' => [true, true];
    }

    #[DataProvider('identityHeaderConfigurations')]
    public function testSecurityHeadersFollowSuccessfulIdentityFinalizationInCanonicalOrder(
        bool $correlationEnabled,
        bool $requestIdEnabled,
    ): void {
        $requestIds = $requestIdEnabled
            ? $this->enabledRequestIds(new ContextStore())
            : self::disabledRequestIds();

        $selected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects($correlationEnabled ? self::once() : self::never())
            ->method('correlationId')
            ->willReturn(self::CORRELATION);

        $expected = [];

        if ($correlationEnabled) {
            $expected['X-Correlation-Id'] = self::CORRELATION;
        }

        if ($requestIdEnabled) {
            $expected[self::REQUEST_HEADER] = self::REQUEST_ID;
        }

        $expected += [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'geolocation=()',
            'Content-Security-Policy' => "default-src 'none'",
        ];

        $input = $this->createMock(ResponseInterface::class);
        $current = $input;

        foreach ($expected as $name => $value) {
            $next = $this->createMock(ResponseInterface::class);

            $current->expects(self::once())
                ->method('withHeader')
                ->willReturnCallback(self::headerMutation($name, $value, $next));

            $current->expects(self::never())->method('withAddedHeader');
            $current->expects(self::never())->method('withoutHeader');
            $current->expects(self::never())->method('withStatus');
            $current->expects(self::never())->method('withBody');

            $current = $next;
        }

        $current->expects(self::never())->method('withHeader');

        $finalizer = new HttpResponseFinalizer(
            $provider,
            $correlationEnabled,
            'X-Correlation-Id',
            $requestIds,
            self::securityHeaders(true),
        );

        self::assertSame($current, $finalizer->finalize($input, $selected));
    }

    #[DataProvider('invalidCorrelations')]
    public function testEnabledRequestIdIsFinalizedWhenCorrelationIsAbsentOrInvalid(?string $correlation): void
    {
        $requestIds = $this->enabledRequestIds(new ContextStore());
        $selected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())
            ->method('correlationId')
            ->willReturn($correlation);

        $input = $this->createMock(ResponseInterface::class);
        $output = $this->createStub(ResponseInterface::class);
        $input->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation(self::REQUEST_HEADER, self::REQUEST_ID, $output));

        self::assertSame(
            $output,
            new HttpResponseFinalizer(
                $provider,
                true,
                'X-Correlation-Id',
                $requestIds,
                self::securityHeaders(false),
            )->finalize($input, $selected),
        );
    }

    #[DataProvider('invalidCorrelations')]
    public function testSecurityHeadersAreAppliedWhenBothIdentitiesAreAbsent(?string $correlation): void
    {
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())
            ->method('correlationId')
            ->willReturn($correlation);

        $policy = new SecurityHeadersPolicy(
            true,
            'nosniff',
            'sameorigin',
            'strict-origin',
            '',
            '',
        );

        $input = $this->createMock(ResponseInterface::class);
        $withoutSniffing = $this->createMock(ResponseInterface::class);
        $withFrameOptions = $this->createMock(ResponseInterface::class);
        $output = $this->createMock(ResponseInterface::class);

        $input->expects(self::once())->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Content-Type-Options', 'nosniff', $withoutSniffing));
        $withoutSniffing->expects(self::once())->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Frame-Options', 'SAMEORIGIN', $withFrameOptions));
        $withFrameOptions->expects(self::once())->method('withHeader')
            ->willReturnCallback(self::headerMutation('Referrer-Policy', 'strict-origin', $output));
        $output->expects(self::never())->method('withHeader');

        $finalizer = new HttpResponseFinalizer(
            $provider,
            true,
            'X-Correlation-Id',
            self::disabledRequestIds(),
            $policy,
        );

        self::assertSame($output, $finalizer->finalize($input));
    }

    #[DataProvider('requestIdentityIntegrityFailures')]
    public function testRequestIdentityIntegrityFailurePreventsHeaderEmission(string $failure): void
    {
        $context = new ContextStore();
        $enabled = $failure !== 'disabled-selected';

        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects($enabled ? self::once() : self::never())
            ->method('generate')
            ->willReturn(self::REQUEST_ID);

        $requestIds = new RequestIdPolicy(
            $context,
            $context,
            $generator,
            $enabled,
            self::REQUEST_HEADER,
            false,
            128,
        );

        $selected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        switch ($failure) {
            case 'missing-context':
                $context->remove(ContextKeys::REQUEST_ID);
                break;
            case 'invalid-context':
                $context->set(ContextKeys::REQUEST_ID, 'invalid request id');
                break;
            case 'non-string-context':
                $context->set(ContextKeys::REQUEST_ID, 123);
                break;
            case 'substituted-context':
                $context->set(ContextKeys::REQUEST_ID, self::NEXT_REQUEST_ID);
                break;
            case 'missing-selected':
                $selected = null;
                break;
            case 'disabled-selected':
                $selected = self::REQUEST_ID;
                break;
            default:
                self::fail('unknown-request-identity-failure-scenario');
        }

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::never())->method('correlationId');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::never())->method('withHeader');
        $response->expects(self::never())->method('withAddedHeader');

        $finalizer = new HttpResponseFinalizer(
            $provider,
            false,
            'X-Correlation-Id',
            $requestIds,
            self::securityHeaders(true),
        );

        try {
            $finalizer->finalize($response, $selected);
            self::fail('request-identity-integrity-failure-expected');
        } catch (\RuntimeException $actual) {
            self::assertTrue(
                $actual->getMessage() === 'http-request-id-context-invalid',
                'request-identity-integrity-failure-reason-mismatch',
            );
        }
    }

    /** @return iterable<string, array{string}> */
    public static function requestIdentityIntegrityFailures(): iterable
    {
        yield 'missing-context' => ['missing-context'];
        yield 'invalid-context' => ['invalid-context'];
        yield 'non-string-context' => ['non-string-context'];
        yield 'substituted-context' => ['substituted-context'];
        yield 'missing-selected' => ['missing-selected'];
        yield 'disabled-selected' => ['disabled-selected'];
    }

    public function testRequestIdMutationMustReturnResponseInterface(): void
    {
        $requestIds = $this->enabledRequestIds(new ContextStore());
        $selected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::never())->method('correlationId');

        $response = $this->createMock(ResponseInterface::class);
        $invalid = $this->createStub(MessageInterface::class);

        $response->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation(self::REQUEST_HEADER, self::REQUEST_ID, $invalid));

        $finalizer = new HttpResponseFinalizer(
            $provider,
            false,
            'X-Correlation-Id',
            $requestIds,
            self::securityHeaders(true),
        );

        try {
            $finalizer->finalize($response, $selected);
            self::fail('invalid-request-id-response-mutation-must-fail');
        } catch (\RuntimeException $actual) {
            self::assertTrue(
                $actual->getMessage() === 'http-request-id-response-invalid',
                'invalid-request-id-response-reason-mismatch',
            );
        }
    }

    public function testCorrelationMutationFailurePreventsRequestIdMutation(): void
    {
        $requestIds = $this->enabledRequestIds(new ContextStore());
        $selected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())
            ->method('correlationId')
            ->willReturn(self::CORRELATION);

        $failure = new \RuntimeException('correlation-header-mutation-failed');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(static function (string $name, mixed $value) use ($failure): never {
                self::assertTrue($name === 'X-Correlation-Id', 'unexpected-identity-header-name');
                self::assertTrue($value === self::CORRELATION, 'unexpected-correlation-header-value');

                throw $failure;
            });

        $finalizer = new HttpResponseFinalizer(
            $provider,
            true,
            'X-Correlation-Id',
            $requestIds,
            self::securityHeaders(true),
        );

        try {
            $finalizer->finalize($response, $selected);
            self::fail('correlation-mutation-failure-must-propagate');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testRequestIdMutationPropagatesOriginalFailureAfterCorrelationMutation(): void
    {
        $requestIds = $this->enabledRequestIds(new ContextStore());
        $selected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::once())
            ->method('correlationId')
            ->willReturn(self::CORRELATION);

        $failure = new \RuntimeException('request-id-header-mutation-failed');
        $response = $this->createMock(ResponseInterface::class);
        $correlated = $this->createMock(ResponseInterface::class);

        $response->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Correlation-Id', self::CORRELATION, $correlated));

        $correlated->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(static function (string $name, mixed $value) use ($failure): never {
                self::assertTrue($name === self::REQUEST_HEADER, 'unexpected-identity-header-name');
                self::assertTrue($value === self::REQUEST_ID, 'unexpected-request-id-header-value');

                throw $failure;
            });

        $finalizer = new HttpResponseFinalizer(
            $provider,
            true,
            'X-Correlation-Id',
            $requestIds,
            self::securityHeaders(true),
        );

        try {
            $finalizer->finalize($response, $selected);
            self::fail('request-id-mutation-failure-must-propagate');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testSecurityHeaderMutationMustReturnResponseInterface(): void
    {
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::never())->method('correlationId');

        $input = $this->createMock(ResponseInterface::class);
        $intermediate = $this->createMock(ResponseInterface::class);
        $invalid = $this->createMock(MessageInterface::class);

        $input->expects(self::once())->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Content-Type-Options', 'nosniff', $intermediate));
        $intermediate->expects(self::once())->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Frame-Options', 'DENY', $invalid));
        $invalid->expects(self::never())->method('withHeader');

        $finalizer = new HttpResponseFinalizer(
            $provider,
            false,
            'X-Correlation-Id',
            self::disabledRequestIds(),
            self::securityHeaders(true),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('http-security-header-response-invalid');

        $finalizer->finalize($input);
    }

    public function testSecurityHeaderMutationPropagatesOriginalFailure(): void
    {
        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::never())->method('correlationId');

        $failure = new \RuntimeException('security-header-mutation-failed');
        $input = $this->createMock(ResponseInterface::class);
        $intermediate = $this->createMock(ResponseInterface::class);

        $input->expects(self::once())->method('withHeader')
            ->willReturnCallback(self::headerMutation('X-Content-Type-Options', 'nosniff', $intermediate));

        $intermediate->expects(self::once())->method('withHeader')
            ->willReturnCallback(static function (string $name, mixed $value) use ($failure): never {
                self::assertTrue($name === 'X-Frame-Options', 'security-header-name-mismatch');
                self::assertTrue($value === 'DENY', 'security-header-value-mismatch');

                throw $failure;
            });

        $finalizer = new HttpResponseFinalizer(
            $provider,
            false,
            'X-Correlation-Id',
            self::disabledRequestIds(),
            self::securityHeaders(true),
        );

        try {
            $finalizer->finalize($input);
            self::fail('security-header-mutation-failure-must-propagate');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
        }
    }

    public function testReusedFinalizerUsesCurrentRequestIdentityWithoutRegeneration(): void
    {
        $context = new ContextStore();
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::exactly(2))
            ->method('generate')
            ->willReturnOnConsecutiveCalls(self::REQUEST_ID, self::NEXT_REQUEST_ID);

        $requestIds = new RequestIdPolicy(
            $context,
            $context,
            $generator,
            true,
            self::REQUEST_HEADER,
            false,
            128,
        );

        $provider = $this->createMock(CorrelationIdProviderInterface::class);
        $provider->expects(self::never())->method('correlationId');

        $finalizer = new HttpResponseFinalizer(
            $provider,
            false,
            'X-Correlation-Id',
            $requestIds,
            self::securityHeaders(false),
        );

        $first = $this->createMock(ResponseInterface::class);
        $firstFinal = $this->createStub(ResponseInterface::class);
        $second = $this->createMock(ResponseInterface::class);
        $secondFinal = $this->createStub(ResponseInterface::class);

        $first->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation(self::REQUEST_HEADER, self::REQUEST_ID, $firstFinal));
        $second->expects(self::once())
            ->method('withHeader')
            ->willReturnCallback(self::headerMutation(self::REQUEST_HEADER, self::NEXT_REQUEST_ID, $secondFinal));

        $firstSelected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        self::assertTrue($firstSelected === self::REQUEST_ID, 'first-request-identity-mismatch');
        self::assertSame($firstFinal, $finalizer->finalize($first, $firstSelected));

        $context->reset();

        $secondSelected = $requestIds->initialize($this->createStub(ServerRequestInterface::class));

        self::assertTrue($secondSelected === self::NEXT_REQUEST_ID, 'second-request-identity-mismatch');
        self::assertTrue($firstSelected !== $secondSelected, 'request-identity-reused');
        self::assertSame($secondFinal, $finalizer->finalize($second, $secondSelected));
    }

    private static function disabledRequestIds(): RequestIdPolicy
    {
        $context = new ContextStore();

        return new RequestIdPolicy(
            $context,
            $context,
            new class() implements IdGeneratorInterface {
                public function generate(): string
                {
                    throw new \LogicException('disabled-request-id-generation-invoked');
                }
            },
            false,
            self::REQUEST_HEADER,
            false,
            128,
        );
    }

    private static function securityHeaders(bool $enabled): SecurityHeadersPolicy
    {
        return new SecurityHeadersPolicy(
            $enabled,
            'nosniff',
            'deny',
            'no-referrer',
            'geolocation=()',
            "default-src 'none'",
        );
    }

    private function enabledRequestIds(ContextStore $context): RequestIdPolicy
    {
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::once())
            ->method('generate')
            ->willReturn(self::REQUEST_ID);

        return new RequestIdPolicy(
            $context,
            $context,
            $generator,
            true,
            self::REQUEST_HEADER,
            false,
            128,
        );
    }

    private static function headerMutation(
        string $expectedName,
        string $expectedValue,
        MessageInterface $response,
    ): \Closure {
        return static function (string $name, mixed $value) use (
            $expectedName,
            $expectedValue,
            $response,
        ): MessageInterface {
            self::assertTrue($name === $expectedName, 'identity-header-name-mismatch');
            self::assertTrue($value === $expectedValue, 'identity-header-value-mismatch');

            return $response;
        };
    }

    private static function kernel(
        KernelRuntimeInterface $runtime,
        Pipeline $pipeline,
        CorrelationIdProviderInterface $correlations,
    ): HttpKernel {
        $requestIds = self::disabledRequestIds();

        return new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer(
                $correlations,
                true,
                'X-Correlation-Id',
                $requestIds,
                self::securityHeaders(false),
            ),
            new HttpRequestMetricsRecorder(new NoopMeter(), false),
            new HttpAccessLogWriter(new NoopLogger(), false),
            new HttpRequestSpanRecorder(new NoopTracer(), false),
            $requestIds,
        );
    }

    private static function handle(): UnitOfWorkHandle
    {
        return new UnitOfWorkHandle([
            'attributes' => [],
            'correlationId' => self::CORRELATION,
            'type' => 'http',
            'uowId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
    }
}
