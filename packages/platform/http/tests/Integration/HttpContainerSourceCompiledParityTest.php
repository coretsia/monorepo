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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Id\UuidGenerator;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Console\MaintenanceDisableCommand;
use Coretsia\Platform\Http\Console\MaintenanceEnableCommand;
use Coretsia\Platform\Http\Console\MaintenanceStatusCommand;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\HttpKernelInterface;
use Coretsia\Platform\Http\HttpsRedirect\HttpsRedirectPolicy;
use Coretsia\Platform\Http\Maintenance\MaintenanceMessage;
use Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\HttpsRedirectMiddleware;
use Coretsia\Platform\Http\Middleware\JsonBodyParserMiddleware;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
use Coretsia\Platform\Http\Middleware\RequestContextMiddleware;
use Coretsia\Platform\Http\Middleware\TraceContextMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedHostMiddleware;
use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use Coretsia\Platform\Http\Observability\HttpAccessLogWriter;
use Coretsia\Platform\Http\Observability\HttpRequestMetricsRecorder;
use Coretsia\Platform\Http\Observability\HttpRequestSpanRecorder;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\Routing\FallbackRouterHandler;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class HttpContainerSourceCompiledParityTest extends TestCase
{
    /** @var list<class-string> */
    private const array COMPLETION_SERVICES = [
        HttpResponseFinalizer::class,
        HttpRequestMetricsRecorder::class,
        HttpAccessLogWriter::class,
        HttpRequestSpanRecorder::class,
    ];

    /** @var list<class-string> */
    private const array MAINTENANCE_COMMANDS = [
        MaintenanceEnableCommand::class,
        MaintenanceDisableCommand::class,
        MaintenanceStatusCommand::class,
    ];

    #[DataProvider('requestIdConfigurations')]
    public function testCanonicalSourceAndCompiledContainersResolveEquivalentHttpRuntimeAndTerminal(
        bool $requestIdEnabled,
    ): void {
        $config = self::requestIdConfig($requestIdEnabled);
        $config['http']['tracing']['enabled'] = false;
        $config['http']['context']['enrich']['enabled'] = false;
        $config['http']['request']['json']['enabled'] = false;
        $config['http']['correlation']['enabled'] = false;

        [$source, $compiled] = self::containers(self::definitions($config), $config);
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(0);
        $stream->method('eof')->willReturn(true);
        HttpParityExternalFactory::$stream = $stream;

        try {
            foreach ([$source, $compiled] as $container) {
                $context = $container->get(ContextStore::class);
                $policy = $container->get(RequestIdPolicy::class);

                self::assertInstanceOf(ContextStore::class, $context);
                self::assertInstanceOf(RequestIdPolicy::class, $policy);
                self::assertSame($context, $container->get(ContextAccessorInterface::class));
                self::assertSame($policy, $container->get(RequestIdPolicy::class));
                self::assertInstanceOf(UuidGenerator::class, $container->get(IdGeneratorInterface::class));
                self::assertSame(
                    $container->get(UlidGenerator::class),
                    new \ReflectionProperty(RequestIdPolicy::class, 'generator')->getValue($policy),
                );
                self::assertSame(
                    $context,
                    new \ReflectionProperty(RequestIdPolicy::class, 'context')->getValue($policy),
                );

                foreach (
                    [
                        'enabled' => $requestIdEnabled,
                        'headerName' => $config['http']['request_id']['header_name'],
                        'acceptFromUpstream' => $config['http']['request_id']['accept_from_upstream'],
                        'maxLength' => $config['http']['request_id']['max_length'],
                    ] as $property => $expected
                ) {
                    self::assertTrue(
                        new \ReflectionProperty(RequestIdPolicy::class, $property)->getValue($policy) === $expected,
                        'request-id-configuration-mismatch:' . $property,
                    );
                }

                self::assertFalse($context->has(ContextKeys::REQUEST_ID));

                $request = $this->createStub(ServerRequestInterface::class);
                $request->method('getMethod')->willReturn('GET');
                $request->method('getBody')->willReturn($stream);
                $request->method('getHeader')->willReturn([]);
                $request->method('hasHeader')->willReturn(false);

                $response = $this->createMock(ResponseInterface::class);
                $response->method('getStatusCode')->willReturn(404);
                $response->method('withBody')->willReturnSelf();
                $response->expects(self::never())->method('withAddedHeader');
                $response->expects(self::never())->method('withoutHeader');
                HttpParityExternalFactory::$response = $response;

                $emittedRequestId = null;
                $expectedHeader = $config['http']['request_id']['header_name'];
                $finalized = $response;

                if ($requestIdEnabled) {
                    $finalized = $this->createMock(ResponseInterface::class);
                    $finalized->method('getStatusCode')->willReturn(404);
                    $finalized->expects(self::never())->method('withHeader');
                    $finalized->expects(self::never())->method('withAddedHeader');
                    $finalized->expects(self::never())->method('withoutHeader');
                    $finalized->method('getHeaderLine')->willReturnCallback(
                        static function (string $name) use ($expectedHeader, &$emittedRequestId): string {
                            return $name === $expectedHeader ? ($emittedRequestId ?? '') : '';
                        },
                    );

                    $response->expects(self::once())->method('withHeader')->willReturnCallback(
                        static function (string $name, mixed $value) use (
                            $context,
                            $policy,
                            $expectedHeader,
                            $finalized,
                            &$emittedRequestId,
                        ): ResponseInterface {
                            self::assertTrue($name === $expectedHeader, 'request-id-header-name-mismatch');
                            self::assertTrue(
                                \is_string($value)
                                && \preg_match('/\A[0-7][0-9A-HJKMNP-TV-Z]{25}\z/D', $value) === 1,
                                'request-id-not-canonical-ulid',
                            );
                            self::assertTrue(
                                $value === $context->get(ContextKeys::REQUEST_ID),
                                'request-id-context-header-mismatch',
                            );
                            self::assertTrue($value === $policy->current(), 'request-id-policy-header-mismatch');

                            $emittedRequestId = $value;

                            return $finalized;
                        },
                    );
                } else {
                    $response->expects(self::never())->method('withHeader');
                }

                $terminal = $container->get(RequestHandlerInterface::class);
                self::assertInstanceOf(FallbackRouterHandler::class, $terminal);
                self::assertSame($response, $terminal->handle($request));
                self::assertInstanceOf(
                    HttpMiddlewareStackBuilder::class,
                    $container->get(HttpMiddlewareStackBuilder::class),
                );
                self::assertInstanceOf(Pipeline::class, $container->get(Pipeline::class));

                foreach (self::COMPLETION_SERVICES as $id) {
                    self::assertInstanceOf($id, $container->get($id));
                }

                $kernel = $container->get(HttpKernelInterface::class);
                $finalizer = $container->get(HttpResponseFinalizer::class);

                self::assertInstanceOf(HttpKernel::class, $kernel);
                self::assertInstanceOf(HttpResponseFinalizer::class, $finalizer);
                self::assertSame($kernel, $container->get(HttpKernel::class));
                self::assertSame(
                    $policy,
                    new \ReflectionProperty(HttpKernel::class, 'requestIds')->getValue($kernel),
                );
                self::assertSame(
                    $policy,
                    new \ReflectionProperty(HttpResponseFinalizer::class, 'requestIds')->getValue($finalizer),
                );
                $securityHeaders = $container->get(SecurityHeadersPolicy::class);

                self::assertInstanceOf(SecurityHeadersPolicy::class, $securityHeaders);
                self::assertSame($securityHeaders, $container->get(SecurityHeadersPolicy::class));
                self::assertSame(
                    $securityHeaders,
                    new \ReflectionProperty(HttpResponseFinalizer::class, 'securityHeaders')->getValue($finalizer),
                );
                self::assertSame([], $securityHeaders->headers());
                self::assertSame(
                    $finalizer,
                    new \ReflectionProperty(HttpKernel::class, 'finalizer')->getValue($kernel),
                );

                $actual = $kernel->handle($request);
                self::assertTrue(
                    $actual === $finalized,
                    'http-finalized-response-identity-mismatch',
                );

                if ($requestIdEnabled) {
                    self::assertTrue(
                        \is_string($emittedRequestId)
                        && $actual->getHeaderLine($expectedHeader) === $emittedRequestId,
                        'request-id-final-response-mismatch',
                    );
                } else {
                    self::assertTrue($emittedRequestId === null, 'disabled-request-id-header-emitted');
                    self::assertFalse($context->has(ContextKeys::REQUEST_ID));
                }

                $runtime = $container->get(KernelRuntimeInterface::class);
                self::assertInstanceOf(HttpParityKernelRuntime::class, $runtime);
                self::assertSame(1, $runtime->begins);
                self::assertSame(1, $runtime->completions);
                self::assertSame('handled_error', $runtime->lastOutcome);
                self::assertSame(404, $actual->getStatusCode());
            }
        } finally {
            HttpParityExternalFactory::$response = null;
            HttpParityExternalFactory::$stream = null;
        }
    }

    #[DataProvider('hardeningConfigurations')]
    public function testSourceAndCompiledContainersResolveEquivalentHardeningBehavior(
        bool $securityHeadersEnabled,
        bool $httpsRedirectEnabled,
    ): void {
        $config = self::requestIdConfig(false);
        $config['http']['tracing']['enabled'] = false;
        $config['http']['context']['enrich']['enabled'] = false;
        $config['http']['request']['json']['enabled'] = false;
        $config['http']['correlation']['enabled'] = false;

        $config['http']['security_headers'] = [
            'enabled' => $securityHeadersEnabled,
            'x_content_type_options' => 'nosniff',
            'x_frame_options' => 'sameorigin',
            'referrer_policy' => 'strict-origin',
            'permissions_policy' => 'geolocation=()',
            'csp' => "default-src 'none'",
        ];
        $config['http']['https_redirect'] = [
            'enabled' => $httpsRedirectEnabled,
            'status' => 307,
            'allow_methods' => ['GET'],
            'target_authority' => 'example.test:8443',
        ];

        [$source, $compiled] = self::containers(self::definitions($config), $config);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(0);
        $stream->method('eof')->willReturn(true);

        HttpParityExternalFactory::$stream = $stream;

        try {
            foreach ([$source, $compiled] as $container) {
                $securityHeaders = $container->get(SecurityHeadersPolicy::class);
                $httpsRedirect = $container->get(HttpsRedirectPolicy::class);
                $middleware = $container->get(HttpsRedirectMiddleware::class);

                self::assertInstanceOf(SecurityHeadersPolicy::class, $securityHeaders);
                self::assertInstanceOf(HttpsRedirectPolicy::class, $httpsRedirect);
                self::assertInstanceOf(HttpsRedirectMiddleware::class, $middleware);

                self::assertSame($securityHeaders, $container->get(SecurityHeadersPolicy::class));
                self::assertSame($httpsRedirect, $container->get(HttpsRedirectPolicy::class));
                self::assertSame($middleware, $container->get(HttpsRedirectMiddleware::class));

                self::assertSame(
                    $httpsRedirect,
                    new \ReflectionProperty(HttpsRedirectMiddleware::class, 'policy')->getValue($middleware),
                );

                $finalizer = $container->get(HttpResponseFinalizer::class);
                self::assertInstanceOf(HttpResponseFinalizer::class, $finalizer);
                self::assertSame($finalizer, $container->get(HttpResponseFinalizer::class));

                self::assertSame(
                    $securityHeaders,
                    new \ReflectionProperty(HttpResponseFinalizer::class, 'securityHeaders')->getValue($finalizer),
                );

                self::assertSame($httpsRedirectEnabled, $httpsRedirect->enabled());
                self::assertSame(307, $httpsRedirect->status());
                self::assertTrue($httpsRedirect->allowsMethod('GET'));
                self::assertFalse($httpsRedirect->allowsMethod('HEAD'));

                $expectedSecurityHeaders = $securityHeadersEnabled ? [
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'Referrer-Policy' => 'strict-origin',
                    'Permissions-Policy' => 'geolocation=()',
                    'Content-Security-Policy' => "default-src 'none'",
                ] : [];

                self::assertSame($expectedSecurityHeaders, $securityHeaders->headers());

                $pipeline = $container->get(Pipeline::class);
                self::assertInstanceOf(Pipeline::class, $pipeline);

                $resolvedMiddleware = new \ReflectionProperty(Pipeline::class, 'middleware')->getValue($pipeline);

                self::assertIsArray($resolvedMiddleware);
                self::assertSame(
                    $httpsRedirectEnabled,
                    \in_array($middleware, $resolvedMiddleware, true),
                );

                $uri = $this->createStub(UriInterface::class);
                $uri->method('getScheme')->willReturn('http');
                $uri->method('getPath')->willReturn('/resource%20name');
                $uri->method('getQuery')->willReturn('page=1');

                $request = $this->createStub(ServerRequestInterface::class);
                $request->method('getMethod')->willReturn('GET');
                $request->method('getUri')->willReturn($uri);
                $request->method('getBody')->willReturn($stream);
                $request->method('getHeader')->willReturn([]);
                $request->method('hasHeader')->willReturn(false);

                $status = $httpsRedirectEnabled ? 307 : 404;
                $expectedHeaders = $httpsRedirectEnabled ? [
                    'Location' => 'https://example.test:8443/resource%20name?page=1',
                ] : [];
                $expectedHeaders += $expectedSecurityHeaders;

                $response = $this->createMock(ResponseInterface::class);
                $response->method('getStatusCode')->willReturn($status);
                $response->expects(self::never())->method('withAddedHeader');
                $response->expects(self::never())->method('withoutHeader');
                $response->expects($httpsRedirectEnabled ? self::never() : self::once())
                    ->method('withBody')
                    ->with(self::identicalTo($stream))
                    ->willReturnSelf();

                $expectedMutations = [];
                foreach ($expectedHeaders as $name => $value) {
                    $expectedMutations[] = [$name, $value];
                }

                $index = 0;
                $response->expects(self::exactly(\count($expectedMutations)))
                    ->method('withHeader')
                    ->willReturnCallback(static function (string $name, mixed $value) use (
                        $expectedMutations,
                        $response,
                        &$index,
                    ): ResponseInterface {
                        self::assertTrue(isset($expectedMutations[$index]), 'unexpected-hardening-mutation');
                        self::assertTrue($name === $expectedMutations[$index][0], 'hardening-header-name-mismatch');
                        self::assertTrue($value === $expectedMutations[$index][1], 'hardening-header-value-mismatch');
                        ++$index;

                        return $response;
                    });

                HttpParityExternalFactory::$response = $response;

                $factory = $container->get(ResponseFactoryInterface::class);
                self::assertInstanceOf(HttpParityExternalFactory::class, $factory);

                $runtime = $container->get(KernelRuntimeInterface::class);
                self::assertInstanceOf(HttpParityKernelRuntime::class, $runtime);
                self::assertSame(0, $runtime->begins);

                $kernel = $container->get(HttpKernelInterface::class);
                self::assertInstanceOf(HttpKernel::class, $kernel);

                self::assertSame($response, $kernel->handle($request));
                self::assertSame($status, $factory->lastResponseCode);
                self::assertSame($status, $response->getStatusCode());
                self::assertSame(\count($expectedMutations), $index);
                self::assertSame(1, $runtime->begins);
                self::assertSame(1, $runtime->completions);
                self::assertSame($httpsRedirectEnabled ? 'success' : 'handled_error', $runtime->lastOutcome);
            }
        } finally {
            HttpParityExternalFactory::$response = null;
            HttpParityExternalFactory::$stream = null;
        }
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function hardeningConfigurations(): iterable
    {
        yield 'both-disabled' => [false, false];
        yield 'security-headers-only' => [true, false];
        yield 'https-redirect-only' => [false, true];
        yield 'both-enabled' => [true, true];
    }

    #[DataProvider('hardeningDescriptorConfigurations')]
    public function testProviderDescriptorsAndTagsHaveOneCanonicalSourceForBothContainerModes(
        bool $requestIdEnabled,
        bool $securityHeadersEnabled,
        bool $httpsRedirectEnabled,
    ): void {
        $config = self::requestIdConfig($requestIdEnabled);
        $config['http']['maintenance']['enabled'] = true;
        $config['http']['maintenance']['retry_after_default_seconds'] = 120;
        $config['http']['security_headers']['enabled'] = $securityHeadersEnabled;
        $config['http']['https_redirect']['enabled'] = $httpsRedirectEnabled;
        $config['http']['https_redirect']['target_authority'] = 'example.test:8443';
        $set = self::definitions($config);
        $operations = $set->toDescriptorStream();
        $graph = self::compiler()->compile($set)->toArray();
        self::assertNotEmpty($graph['services']);
        self::assertContains(RuntimePathContext::class, $set->requiredServiceIds());
        self::assertArrayNotHasKey(RuntimePathContext::class, $graph['services']);
        self::assertArrayNotHasKey(RuntimePathContext::class, $graph['aliases']);

        $expected = [
            RequestIdPolicy::class,
            SecurityHeadersPolicy::class,
            HttpsRedirectPolicy::class,
            HttpsRedirectMiddleware::class,
            ScopedFileLock::class,
            MaintenanceStateStore::class,
            MaintenanceMiddleware::class,
            ...self::MAINTENANCE_COMMANDS,
            HttpKernel::class,
            ...self::COMPLETION_SERVICES,
        ];

        $services = [];
        $tags = [];
        $cliTags = [];
        $lifecycleTags = [];
        $contextStores = [];

        foreach ($operations as $operation) {
            $kind = $operation['kind'] ?? null;
            if (\in_array($kind, ['service.class', 'service.factory.class-method'], true)) {
                if (\in_array($operation['id'], $expected, true)) {
                    self::assertArrayNotHasKey($operation['id'], $services);
                    $services[$operation['id']] = $operation;
                }

                if ($kind === 'service.class' && $operation['class'] === ContextStore::class) {
                    $contextStores[] = $operation['id'];
                }
            }

            if (($operation['kind'] ?? null) !== 'tag') {
                continue;
            }

            if (\str_starts_with($operation['tag'], 'http.middleware.')) {
                $tags[] = $operation;
                self::assertNotContains($operation['serviceId'], self::COMPLETION_SERVICES);
                self::assertNotSame(HttpKernel::class, $operation['serviceId']);
                self::assertNotSame(RequestIdPolicy::class, $operation['serviceId']);
                self::assertNotSame(SecurityHeadersPolicy::class, $operation['serviceId']);
                self::assertNotSame(HttpsRedirectPolicy::class, $operation['serviceId']);
                self::assertNotSame(ScopedFileLock::class, $operation['serviceId']);
                self::assertNotSame(MaintenanceStateStore::class, $operation['serviceId']);
                self::assertNotContains($operation['serviceId'], self::MAINTENANCE_COMMANDS);
            }

            if ($operation['tag'] === ReservedTags::CLI_COMMAND) {
                $id = $operation['serviceId'];

                self::assertArrayNotHasKey($id, $cliTags);

                $cliTags[$id] = [
                    'id' => $id,
                    'meta' => $operation['meta'],
                    'priority' => $operation['priority'],
                ];
            }

            if (\in_array(
                $operation['tag'],
                [ReservedTags::KERNEL_RESET, ReservedTags::KERNEL_STATEFUL],
                true,
            )) {
                $lifecycleTags[] = [$operation['tag'], $operation['serviceId']];
            }
        }

        self::assertCount(15, $services);
        self::assertCount(8, $tags);
        self::assertCount(3, $cliTags);
        self::assertSame(
            [
                [TraceContextMiddleware::class, 930, ['toggle' => 'http.tracing.enabled']],
                [MaintenanceMiddleware::class, 900, ['toggle' => 'http.maintenance.enabled']],
                [TrustedProxyMiddleware::class, 880, ['toggle' => 'http.proxy.enabled']],
                [RequestContextMiddleware::class, 870, ['toggle' => 'http.context.enrich.enabled']],
                [TrustedHostMiddleware::class, 860, ['toggle' => 'http.hosts.enabled']],
                [HttpsRedirectMiddleware::class, 850, ['toggle' => 'http.https_redirect.enabled']],
                [RequestBodySizeLimitMiddleware::class, 830, []],
                [JsonBodyParserMiddleware::class, 800, ['toggle' => 'http.request.json.enabled']],
            ],
            \array_map(
                static fn (array $tag): array => [$tag['serviceId'], $tag['priority'], $tag['meta']],
                $tags,
            ),
        );

        foreach ($tags as $tag) {
            self::assertSame(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, $tag['tag']);
        }

        $expectedCliTags = [];

        foreach (self::MAINTENANCE_COMMANDS as $id) {
            $options = $id::OPTIONS;

            foreach ($options as &$option) {
                \ksort($option, SORT_STRING);
            }
            unset($option);

            $expectedCliTags[$id] = [
                'id' => $id,
                'meta' => [
                    'arguments' => $id::ARGUMENTS,
                    'group' => $id::GROUP,
                    'hidden' => $id::HIDDEN,
                    'name' => $id::NAME,
                    'options' => $options,
                    'summary' => $id::SUMMARY,
                ],
                'priority' => 0,
            ];
        }

        self::assertSame($expectedCliTags, $cliTags);

        \ksort($expectedCliTags, SORT_STRING);

        self::assertSame(
            \array_values($expectedCliTags),
            $graph['tags'][ReservedTags::CLI_COMMAND],
        );

        self::assertSame([ContextStore::class], $contextStores);
        self::assertSame([
            [ReservedTags::KERNEL_RESET, ContextStore::class],
            [ReservedTags::KERNEL_STATEFUL, ContextStore::class],
        ], $lifecycleTags);

        foreach ($services as $id => $service) {
            if ($service['kind'] === 'service.factory.class-method') {
                self::assertSame('factory', $graph['services'][$id]['type']);
                self::assertSame([
                    'factory' => [
                        'class' => $service['factoryClass'],
                        'kind' => 'class-method',
                        'method' => $service['method'],
                    ],
                ], $graph['services'][$id]['construction']);
            } else {
                self::assertSame('class', $graph['services'][$id]['type']);
                self::assertSame([
                    'class' => $service['class'],
                ], $graph['services'][$id]['construction']);
            }

            self::assertTrue($service['shared']);
            self::assertTrue($graph['services'][$id]['shared']);
            self::assertSame($service['arguments'], $graph['services'][$id]['arguments']);
        }

        $ref = static fn (string $id): array => ContainerValueReference::service($id)->toArray();
        $requestId = $config['http']['request_id'];

        $maintenanceArguments = [
            ScopedFileLock::class => [],
            MaintenanceStateStore::class => [
                $ref(RuntimePathContext::class),
                $ref(ScopedFileLock::class),
            ],
            MaintenanceMiddleware::class => [
                $ref(MaintenanceStateStore::class),
                $ref(ResponseFactoryInterface::class),
                $ref(StreamFactoryInterface::class),
                $ref(MeterPortInterface::class),
                $ref(LoggerInterface::class),
                $config['http']['maintenance']['enabled'],
                $config['http']['maintenance']['retry_after_default_seconds'],
                $config['http']['metrics']['enabled'],
                $config['http']['access_log']['enabled'],
            ],
            MaintenanceEnableCommand::class => [
                $ref(MaintenanceStateStore::class),
            ],
            MaintenanceDisableCommand::class => [
                $ref(MaintenanceStateStore::class),
            ],
            MaintenanceStatusCommand::class => [
                $ref(MaintenanceStateStore::class),
            ],
        ];

        foreach ($maintenanceArguments as $id => $arguments) {
            self::assertSame([
                'arguments' => $arguments,
                'class' => $id,
                'id' => $id,
                'kind' => 'service.class',
                'shared' => true,
            ], $services[$id]);
        }

        self::assertSame(RequestIdPolicy::class, $services[RequestIdPolicy::class]['class']);
        self::assertSame([
            $ref(ContextStore::class),
            $ref(UlidGenerator::class),
            $requestId['enabled'],
            $requestId['header_name'],
            $requestId['accept_from_upstream'],
            $requestId['max_length'],
        ], $services[RequestIdPolicy::class]['arguments']);

        foreach (
            [
                SecurityHeadersPolicy::class => 'securityHeaders',
                HttpsRedirectPolicy::class => 'httpsRedirect',
            ] as $id => $method
        ) {
            self::assertSame('service.factory.class-method', $services[$id]['kind']);
            self::assertSame(HttpServiceFactory::class, $services[$id]['factoryClass']);
            self::assertSame($method, $services[$id]['method']);
            self::assertSame([
                $ref(ConfigRepositoryInterface::class),
            ], $services[$id]['arguments']);
        }

        self::assertSame(
            HttpsRedirectMiddleware::class,
            $services[HttpsRedirectMiddleware::class]['class'],
        );

        self::assertSame([
            $ref(HttpsRedirectPolicy::class),
            $ref(ResponseFactoryInterface::class),
            $ref(MeterPortInterface::class),
            $ref(LoggerInterface::class),
            $config['http']['proxy']['enabled'],
            $config['http']['metrics']['enabled'],
            $config['http']['access_log']['enabled'],
        ], $services[HttpsRedirectMiddleware::class]['arguments']);

        self::assertSame([
            $ref(CorrelationIdProviderInterface::class),
            true,
            'X-Correlation-Id',
            $ref(RequestIdPolicy::class),
            $ref(SecurityHeadersPolicy::class),
        ], $services[HttpResponseFinalizer::class]['arguments']);

        self::assertSame([
            $ref(KernelRuntimeInterface::class),
            $ref(Stopwatch::class),
            $ref(Pipeline::class),
            ...\array_map($ref, self::COMPLETION_SERVICES),
            $ref(RequestIdPolicy::class),
        ], $services[HttpKernel::class]['arguments']);

        self::assertSame(UuidGenerator::class, $graph['aliases'][IdGeneratorInterface::class]);
        self::assertSame(ContextStore::class, $graph['aliases'][ContextAccessorInterface::class]);

        $compiledContextStores = [];
        foreach ($graph['services'] as $id => $service) {
            if (($service['construction']['class'] ?? null) === ContextStore::class) {
                $compiledContextStores[] = $id;
            }
        }

        self::assertSame([ContextStore::class], $compiledContextStores);

        $compiledMiddlewareTags = \array_filter(
            $graph['tags'],
            static fn (string $tag): bool => \str_starts_with($tag, 'http.middleware.'),
            ARRAY_FILTER_USE_KEY,
        );

        self::assertSame([
            ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE => \array_map(
                static fn (array $tag): array => [
                    'id' => $tag['serviceId'],
                    'meta' => $tag['meta'],
                    'priority' => $tag['priority'],
                ],
                $tags,
            ),
        ], $compiledMiddlewareTags);

        $expectedContextStoreRegistration = [
            [
                'id' => ContextStore::class,
                'meta' => [],
                'priority' => 0,
            ],
        ];

        self::assertSame(
            $expectedContextStoreRegistration,
            $graph['tags'][ReservedTags::KERNEL_RESET],
        );
        self::assertSame(
            $expectedContextStoreRegistration,
            $graph['tags'][ReservedTags::KERNEL_STATEFUL],
        );

        $opposite = $config;
        $opposite['http']['request_id']['enabled'] = !$requestIdEnabled;
        $opposite['http']['security_headers']['enabled'] = !$securityHeadersEnabled;
        $opposite['http']['https_redirect']['enabled'] = !$httpsRedirectEnabled;
        $opposite['http']['maintenance']['enabled'] = false;
        $oppositeGraph = self::compiler()->compile(self::definitions($opposite))->toArray();

        self::assertSame(\array_keys($graph['services']), \array_keys($oppositeGraph['services']));
        self::assertSame($graph['aliases'], $oppositeGraph['aliases']);
        self::assertSame($graph['parameters'], $oppositeGraph['parameters']);
        self::assertSame($graph['tags'], $oppositeGraph['tags']);

        $expectedServices = $graph['services'];
        $expectedServices[RequestIdPolicy::class]['arguments'][2] = !$requestIdEnabled;
        $expectedServices[MaintenanceMiddleware::class]['arguments'][5] = false;

        self::assertSame($expectedServices, $oppositeGraph['services']);

        self::assertSame(HttpParityExternalFactory::class, $graph['aliases'][ResponseFactoryInterface::class]);
        self::assertSame(HttpParityExternalFactory::class, $graph['aliases'][StreamFactoryInterface::class]);
    }

    /** @return iterable<string, array{bool, bool, bool}> */
    public static function hardeningDescriptorConfigurations(): iterable
    {
        foreach ([false, true] as $requestIdEnabled) {
            foreach ([false, true] as $securityHeadersEnabled) {
                foreach ([false, true] as $httpsRedirectEnabled) {
                    yield \sprintf(
                        'request-id-%d-security-headers-%d-https-redirect-%d',
                        $requestIdEnabled,
                        $securityHeadersEnabled,
                        $httpsRedirectEnabled,
                    ) => [$requestIdEnabled, $securityHeadersEnabled, $httpsRedirectEnabled];
                }
            }
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function requestIdConfigurations(): iterable
    {
        yield 'request-id-disabled' => [false];
        yield 'request-id-enabled' => [true];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('factorySelections')]
    public function testAllExplicitFactorySelectionsContributeOnlyTheirSelectedFactoryDescriptors(
        string $selection,
        string $responseClass,
        string $streamClass,
    ): void {
        foreach (HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES as $classes) {
            foreach (\array_unique([$classes['response'], $classes['stream']]) as $class) {
                if (!\class_exists($class)) {
                    \class_alias(HttpParityExternalFactory::class, $class);
                }
            }
        }

        $config = self::config($selection);
        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext($config));
        $operations = $definitions->build()->toDescriptorStream();
        $factoryServices = [];
        $aliases = [];
        $known = [];
        foreach (HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES as $classes) {
            $known[$classes['response']] = true;
            $known[$classes['stream']] = true;
        }
        foreach ($operations as $operation) {
            if (($operation['kind'] ?? null) === 'service.class' && isset($known[$operation['id']])) {
                $factoryServices[] = $operation['id'];
            }
            if (($operation['kind'] ?? null) === 'alias'
                && \in_array(
                    $operation['alias'],
                    [ResponseFactoryInterface::class, StreamFactoryInterface::class],
                    true,
                )
            ) {
                $aliases[$operation['alias']] = $operation['serviceId'];
            }
        }
        self::assertSame(\array_unique([$responseClass, $streamClass]), $factoryServices);
        self::assertSame([
            ResponseFactoryInterface::class => $responseClass,
            StreamFactoryInterface::class => $streamClass,
        ], $aliases);
        self::assertTrue(\is_subclass_of($responseClass, ResponseFactoryInterface::class));
        self::assertTrue(\is_subclass_of($streamClass, StreamFactoryInterface::class));
        $graph = self::compiler()->compile(self::definitions($config))->toArray();
        self::assertSame($responseClass, $graph['aliases'][ResponseFactoryInterface::class]);
        self::assertSame($streamClass, $graph['aliases'][StreamFactoryInterface::class]);

        [$source, $compiled] = self::containers(self::definitions($config), $config);
        foreach ([$source, $compiled] as $container) {
            $responses = $container->get(ResponseFactoryInterface::class);
            $streams = $container->get(StreamFactoryInterface::class);
            self::assertInstanceOf(ResponseFactoryInterface::class, $responses);
            self::assertInstanceOf(StreamFactoryInterface::class, $streams);

            if ($responseClass === $streamClass) {
                self::assertSame($responses, $streams);
            } else {
                self::assertNotSame($responses, $streams);
            }
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function factorySelections(): iterable
    {
        yield 'nyholm-shared' => [
            'nyholm',
            'Nyholm\\Psr7\\Factory\\Psr17Factory',
            'Nyholm\\Psr7\\Factory\\Psr17Factory',
        ];
        yield 'guzzlehttp-shared' => ['guzzlehttp', 'GuzzleHttp\\Psr7\\HttpFactory', 'GuzzleHttp\\Psr7\\HttpFactory'];
        yield 'laminas-distinct' => [
            'laminas',
            'Laminas\\Diactoros\\ResponseFactory',
            'Laminas\\Diactoros\\StreamFactory',
        ];
        yield 'slim-distinct' => ['slim', 'Slim\\Psr7\\Factory\\ResponseFactory', 'Slim\\Psr7\\Factory\\StreamFactory'];
    }

    public function testCanonicalHttpServicesCannotRetainMutableRequestLifecycleState(): void
    {
        $expectedProperties = [
            HttpKernel::class => [
                'runtime',
                'stopwatch',
                'pipeline',
                'finalizer',
                'metrics',
                'accessLog',
                'spans',
                'requestIds',
            ],
            HttpResponseFinalizer::class => [
                'correlationIds',
                'enabled',
                'headerName',
                'requestIds',
                'securityHeaders',
            ],
            SecurityHeadersPolicy::class => ['headers'],
            HttpsRedirectPolicy::class => [
                'allowedMethods',
                'enabled',
                'status',
                'targetAuthority',
            ],
            HttpsRedirectMiddleware::class => [
                'policy',
                'responses',
                'meter',
                'logger',
                'proxyEnabled',
                'metricsEnabled',
                'accessLogEnabled',
            ],
            ScopedFileLock::class => [],
            MaintenanceStateStore::class => ['paths', 'locks'],
            MaintenanceMiddleware::class => [
                'state',
                'responses',
                'streams',
                'meter',
                'logger',
                'enabled',
                'retryAfterDefaultSeconds',
                'metricsEnabled',
                'accessLogEnabled',
            ],
            MaintenanceEnableCommand::class => ['stateStore'],
            MaintenanceDisableCommand::class => ['stateStore'],
            MaintenanceStatusCommand::class => ['stateStore'],
            RequestIdPolicy::class => [
                'context',
                'generator',
                'enabled',
                'headerName',
                'acceptFromUpstream',
                'maxLength',
            ],
            HttpRequestMetricsRecorder::class => ['meter', 'enabled'],
            HttpAccessLogWriter::class => ['logger', 'enabled'],
            HttpRequestSpanRecorder::class => ['tracer', 'enabled'],
            Pipeline::class => ['middleware', 'terminal'],
        ];

        foreach ($expectedProperties as $class => $names) {
            $reflection = new \ReflectionClass($class);
            self::assertTrue($reflection->isReadOnly());
            $properties = $reflection->getProperties();
            self::assertSame(
                $names,
                \array_map(
                    static fn (\ReflectionProperty $property): string => $property->getName(),
                    $properties,
                ),
            );
            foreach ($properties as $property) {
                self::assertFalse($property->isStatic());
            }
        }
    }

    public function testRequestIdPolicyIsAMandatoryConstructorDependencyOfBothHttpOwners(): void
    {
        foreach ([HttpKernel::class, HttpResponseFinalizer::class] as $class) {
            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();

            self::assertNotNull($constructor);
            $parameters = [];
            foreach ($constructor->getParameters() as $candidate) {
                $parameters[$candidate->getName()] = $candidate;
            }

            self::assertArrayHasKey('requestIds', $parameters);
            $parameter = $parameters['requestIds'];
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame(RequestIdPolicy::class, $type->getName());
            self::assertFalse($type->allowsNull());
            self::assertFalse($parameter->isOptional());
            self::assertFalse($parameter->isDefaultValueAvailable());
        }
    }

    public function testSecurityHeadersPolicyIsAMandatoryConstructorDependencyOfTheFinalizer(): void
    {
        $constructor = new \ReflectionClass(HttpResponseFinalizer::class)->getConstructor();
        self::assertNotNull($constructor);

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        self::assertArrayHasKey('securityHeaders', $parameters);

        $parameter = $parameters['securityHeaders'];
        $type = $parameter->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(SecurityHeadersPolicy::class, $type->getName());
        self::assertFalse($type->allowsNull());
        self::assertFalse($parameter->isOptional());
        self::assertFalse($parameter->isDefaultValueAvailable());

        self::assertSame(
            $parameters['requestIds']->getPosition() + 1,
            $parameter->getPosition(),
        );
    }

    public function testHardeningServicesHaveOnlyMandatoryConstructorDependencies(): void
    {
        foreach (
            [
                SecurityHeadersPolicy::class,
                HttpsRedirectPolicy::class,
                HttpsRedirectMiddleware::class,
            ] as $class
        ) {
            $reflection = new \ReflectionClass($class);
            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());

            $constructor = $reflection->getConstructor();
            self::assertNotNull($constructor);

            foreach ($constructor->getParameters() as $parameter) {
                self::assertFalse($parameter->allowsNull());
                self::assertFalse($parameter->isOptional());
                self::assertFalse($parameter->isDefaultValueAvailable());
            }
        }
    }

    public function testMaintenanceServicesHaveOnlyMandatoryConstructorDependencies(): void
    {
        foreach (
            [
                MaintenanceMessage::class,
                MaintenanceStateStore::class,
                MaintenanceMiddleware::class,
                ...self::MAINTENANCE_COMMANDS,
            ] as $class
        ) {
            $reflection = new \ReflectionClass($class);

            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());

            $constructor = $reflection->getConstructor();
            self::assertNotNull($constructor);

            foreach ($constructor->getParameters() as $parameter) {
                self::assertFalse($parameter->isOptional());
                self::assertFalse($parameter->isDefaultValueAvailable());

                self::assertSame(
                    $class === MaintenanceMessage::class
                    && $parameter->getName() === 'retryAfterSeconds',
                    $parameter->allowsNull(),
                );
            }
        }
    }

    public function testHardeningPoliciesResolveCanonicalValuesFromRuntimeConfiguration(): void
    {
        $config = self::config();
        $authority = '[2001:db8::1]:8443';
        $csp = 'default-src ' . \str_repeat('https://example.test ', 64) . 'https://other.example';

        $config['http']['security_headers']['enabled'] = true;
        $config['http']['security_headers']['csp'] = $csp;
        $config['http']['https_redirect']['enabled'] = true;
        $config['http']['https_redirect']['target_authority'] = $authority;

        [$source, $compiled] = self::containers(self::definitions($config), $config);

        foreach ([$source, $compiled] as $container) {
            $securityHeaders = $container->get(SecurityHeadersPolicy::class);
            $httpsRedirect = $container->get(HttpsRedirectPolicy::class);

            self::assertInstanceOf(SecurityHeadersPolicy::class, $securityHeaders);
            self::assertInstanceOf(HttpsRedirectPolicy::class, $httpsRedirect);

            self::assertTrue(
                ($securityHeaders->headers()['Content-Security-Policy'] ?? null) === $csp,
                'security-header-policy-value-mismatch',
            );
            self::assertTrue(
                $httpsRedirect->targetAuthority() === $authority,
                'https-redirect-target-authority-mismatch',
            );
        }
    }

    public function testEnabledInvalidRedirectPolicyFailsDuringMiddlewareResolutionBeforeUnitOfWork(): void
    {
        $config = self::config();
        $config['http']['https_redirect']['enabled'] = true;
        $config['http']['https_redirect']['target_authority'] = 'https://example.test';

        [$source, $compiled] = self::containers(self::definitions($config), $config);

        foreach (
            [
                [$source, 'container-definition-factory-failed'],
                [$compiled, 'container-compiled-factory-failed'],
            ] as [$container, $reason]
        ) {
            $runtime = $container->get(KernelRuntimeInterface::class);
            self::assertInstanceOf(HttpParityKernelRuntime::class, $runtime);
            self::assertSame(0, $runtime->begins);
            self::assertSame(0, $runtime->completions);

            try {
                $container->get(Pipeline::class);
                self::fail('invalid-enabled-redirect-policy-must-fail-during-resolution');
            } catch (ContainerException $actual) {
                self::assertSame($reason, $actual->reason());

                if ($container === $source) {
                    $previous = $actual->getPrevious();
                    self::assertInstanceOf(\InvalidArgumentException::class, $previous);
                    self::assertSame('http-https-redirect-target-authority-invalid', $previous->getMessage());
                }
            }

            self::assertSame(0, $runtime->begins);
            self::assertSame(0, $runtime->completions);
        }
    }

    public function testDisabledInvalidRedirectPolicyDoesNotRequireEagerResolution(): void
    {
        $config = self::config();
        $config['http']['https_redirect']['enabled'] = false;
        $config['http']['https_redirect']['target_authority'] = 'https://example.test';

        [$source, $compiled] = self::containers(self::definitions($config), $config);

        foreach (
            [
                [$source, 'container-definition-factory-failed'],
                [$compiled, 'container-compiled-factory-failed'],
            ] as [$container, $reason]
        ) {
            $runtime = $container->get(KernelRuntimeInterface::class);
            self::assertInstanceOf(HttpParityKernelRuntime::class, $runtime);

            $pipeline = $container->get(Pipeline::class);
            self::assertInstanceOf(Pipeline::class, $pipeline);
            self::assertInstanceOf(HttpKernel::class, $container->get(HttpKernelInterface::class));

            $resolvedMiddleware = new \ReflectionProperty(Pipeline::class, 'middleware')->getValue($pipeline);
            self::assertIsArray($resolvedMiddleware);

            foreach ($resolvedMiddleware as $middleware) {
                self::assertNotInstanceOf(HttpsRedirectMiddleware::class, $middleware);
            }

            self::assertSame(0, $runtime->begins);
            self::assertSame(0, $runtime->completions);

            try {
                $container->get(HttpsRedirectPolicy::class);
                self::fail('invalid-redirect-policy-must-fail-when-explicitly-resolved');
            } catch (ContainerException $actual) {
                self::assertSame($reason, $actual->reason());
            }

            self::assertSame(0, $runtime->begins);
            self::assertSame(0, $runtime->completions);
        }
    }

    #[DataProvider('maintenanceConfigurations')]
    public function testSourceAndCompiledContainersObserveIsolatedMaintenanceStateWithoutRebuilding(
        bool $maintenanceEnabled,
    ): void {
        $config = self::config();

        $config['http']['maintenance'] = [
            'enabled' => $maintenanceEnabled,
            'retry_after_default_seconds' => 75,
        ];
        $config['http']['correlation']['enabled'] = false;
        $config['http']['tracing']['enabled'] = false;
        $config['http']['metrics']['enabled'] = false;
        $config['http']['access_log']['enabled'] = false;
        $config['http']['context']['enrich']['enabled'] = false;
        $config['http']['request']['json']['enabled'] = false;

        $set = self::definitions($config);
        $descriptors = $set->toDescriptorStream();
        $graph = self::compiler()->compile($set)->toArray();
        $encodedGraph = \serialize($graph);

        self::assertFalse(
            \str_contains($encodedGraph, 'var/maintenance'),
            'maintenance-state-path-in-compiled-graph',
        );
        self::assertFalse(
            \str_contains($encodedGraph, 'maintenance.lock'),
            'maintenance-lock-path-in-compiled-graph',
        );
        self::assertFalse(
            \str_contains($encodedGraph, 'message.json'),
            'maintenance-message-path-in-compiled-graph',
        );

        $temporaryDirectory = \realpath(\sys_get_temp_dir());
        self::assertIsString($temporaryDirectory);

        $roots = [];
        $fixtures = [];

        try {
            foreach ([0, 1] as $index) {
                $root = \rtrim($temporaryDirectory, '/\\')
                    . '/coretsia-http-maintenance-parity-'
                    . \bin2hex(\random_bytes(8));

                self::assertTrue(
                    @\mkdir($root, 0700),
                    'maintenance-test-root-creation-failed',
                );

                $roots[] = $root;
                $paths = new RuntimePathContext($root, $root . '/var/cache');

                foreach (self::containers($set, $config, $paths) as $container) {
                    $store = $container->get(MaintenanceStateStore::class);
                    $middleware = $container->get(MaintenanceMiddleware::class);
                    $kernel = $container->get(HttpKernelInterface::class);
                    $pipeline = $container->get(Pipeline::class);
                    $locks = $container->get(ScopedFileLock::class);
                    $stack = $container->get(HttpMiddlewareStackBuilder::class);

                    self::assertInstanceOf(MaintenanceStateStore::class, $store);
                    self::assertInstanceOf(MaintenanceMiddleware::class, $middleware);
                    self::assertInstanceOf(HttpKernel::class, $kernel);
                    self::assertInstanceOf(Pipeline::class, $pipeline);
                    self::assertInstanceOf(ScopedFileLock::class, $locks);
                    self::assertInstanceOf(HttpMiddlewareStackBuilder::class, $stack);

                    self::assertSame($paths, $container->get(RuntimePathContext::class));
                    self::assertSame(
                        $paths,
                        new \ReflectionProperty(MaintenanceStateStore::class, 'paths')->getValue($store),
                    );
                    self::assertSame(
                        $locks,
                        new \ReflectionProperty(MaintenanceStateStore::class, 'locks')->getValue($store),
                    );
                    self::assertSame(
                        $store,
                        new \ReflectionProperty(MaintenanceMiddleware::class, 'state')->getValue($middleware),
                    );

                    self::assertSame($store, $container->get(MaintenanceStateStore::class));
                    self::assertSame($middleware, $container->get(MaintenanceMiddleware::class));
                    self::assertSame($locks, $container->get(ScopedFileLock::class));

                    self::assertSame(
                        $maintenanceEnabled,
                        \in_array(
                            MaintenanceMiddleware::class,
                            $stack->build($config['http']),
                            true,
                        ),
                    );

                    foreach (self::MAINTENANCE_COMMANDS as $id) {
                        $command = $container->get($id);

                        self::assertInstanceOf($id, $command);
                        self::assertSame($command, $container->get($id));
                        self::assertSame(
                            $store,
                            new \ReflectionProperty($id, 'stateStore')->getValue($command),
                        );
                    }

                    $fixtures[$index][] = [
                        'container' => $container,
                        'store' => $store,
                        'kernel' => $kernel,
                        'pipeline' => $pipeline,
                    ];
                }

                self::assertFalse(
                    \str_contains($encodedGraph, $paths->applicationRoot()),
                    'maintenance-application-root-in-compiled-graph',
                );
                self::assertFalse(
                    \str_contains($encodedGraph, $paths->artifactRoot()),
                    'maintenance-artifact-root-in-compiled-graph',
                );
            }

            $assertStates = function (bool $firstActive, bool $secondActive) use (
                $fixtures,
                $maintenanceEnabled,
            ): void {
                foreach ($fixtures as $index => $pair) {
                    $active = $index === 0 ? $firstActive : $secondActive;

                    foreach ($pair as $fixture) {
                        $container = $fixture['container'];
                        $store = $fixture['store'];

                        self::assertSame($active, $store->read()['enabled']);
                        self::assertSame($store, $container->get(MaintenanceStateStore::class));
                        self::assertSame($fixture['kernel'], $container->get(HttpKernelInterface::class));
                        self::assertSame($fixture['pipeline'], $container->get(Pipeline::class));

                        $served = $maintenanceEnabled && $active;

                        $this->assertMaintenanceParityResponse(
                            $container,
                            $fixture['kernel'],
                            $served ? ($index === 0 ? 120 : 75) : null,
                            $served
                                ? ($index === 0 ? "Maintenance parity\n" : "Service Unavailable\n")
                                : '',
                        );
                    }
                }
            };

            $assertDefinitionsUnchanged = static function () use (
                $config,
                $descriptors,
                $graph,
            ): void {
                $current = self::definitions($config);

                self::assertSame($descriptors, $current->toDescriptorStream());
                self::assertSame($graph, self::compiler()->compile($current)->toArray());
            };

            $firstStore = $fixtures[0][0]['store'];
            $secondStore = $fixtures[1][1]['store'];

            $assertStates(false, false);

            $firstStore->enable(new MaintenanceMessage(1, 'Maintenance parity', 120));

            $assertStates(true, false);
            $assertDefinitionsUnchanged();

            $secondStore->enable(null);

            $assertStates(true, true);
            $assertDefinitionsUnchanged();

            $fixtures[0][1]['store']->disable();

            $assertStates(false, true);
            $assertDefinitionsUnchanged();

            $fixtures[1][0]['store']->disable();

            $assertStates(false, false);
            $assertDefinitionsUnchanged();

            self::assertFalse(
                \str_contains($encodedGraph, 'Maintenance parity'),
                'maintenance-message-in-compiled-graph',
            );
        } finally {
            HttpParityExternalFactory::$response = null;
            HttpParityExternalFactory::$stream = null;

            foreach ($roots as $root) {
                self::removeMaintenanceRoot($root);
            }
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function maintenanceConfigurations(): iterable
    {
        yield 'maintenance-enabled' => [true];
        yield 'maintenance-disabled' => [false];
    }

    public function testExternalDoesNotContributeAnyFactoryReplacementBeforeApplicationBindings(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext(self::config()));
        $operations = $definitions->build()->toDescriptorStream();
        foreach ($operations as $operation) {
            self::assertFalse(
                ($operation['kind'] ?? null) === 'alias'
                && \in_array(
                    $operation['alias'],
                    [ResponseFactoryInterface::class, StreamFactoryInterface::class],
                    true,
                ),
            );
            self::assertFalse(
                ($operation['kind'] ?? null) === 'service.class'
                && \in_array($operation['id'], [ResponseFactoryInterface::class, StreamFactoryInterface::class], true),
            );
        }
    }

    public function testSwitchingExplicitFactoryChangesCompiledGraphWithoutChangingHttpMiddlewareDefinitions(): void
    {
        $a = self::compiler()->compile(self::definitions(self::config('nyholm')))->toArray();
        $b = self::compiler()->compile(self::definitions(self::config('slim')))->toArray();
        self::assertNotSame(
            $a['aliases'][ResponseFactoryInterface::class],
            $b['aliases'][ResponseFactoryInterface::class],
        );
        self::assertNotSame($a['aliases'][StreamFactoryInterface::class], $b['aliases'][StreamFactoryInterface::class]);
        self::assertSame($a['tags'], $b['tags']);
        foreach ([HttpKernel::class, RequestIdPolicy::class, ...self::COMPLETION_SERVICES, Pipeline::class] as $id) {
            self::assertSame($a['services'][$id], $b['services'][$id]);
        }
    }

    public function testExternalMissingFactoryIsRejectedDuringContainerCompilation(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new FoundationServiceProvider()->define($definitions, new ContainerDefinitionContext(self::config()));
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext(self::config()));
        $definitions
            ->classService(KernelRuntimeInterface::class, HttpParityKernelRuntime::class)
            ->classService(RuntimeDriverResolver::class, RuntimeDriverResolver::class);
        $set = $definitions->build();
        try {
            self::compiler()->compile($set);
            self::fail('Missing external PSR-17 bindings must prevent container compilation.');
        } catch (ContainerCompileFailedException $exception) {
            self::assertSame(ContainerCompileFailedException::REASON_GRAPH_INVALID, $exception->reason());
        }
    }

    public function testLaterBindingWinsForExternalServicesAndAliasesInSourceAndCompiledContainers(): void
    {
        $config = self::config();
        $definitions = new ContainerDefinitionBuilder();
        new FoundationServiceProvider()->define($definitions, new ContainerDefinitionContext($config));
        new HttpServiceProvider()->define($definitions, new ContainerDefinitionContext($config));
        $definitions
            ->classService(KernelRuntimeInterface::class, HttpParityKernelRuntime::class)
            ->classService(RuntimeDriverResolver::class, RuntimeDriverResolver::class)
            ->classService(HttpParityExternalFactory::class, HttpParityExternalFactory::class)
            ->classService(HttpParityOverrideFactory::class, HttpParityOverrideFactory::class)
            ->alias(ResponseFactoryInterface::class, HttpParityExternalFactory::class)
            ->alias(StreamFactoryInterface::class, HttpParityExternalFactory::class)
            ->alias(ResponseFactoryInterface::class, HttpParityOverrideFactory::class)
            ->alias(StreamFactoryInterface::class, HttpParityOverrideFactory::class);
        [$source, $compiled] = self::containers($definitions->build(), $config);
        foreach ([$source, $compiled] as $container) {
            self::assertInstanceOf(HttpParityOverrideFactory::class, $container->get(ResponseFactoryInterface::class));
            self::assertSame(
                $container->get(ResponseFactoryInterface::class),
                $container->get(StreamFactoryInterface::class),
            );
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOverriddenBuiltInSelectionFailsReadinessInBothContainerModes(): void
    {
        $selected = HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES['nyholm']['response'];
        if (!\class_exists($selected)) {
            \class_alias(HttpParityVendorMarker::class, $selected);
        }
        $config = self::config('nyholm');
        $definitions = self::definitions($config, true);
        [$source, $compiled] = self::containers($definitions, $config);
        foreach ([$source, $compiled] as $container) {
            $this->expectFactoryMismatch($container, $config);
        }

        $serviceDefinitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);
        new FoundationServiceProvider()->define($serviceDefinitions, $context);
        new HttpServiceProvider()->define($serviceDefinitions, $context);
        $serviceDefinitions
            ->classService(KernelRuntimeInterface::class, HttpParityKernelRuntime::class)
            ->classService(RuntimeDriverResolver::class, RuntimeDriverResolver::class)
            ->classService($selected, HttpParityExternalFactory::class);

        [$source, $compiled] = self::containers($serviceDefinitions->build(), $config);
        foreach ([$source, $compiled] as $container) {
            self::assertInstanceOf(HttpParityExternalFactory::class, $container->get($selected));
            $this->expectFactoryMismatch($container, $config);
        }
    }

    private function assertMaintenanceParityResponse(
        Container $container,
        HttpKernel $kernel,
        ?int $retryAfterSeconds,
        string $expectedBody,
    ): void {
        $expectedStatus = $retryAfterSeconds === null ? 404 : 503;

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(0);
        $stream->method('eof')->willReturn(true);

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/maintenance-parity');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);
        $request->method('getBody')->willReturn($stream);
        $request->method('getHeader')->willReturn([]);
        $request->method('hasHeader')->willReturn(false);

        $headers = [];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($expectedStatus);
        $response->expects(self::once())->method('withBody')->with($stream)->willReturnSelf();

        $response->expects(self::exactly($retryAfterSeconds === null ? 0 : 3))
            ->method('withHeader')
            ->willReturnCallback(
                static function (string $name, mixed $value) use ($response, &$headers): ResponseInterface {
                    $headers[$name] = $value;

                    return $response;
                },
            );

        HttpParityExternalFactory::$stream = $stream;
        HttpParityExternalFactory::$response = $response;

        $factory = $container->get(HttpParityExternalFactory::class);
        self::assertInstanceOf(HttpParityExternalFactory::class, $factory);

        $factory->lastResponseCode = null;
        $factory->lastStreamContent = null;

        $actual = $kernel->handle($request);

        self::assertSame($response, $actual);
        self::assertSame($expectedStatus, $actual->getStatusCode());
        self::assertSame($expectedStatus, $factory->lastResponseCode);
        self::assertSame($expectedBody, $factory->lastStreamContent);

        self::assertSame(
            $retryAfterSeconds === null
                ? []
                : [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-store',
                'Retry-After' => (string) $retryAfterSeconds,
            ],
            $headers,
        );
    }

    private static function removeMaintenanceRoot(string $root): void
    {
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                @\rmdir($entry->getPathname());
            } else {
                @\unlink($entry->getPathname());
            }
        }

        @\rmdir($root);
    }

    private function expectFactoryMismatch(Container $container, array $config): void
    {
        try {
            $container->get(HttpRuntimeEntrypointGuard::class)->assertEntrypointAllowed(
                new ArrayConfigRepository($config),
                self::plan(),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
        } catch (\RuntimeException $exception) {
            self::assertSame('http-factory-selection-mismatch', $exception->getMessage());

            return;
        }
        self::fail('An overridden built-in factory must fail HTTP readiness.');
    }

    /** @return array<string, mixed> */
    private static function config(string $factory = 'external'): array
    {
        $http = require __DIR__ . '/../../config/http.php';
        $http['factory'] = $factory;
        $http['maintenance']['enabled'] = false;

        return [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'http' => $http,
            'kernel' => ['runtime' => ['http_driver' => 'http.classic']],
        ];
    }

    /** @return array<string, mixed> */
    private static function requestIdConfig(bool $enabled): array
    {
        $config = self::config();
        $config['foundation']['ids']['default'] = 'uuid';
        $config['http']['request_id'] = [
            'enabled' => $enabled,
            'header_name' => 'X-Edge-Request-Id',
            'accept_from_upstream' => true,
            'max_length' => 64,
        ];

        return $config;
    }

    private static function definitions(array $config, bool $overrideBuiltIn = false): ContainerDefinitionSet
    {
        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);
        new FoundationServiceProvider()->define($definitions, $context);
        new HttpServiceProvider()->define($definitions, $context);
        $definitions
            ->classService(KernelRuntimeInterface::class, HttpParityKernelRuntime::class)
            ->classService(RuntimeDriverResolver::class, RuntimeDriverResolver::class);
        if ($config['http']['factory'] === 'external' || $overrideBuiltIn) {
            $definitions->classService(HttpParityExternalFactory::class, HttpParityExternalFactory::class);
            $definitions->alias(ResponseFactoryInterface::class, HttpParityExternalFactory::class);
            $definitions->alias(StreamFactoryInterface::class, HttpParityExternalFactory::class);
        }

        return $definitions->build();
    }

    private static function compiler(): ContainerCompiler
    {
        return new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch());
    }

    /** @return array{Container, Container} */
    private static function containers(
        ContainerDefinitionSet $set,
        array $config,
        ?RuntimePathContext $paths = null,
    ): array {
        $paths ??= new RuntimePathContext('/test', '/test/var/cache');

        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config));
        $builder->instance(TagRegistry::class, $builder->tagRegistry());
        $builder->instance(RuntimePathContext::class, $paths);
        $source = $builder->applyDefinitions($set)->build();
        $graph = self::compiler()->compile($set);
        new ContainerGraphCompletenessValidator()->validate($graph, $set);
        $envelope = new ArtifactEnvelopeFactory()->container(
            \str_repeat('a', 64),
            ['kind' => 'compiled', 'compiled' => true, ...$graph->toArray()],
        );
        $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $envelope,
            new RuntimeContainerSeedSet([
                ConfigRepositoryInterface::class => new ArrayConfigRepository($config),
                ModulePlan::class => self::plan(),
                RuntimePathContext::class => $paths,
            ]),
        );

        return [$source, $compiled];
    }

    private static function plan(): ModulePlan
    {
        $id = ModuleId::fromString('platform.http');

        return new ModulePlan(
            app: 'web',
            enabled: [$id],
            excluded: [],
            topologicalOrder: [$id],
            modules: [new ModulePlanEntry($id, 'coretsia/platform-http')],
        );
    }
}

