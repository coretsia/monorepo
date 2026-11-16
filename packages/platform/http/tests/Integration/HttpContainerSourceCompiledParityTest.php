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
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Context\ContextStore;
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
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\HttpKernelInterface;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
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
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\Routing\FallbackRouterHandler;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpContainerSourceCompiledParityTest extends TestCase
{
    /** @var list<class-string> */
    private const array COMPLETION_SERVICES = [
        HttpResponseFinalizer::class,
        HttpRequestMetricsRecorder::class,
        HttpAccessLogWriter::class,
        HttpRequestSpanRecorder::class,
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

    #[DataProvider('requestIdConfigurations')]
    public function testProviderDescriptorsAndTagsHaveOneCanonicalSourceForBothContainerModes(
        bool $requestIdEnabled,
    ): void {
        $config = self::requestIdConfig($requestIdEnabled);
        $set = self::definitions($config);
        $operations = $set->toDescriptorStream();
        $graph = self::compiler()->compile($set)->toArray();
        self::assertNotEmpty($graph['services']);

        $expected = [
            RequestIdPolicy::class,
            HttpKernel::class,
            ...self::COMPLETION_SERVICES,
        ];

        $services = [];
        $tags = [];
        $lifecycleTags = [];
        $contextStores = [];

        foreach ($operations as $operation) {
            if (($operation['kind'] ?? null) === 'service.class') {
                if (\in_array($operation['id'], $expected, true)) {
                    $services[$operation['id']] = $operation;
                }

                if ($operation['class'] === ContextStore::class) {
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
            }

            if (\in_array(
                $operation['tag'],
                [ReservedTags::KERNEL_RESET, ReservedTags::KERNEL_STATEFUL],
                true,
            )) {
                $lifecycleTags[] = [$operation['tag'], $operation['serviceId']];
            }
        }

        self::assertCount(6, $services);
        self::assertCount(6, $tags);
        self::assertSame(
            [
            [TraceContextMiddleware::class, 930, ['toggle' => 'http.tracing.enabled']],
            [TrustedProxyMiddleware::class, 880, ['toggle' => 'http.proxy.enabled']],
            [RequestContextMiddleware::class, 870, ['toggle' => 'http.context.enrich.enabled']],
            [TrustedHostMiddleware::class, 860, ['toggle' => 'http.hosts.enabled']],
            [RequestBodySizeLimitMiddleware::class, 830, []],
            [JsonBodyParserMiddleware::class, 800, ['toggle' => 'http.request.json.enabled']],
        ],
            \array_map(
                static fn (array $tag): array => [$tag['serviceId'], $tag['priority'], $tag['meta']],
                $tags,
            )
        );

        foreach ($tags as $tag) {
            self::assertSame(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, $tag['tag']);
        }
        self::assertSame([ContextStore::class], $contextStores);
        self::assertSame([
            [ReservedTags::KERNEL_RESET, ContextStore::class],
            [ReservedTags::KERNEL_STATEFUL, ContextStore::class],
        ], $lifecycleTags);

        foreach ($services as $id => $service) {
            self::assertSame($service['class'], $graph['services'][$id]['construction']['class']);
            self::assertTrue($service['shared']);
            self::assertTrue($graph['services'][$id]['shared']);
            self::assertSame($service['arguments'], $graph['services'][$id]['arguments']);
        }

        $ref = static fn (string $id): array => ContainerValueReference::service($id)->toArray();
        $requestId = $config['http']['request_id'];

        self::assertSame(RequestIdPolicy::class, $services[RequestIdPolicy::class]['class']);
        self::assertSame([
            $ref(ContextStore::class),
            $ref(UlidGenerator::class),
            $requestId['enabled'],
            $requestId['header_name'],
            $requestId['accept_from_upstream'],
            $requestId['max_length'],
        ], $services[RequestIdPolicy::class]['arguments']);

        self::assertSame([
            $ref(CorrelationIdProviderInterface::class),
            true,
            'X-Correlation-Id',
            $ref(RequestIdPolicy::class),
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
        $oppositeGraph = self::compiler()->compile(self::definitions($opposite))->toArray();

        self::assertSame(\array_keys($graph['services']), \array_keys($oppositeGraph['services']));
        self::assertSame($graph['aliases'], $oppositeGraph['aliases']);
        self::assertSame($graph['parameters'], $oppositeGraph['parameters']);
        self::assertSame($graph['tags'], $oppositeGraph['tags']);

        $expectedServices = $graph['services'];
        $expectedServices[RequestIdPolicy::class]['arguments'][2] = !$requestIdEnabled;
        self::assertSame($expectedServices, $oppositeGraph['services']);

        self::assertSame(HttpParityExternalFactory::class, $graph['aliases'][ResponseFactoryInterface::class]);
        self::assertSame(HttpParityExternalFactory::class, $graph['aliases'][StreamFactoryInterface::class]);
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
            HttpResponseFinalizer::class => ['correlationIds', 'enabled', 'headerName', 'requestIds'],
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
            $parameters = $constructor->getParameters();
            $parameter = $parameters[\count($parameters) - 1];
            $type = $parameter->getType();

            self::assertSame('requestIds', $parameter->getName());
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertSame(RequestIdPolicy::class, $type->getName());
            self::assertFalse($type->allowsNull());
            self::assertFalse($parameter->isOptional());
            self::assertFalse($parameter->isDefaultValueAvailable());
        }
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
    private static function containers(ContainerDefinitionSet $set, array $config): array
    {
        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config));
        $builder->instance(TagRegistry::class, $builder->tagRegistry());
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
                RuntimePathContext::class => new RuntimePathContext('/test', '/test/var/cache'),
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

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return self::$response ?? throw new \LogicException('http-parity-response-not-set');
    }

    public function createStream(string $content = ''): StreamInterface
    {
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
{}

final class HttpParityVendorMarker
{}

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
