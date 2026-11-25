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

namespace Coretsia\Platform\Http\Tests\Integration;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\HttpsRedirect\HttpsRedirectPolicy;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\HttpsRedirectMiddleware;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersAppliedToAllResponsesTest extends TestCase
{
    private const string REQUEST_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAA';
    private const array SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'geolocation=()',
        'Content-Security-Policy' => "default-src 'none'",
    ];
    private const array APPLICATION_HEADERS = [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'private',
        'Location' => '/application-location',
        'X-Application' => 'retained',
    ];

    #[DataProvider('responseCases')]
    public function testCanonicalCompletionHardensEveryReturnedResponse(
        string $case,
        int|string $status,
        bool $hardening,
        bool $correlationEnabled,
        bool $requestIdEnabled,
    ): void {
        $context = new ContextStore();
        $events = [];
        $mutations = [];
        $mutationsAtCompletion = [];
        $records = [];
        $fatal = $status === 'unknown';
        $outcome = $fatal ? 'fatal_error' : ($status < 400 ? 'success' : 'handled_error');
        $failure = match ($case) {
            'mapped', 'redirect-failure' => BadJsonException::invalid(),
            default => new \RuntimeException('synthetic-http-failure'),
        };
        $body = $this->createStub(StreamInterface::class);
        $body->method('__toString')->willReturn('synthetic-body');
        $problemBody = null;
        $problemContent = null;
        $correlation = new CorrelationIdProvider($context);
        $runtime = $this->runtime($context, $outcome, $requestIdEnabled, $events);
        $requestGenerator = $this->createMock(IdGeneratorInterface::class);
        $requestGenerator->expects($requestIdEnabled ? self::once() : self::never())
            ->method('generate')->willReturn(self::REQUEST_ID);
        $requestIds = new RequestIdPolicy(
            $context,
            $context,
            $requestGenerator,
            $requestIdEnabled,
            'X-Request-Id',
            false,
            128,
        );
        $policy = new SecurityHeadersPolicy(
            $hardening,
            'nosniff',
            'deny',
            'no-referrer',
            'geolocation=()',
            "default-src 'none'",
        );

        $base = $this->response(
            \is_int($status) ? $status : 200,
            $body,
            self::APPLICATION_HEADERS,
            $mutations,
            $case === 'finalizer-failure' ? $failure : null,
        );
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getScheme')->willReturn('http');
        $uri->method('getPath')->willReturn('/safe%2Fpath');
        $uri->method('getQuery')->willReturn('value=%20');
        $request->method('getUri')->willReturn($uri);

        $mapped = \in_array($case, ['mapped', 'redirect-failure'], true);
        $problem = $mapped || $case === 'emergency' || $case === 'boundary-failure';
        $errors = $this->createMock(ErrorHandlerInterface::class);
        if ($mapped) {
            $descriptor = new HttpExceptionMapper()->map($failure);
            self::assertNotNull($descriptor);
            $errors->expects(self::once())->method('handle')->with(self::identicalTo($failure))->willReturn(
                $descriptor,
            );
        } elseif ($problem) {
            $errors->expects(self::once())->method('handle')->with(self::identicalTo($failure))
                ->willThrowException(new \RuntimeException('synthetic-normalization-failure'));
        } else {
            $errors->expects(self::never())->method('handle');
        }
        $problemFactory = $this->createMock(ResponseFactoryInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        if ($case === 'boundary-failure') {
            $problemFactory->expects(self::once())->method('createResponse')->with(500)
                ->willThrowException(new \RuntimeException('synthetic-error-transport-failure'));
            $streams->expects(self::never())->method('createStream');
        } elseif ($problem) {
            $problemFactory->expects(self::once())->method('createResponse')->with($status)->willReturn($base);
            $streams->expects(self::once())->method('createStream')->willReturnCallback(
                function (string $content) use (&$problemBody, &$problemContent): StreamInterface {
                    $problemContent = $content;
                    /** @var StreamInterface&\PHPUnit\Framework\MockObject\Stub $stream */
                    $stream = $this->createStub(StreamInterface::class);
                    $stream->method('__toString')->willReturn($content);
                    $problemBody = $stream;

                    return $stream;
                },
            );
        } else {
            $problemFactory->expects(self::never())->method('createResponse');
            $streams->expects(self::never())->method('createStream');
        }
        $boundary = new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $problemFactory,
            $streams,
        );

        $redirect = \in_array($case, ['redirect', 'redirect-failure'], true);
        $redirectFactory = $this->createMock(ResponseFactoryInterface::class);
        if ($case === 'redirect-failure') {
            $redirectFactory->expects(self::once())->method('createResponse')->with(308)->willThrowException($failure);
        } elseif ($redirect) {
            $redirectFactory->expects(self::once())->method('createResponse')->with(308)->willReturn($base);
        } else {
            $redirectFactory->expects(self::never())->method('createResponse');
        }
        $redirectMiddleware = new HttpsRedirectMiddleware(
            new HttpsRedirectPolicy($redirect, 308, ['GET'], 'secure.example:8443'),
            $redirectFactory,
            new NoopMeter(),
            new NoopLogger(),
            false,
            true,
            true,
        );

        $shortCircuit = \in_array($case, ['request-short', 'post-short', 'maintenance'], true);
        $short = $this->createMock(MiddlewareInterface::class);
        $short->expects($shortCircuit ? self::once() : self::never())->method('process')->willReturn($base);
        $post = $this->createMock(MiddlewareInterface::class);
        $post->expects($case === 'request-short' ? self::once() : self::never())->method('process')
            ->willReturnCallback(
                static function (
                    ServerRequestInterface $incoming,
                    RequestHandlerInterface $next,
                ): ResponseInterface {
                    $response = $next->handle($incoming)->withHeader('X-Post-Decorator', 'retained');
                    self::assertInstanceOf(ResponseInterface::class, $response);

                    return $response;
                },
            );
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $handling = $terminal->expects($shortCircuit || $redirect ? self::never() : self::once())->method('handle');
        if ($problem || $case === 'escaped') {
            $handling->willThrowException($failure);
        } else {
            $handling->willReturn($base);
        }

        $http = require __DIR__ . '/../../config/http.php';
        $http['https_redirect']['enabled'] = $redirect;
        $http['https_redirect']['target_authority'] = 'secure.example:8443';
        $tags = new TagRegistry();
        $selected = $case !== 'escaped';
        if ($selected) {
            $definitions = new ContainerDefinitionBuilder();
            new ProblemDetailsServiceProvider()->define(
                $definitions,
                new ContainerDefinitionContext(['http' => $http]),
            );
            foreach ($definitions->build()->toDescriptorStream() as $operation) {
                if (($operation['kind'] ?? null) === 'tag') {
                    $tags->add($operation['tag'], $operation['serviceId'], $operation['priority'], $operation['meta']);
                }
            }
        }
        $tags->add(
            ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            HttpsRedirectMiddleware::class,
            850,
            ['toggle' => 'http.https_redirect.enabled'],
        );
        if ($shortCircuit) {
            $tags->add(
                $case === 'post-short' ? ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST : ReservedTags::HTTP_MIDDLEWARE_APP_PRE,
                'App\\ShortCircuit',
            );
        }
        if ($case === 'request-short') {
            $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_POST, 'App\\PostDecorator');
        }
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static fn (string $id): MiddlewareInterface => match ($id) {
            HttpErrorHandlingMiddleware::class => $boundary,
            HttpsRedirectMiddleware::class => $redirectMiddleware,
            'App\\ShortCircuit' => $short,
            'App\\PostDecorator' => $post,
            default => throw new \LogicException('unexpected-http-middleware'),
        });
        $stack = new HttpMiddlewareStackBuilder($tags);
        if ($selected) {
            self::assertSame(HttpErrorHandlingMiddleware::class, $stack->build($http)[0]);
        }
        $pipeline = HttpServiceFactory::pipeline(
            $container,
            new ArrayConfigRepository(['http' => $http], []),
            $stack,
            $terminal,
        );
        $meter = $this->createStub(MeterPortInterface::class);
        $meter->method('increment')->willReturnCallback(
            static function (string $name, int $delta, array $labels) use (
                &$records,
                &$events,
                &$mutations,
                &$mutationsAtCompletion,
            ): void {
                $records[] = [$name, $delta, $labels];
                $mutationsAtCompletion[] = $mutations;
                $events[] = 'completion';
            },
        );
        $kernel = new HttpKernel(
            $runtime,
            new Stopwatch(),
            $pipeline,
            new HttpResponseFinalizer($correlation, $correlationEnabled, 'X-Correlation-Id', $requestIds, $policy),
            new HttpRequestMetricsRecorder($meter, true),
            new HttpAccessLogWriter(new NoopLogger(), true),
            new HttpRequestSpanRecorder(new NoopTracer(), true),
            $requestIds,
        );

        if ($fatal) {
            try {
                $kernel->handle($request);
                self::fail('fatal-http-failure-returned-response');
            } catch (\Throwable $actual) {
                self::assertSame($failure, $actual);
            }
            $expectedNames = $case === 'finalizer-failure'
                ? [
                    ...($correlationEnabled ? ['X-Correlation-Id'] : []),
                    ...($requestIdEnabled ? ['X-Request-Id'] : []),
                    'X-Content-Type-Options',
                ]
                : [];
            self::assertSame($expectedNames, $mutations);
        } else {
            $actual = $kernel->handle($request);
            self::assertSame($status, $actual->getStatusCode());
            self::assertSame($problem ? $problemBody : $body, $actual->getBody());
            if ($problem) {
                self::assertIsString($problemContent);
                $decoded = \json_decode($problemContent, true, 512, JSON_THROW_ON_ERROR);
                self::assertSame($status, $decoded['status']);
                self::assertSame('about:blank', $decoded['type']);
            }
            $headers = self::APPLICATION_HEADERS;
            $expectedNames = [];
            if ($problem) {
                $headers['Content-Type'] = 'application/problem+json';
                $headers['Cache-Control'] = 'no-store';
                $expectedNames = ['Content-Type', 'Cache-Control'];
            }
            if ($case === 'redirect') {
                $headers['Location'] = 'https://secure.example:8443/safe%2Fpath?value=%20';
                $expectedNames[] = 'Location';
            }
            if ($case === 'request-short') {
                $headers['X-Post-Decorator'] = 'retained';
                $expectedNames[] = 'X-Post-Decorator';
            }
            if ($correlationEnabled) {
                self::assertSame(
                    1,
                    \preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $actual->getHeaderLine('X-Correlation-Id')),
                    'correlation-header-invalid',
                );
                $expectedNames[] = 'X-Correlation-Id';
            } else {
                self::assertSame('', $actual->getHeaderLine('X-Correlation-Id'));
            }
            if ($requestIdEnabled) {
                $headers['X-Request-Id'] = self::REQUEST_ID;
                $expectedNames[] = 'X-Request-Id';
            } else {
                self::assertSame('', $actual->getHeaderLine('X-Request-Id'));
            }
            foreach (self::SECURITY_HEADERS as $name => $value) {
                self::assertSame($hardening ? $value : '', $actual->getHeaderLine($name));
                if ($hardening) {
                    $expectedNames[] = $name;
                }
            }
            foreach ($headers as $name => $value) {
                self::assertSame($value, $actual->getHeaderLine($name));
            }
            self::assertSame($expectedNames, $mutations);
            if (!$hardening && !$correlationEnabled && !$requestIdEnabled && $case === 'normal') {
                self::assertSame($base, $actual);
            }
        }
        self::assertSame(
            [['http.request_total', 1, ['method' => 'GET', 'status' => $status, 'outcome' => $outcome]]],
            $records,
        );
        self::assertSame([$mutations], $mutationsAtCompletion);
        self::assertSame(['completion', 'after', 'reset'], $events);
        self::assertSame([], $context->all());
    }

    /** @return iterable<string, array{string, int|string, bool, bool, bool}> */
    public static function responseCases(): iterable
    {
        foreach ([false, true] as $correlation) {
            foreach ([false, true] as $requestId) {
                foreach ([false, true] as $hardening) {
                    yield \sprintf('normal-%d-%d-%d', $correlation, $requestId, $hardening)
                    => ['normal', 200, $hardening, $correlation, $requestId];
                }
            }
        }
        foreach (
            [
                'request-short' => 403,
                'post-short' => 403,
                'maintenance' => 503,
                'redirect' => 308,
                'mapped' => 400,
                'emergency' => 500,
                'redirect-failure' => 400,
                'escaped' => 'unknown',
                'boundary-failure' => 'unknown',
                'finalizer-failure' => 'unknown',
            ] as $case => $status
        ) {
            yield $case => [$case, $status, true, false, false];
            if (\is_int($status)) {
                yield $case . '-hardening-disabled' => [$case, $status, false, false, false];
            }
        }
        yield 'finalizer-failure-with-identities' => ['finalizer-failure', 'unknown', true, true, true];
    }

    /** @param list<string> $events */
    private function runtime(
        ContextStore $context,
        string $outcome,
        bool $requestIdEnabled,
        array &$events,
    ): KernelRuntime {
        $after = $this->createMock(AfterUowHookInterface::class);
        $after->expects(self::once())->method('afterUow')->willReturnCallback(
            static function (array $hookContext, array $result) use (
                $context,
                $outcome,
                $requestIdEnabled,
                &$events,
            ): void {
                self::assertSame('http', $context->get(ContextKeys::UOW_TYPE));
                self::assertSame($outcome, $result['outcome']);
                self::assertSame($requestIdEnabled, $context->has(ContextKeys::REQUEST_ID));
                $events[] = 'after';
            },
        );
        $probe = $this->createMock(ResetInterface::class);
        $probe->expects(self::once())->method('reset')->willReturnCallback(
            static function () use ($context, &$events): void {
                self::assertSame('http', $context->get(ContextKeys::UOW_TYPE));
                $events[] = 'reset';
            },
        );
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static fn (string $id): mixed => match ($id) {
            ContextStore::class => $context,
            'App\\AfterHook' => $after,
            'App\\ResetProbe' => $probe,
            default => throw new \LogicException('unexpected-lifecycle-service'),
        });
        $container->method('has')->willReturn(true);
        $tags = new TagRegistry();
        $tags->add(ReservedTags::KERNEL_HOOK_AFTER_UOW, 'App\\AfterHook');
        $reset = new ResetOrchestrator($container, $tags);
        $tags->add(ReservedTags::KERNEL_STATEFUL, ContextStore::class);
        $tags->add(ReservedTags::KERNEL_STATEFUL, 'App\\ResetProbe');
        $tags->add($reset->effectiveResetTag(), 'App\\ResetProbe', 100);
        $tags->add($reset->effectiveResetTag(), ContextStore::class);

        return new KernelRuntime(
            contextStore: $context,
            resetOrchestrator: $reset,
            stopwatch: new Stopwatch(),
            uowIds: new UlidGenerator(),
            correlationIdProvider: new CorrelationIdProvider($context),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker($container, $tags),
            logger: new NoopLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );
    }

    /**
     * Synthetic immutable PSR-7 port fixture; only successful mutations create the next response.
     * @param array<string, string> $headers
     * @param list<string> $mutations
     */
    private function response(
        int $status,
        StreamInterface $body,
        array $headers,
        array &$mutations,
        ?\Throwable $failure,
    ): ResponseInterface {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($body);
        $response->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => $headers[$name] ?? '',
        );
        $response->expects(self::never())->method('withAddedHeader');
        $response->expects(self::never())->method('withoutHeader');
        $response->expects(self::never())->method('withStatus');
        $response->method('withHeader')->willReturnCallback(
            function (string $name, mixed $value) use (
                $status,
                $body,
                $headers,
                &$mutations,
                $failure,
            ): ResponseInterface {
                $mutations[] = $name;
                if ($failure !== null && $name === 'X-Content-Type-Options') {
                    throw $failure;
                }
                self::assertIsString($value);
                $headers[$name] = $value;

                return $this->response($status, $body, $headers, $mutations, $failure);
            },
        );
        $response->method('withBody')->willReturnCallback(
            function (StreamInterface $replacement) use ($status, $headers, &$mutations, $failure): ResponseInterface {
                return $this->response($status, $replacement, $headers, $mutations, $failure);
            },
        );

        return $response;
    }
}