/** A test-only PSR-17 implementation; no runtime factory package is required. */
class HttpParityExternalFactory implements ResponseFactoryInterface, StreamFactoryInterface
{
    public static ?ResponseInterface $response = null;
    public static ?StreamInterface $stream = null;
    public ?int $lastResponseCode = null;
    public ?string $lastStreamContent = null;

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        $this->lastResponseCode = $code;
        return self::$response ?? throw new \LogicException('http-parity-response-not-set');
    }

    public function createStream(string $content = ''): StreamInterface
    {
        $this->lastStreamContent = $content;

        return self::$stream ?? throw new \LogicException('http-parity-stream-not-set');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('http-parity-file-unsupported');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('http-parity-resource-unsupported');
    }
}

final class HttpParityOverrideFactory extends HttpParityExternalFactory
{
}

final class HttpParityVendorMarker
{
}

/** Test-only KernelRuntime boundary; production KernelRuntime is not replaced. */
final class HttpParityKernelRuntime implements KernelRuntimeInterface
{
    public int $begins = 0;
    public int $completions = 0;
    public ?string $lastOutcome = null;

    public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
    {
        throw new \LogicException('http-parity-low-level-api-required');
    }

    public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
    {
        ++$this->begins;

        return new UnitOfWorkHandle([
            'attributes' => $attributes,
            'correlationId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'type' => $type,
            'uowId' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        ]);
    }

    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        ++$this->completions;
        $this->lastOutcome = $outcome;

        return [];
    }
}
