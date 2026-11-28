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

namespace Coretsia\Platform\HttpApp\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteMatch;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\HttpApp\Error\HttpAppProblemMapper;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Coretsia\Platform\HttpApp\Exception\HttpMethodNotAllowedException;
use Coretsia\Platform\HttpApp\Exception\HttpNotFoundException;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class NotFound405GoThroughCanonicalErrorFlowTest extends TestCase
{
    #[DataProvider('routingFailures')]
    public function testRealArtifactRoutingMissesUseCanonicalErrorFlow(
        string $method,
        string $path,
        int $status,
        array $allowed,
        int $enumerations,
    ): void {
        $root = RoutingIntegrationFixture::root();
        try {
            $routes = [
                new RouteDefinition('post', ['POST'], '/items/{id}', 'private.action', ['id' => '[0-9]+']),
                new RouteDefinition('get', ['GET'], '/items/{id}', 'private.action', ['id' => '[0-9]+']),
                new RouteDefinition('delete', ['DELETE'], '/items/{id}', 'private.action', ['id' => '[0-9]+']),
                new RouteDefinition(
                    'all',
                    ['CONNECT', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'],
                    '/all/{id}',
                    'private.action',
                    ['id' => '[0-9]+'],
                ),
            ];
            self::assertTrue(
                RoutingIntegrationFixture::writer($root)->write(
                    RoutingIntegrationFixture::schema()->createEnvelope($routes, 'web', 'local'),
                ),
            );
            $real = new CompiledRouter(
                new CompiledRouteTable(RoutingIntegrationFixture::reader($root)),
                new ContextStore(),
                new RoutingMatchObserver(
                    new NoopTracer(),
                    new NoopMeter(),
                    new NoopLogger(),
                    new Stopwatch(),
                    false,
                    false,
                    false,
                ),
            );
            $router = $this->createMock(RouterInterface::class);
            $router
                ->expects(self::once())->method('match')
                ->with($method, $path, 'example.test')
                ->willReturnCallback(
                    static fn (string $method, string $path, ?string $host): ?RouteMatch => $real->match(
                        $method,
                        $path,
                        $host,
                    ),
                );
            $router->expects(self::exactly($enumerations))->method('allowedMethods')->with(
                $path,
                'example.test',
            )->willReturnCallback($real->allowedMethods(...));
            $config = self::config();
            $config['http_app']['handlers'] = [['action_id' => 'private.action', 'service_id' => 'private.service']];
            $builder = $this->builder($config, $router);
            $resolutions = new \ArrayObject();
            $builder->factory('private.service', static function () use ($resolutions): never {
                $resolutions->append('unexpected-action-resolution');
                throw new \LogicException('unexpected-action-resolution');
            });
            [$pipeline, $response, $primary, $descriptors, $normalized] = $this->flow($builder, $status, $allowed);
            self::assertSame($response, $pipeline->handle($this->request($method, $path)));
            self::assertCount(0, $resolutions);
            self::assertCount(1, $primary);
            self::assertCount(1, $descriptors);
            self::assertCount(1, $normalized);
            self::assertSame(
                $status === 405 ? HttpMethodNotAllowedException::class : HttpNotFoundException::class,
                $primary[0]::class,
            );
            self::assertSame('CORETSIA_HTTP_' . $status, $descriptors[0]->code());
            self::assertSame($status, $descriptors[0]->httpStatus());
            self::assertSame($status === 405 ? ['allowed_methods' => $allowed] : [], $descriptors[0]->extensions());
            self::assertSame($descriptors[0]->extensions(), $normalized[0]['extensions']);
        } finally {
            RoutingIntegrationFixture::remove($root);
        }
    }

    /** @return iterable<string, array{string, string, int, list<string>, int}> */
    public static function routingFailures(): iterable
    {
        yield 'missing-path' => ['GET', '/private-path', 404, [], 1];
        yield 'requirement-mismatch' => ['GET', '/items/private-parameter', 404, [], 1];
        foreach (['PUT', 'HEAD', 'OPTIONS', 'PROPFIND', 'get'] as $method) {
            yield 'method-' . $method => [$method, '/items/123', 405, ['DELETE', 'GET', 'POST'], 1];
        }
        yield 'bounded-nine-methods' => [
            'PROPFIND',
            '/all/123',
            405,
            ['CONNECT', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'],
            1,
        ];
        yield 'empty-method' => ['', '/items/123', 404, [], 0];
        yield 'malformed-method' => ['BAD METHOD', '/items/123', 404, [], 0];

        foreach (["BAD\tMETHOD", "GET\n", 'BAD/METHOD', 'MÉTHOD', "GET\x00"] as $index => $method) {
            yield 'invalid-token-' . $index => [$method, '/items/123', 404, [], 0];
        }

        yield 'extension-token-punctuation' => [
            "X!#$%&'*+-.^_`|~",
            '/items/123',
            405,
            ['DELETE', 'GET', 'POST'],
            1,
        ];
    }

    #[DataProvider('dispatchFailures')]
    public function testMatchedDispatchFailuresAreInternalErrorsNotRoutingMisses(string $mode, string $reason): void
    {
        $config = self::config();
        $handlerId = $mode === 'missing-handler-class' ? ErrorFlowArgumentAction::class : 'private.service';
        $config['http_app']['handlers'] = $mode === 'unmapped' ? [] : [
            [
                'action_id' => 'private.action',
                'service_id' => $handlerId,
            ],
        ];
        if ($mode === 'missing-argument') {
            $config['http_app']['arguments']['allow_container_types'] = [ErrorFlowArgument::class];
        }
        if ($mode === 'missing-middleware') {
            $config['http_app']['route_middleware'] = ['private.middleware'];
        }
        if ($mode === 'disabled-json') {
            $config['http_app']['response']['json']['enabled'] = false;
        }
        if ($mode === 'disabled-html') {
            $config['http_app']['response']['html']['enabled'] = false;
        }
        $match = new RouteMatch(
            'route',
            '/items/{id}',
            'private.action',
            ['id' => 'private-parameter'],
            $mode === 'missing-middleware' ? ['http_app_middleware' => ['private.middleware']] : [],
        );
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('match')->with(
            'GET',
            '/items/private-parameter',
            'example.test',
        )->willReturn($match);
        $router->expects(self::never())->method('allowedMethods');
        $builder = $this->builder($config, $router);
        $events = new \ArrayObject();
        $unrelated = new \RuntimeException('private-construction-or-action-details');
        if (!\in_array($mode, ['missing-handler', 'missing-handler-class'], true)) {
            $builder->factory('private.service', static function () use ($mode, $events, $unrelated): object {
                $events->append('resolve');

                return match ($mode) {
                    'construction' => throw $unrelated,
                    'handler-shape' => new \stdClass(),
                    'signature' => new class() {
                        public function __invoke(string|int $id): never
                        {
                            throw new \LogicException('unexpected-action-execution');
                        }
                    },
                    'argument-shape' => new class() {
                        public function __invoke(int $id): never
                        {
                            throw new \LogicException('unexpected-action-execution');
                        }
                    },
                    'missing-argument' => new ErrorFlowArgumentAction(),
                    default => new ErrorFlowResultAction($mode, $events, $unrelated),
                };
            }, false);
        }
        if ($mode === 'missing-handler-class') {
            self::assertTrue($builder->build()->has($handlerId));
            self::assertFalse(\in_array($handlerId, $builder->serviceIds(), true));
        }
        if ($mode === 'missing-argument') {
            self::assertTrue($builder->build()->has(ErrorFlowArgument::class));
            self::assertFalse(\in_array(ErrorFlowArgument::class, $builder->serviceIds(), true));
        }
        [$pipeline, $response, $primary, $descriptors, $normalized] = $this->flow($builder, 500, []);
        self::assertSame($response, $pipeline->handle($this->request('GET', '/items/private-parameter')));
        self::assertCount(1, $primary);
        self::assertCount(1, $descriptors);
        self::assertCount(1, $normalized);
        if ($mode === 'unrelated') {
            self::assertSame($unrelated, $primary[0]);
        } else {
            self::assertInstanceOf(HttpAppDispatchException::class, $primary[0]);
            self::assertSame($reason, $primary[0]->reason());
            self::assertSame($reason, $primary[0]->getMessage());
            self::assertNull($primary[0]->getPrevious());
        }
        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptors[0]->code());
        self::assertSame('Unhandled exception.', $descriptors[0]->message());
        self::assertNull($descriptors[0]->httpStatus());
        self::assertSame([], $descriptors[0]->extensions());
        $expected = match ($mode) {
            'unmapped', 'missing-handler', 'missing-handler-class', 'missing-middleware' => [],
            'unsupported', 'disabled-json', 'disabled-html', 'unrelated' => ['resolve', 'action'],
            default => ['resolve'],
        };
        self::assertSame($expected, $events->getArrayCopy());
    }

    /** @return iterable<string, array{string, string}> */
    public static function dispatchFailures(): iterable
    {
        yield 'unmapped' => ['unmapped', 'http-app-handler-unmapped'];
        yield 'missing-handler' => ['missing-handler', 'http-app-handler-unavailable'];
        yield 'missing-handler-autowire' => ['missing-handler-class', 'http-app-handler-unavailable'];
        yield 'construction' => ['construction', 'http-app-handler-unavailable'];
        yield 'handler-shape' => ['handler-shape', 'http-app-handler-invalid'];
        yield 'signature' => ['signature', 'http-app-handler-invalid'];
        yield 'argument-shape' => ['argument-shape', 'http-app-argument-invalid'];
        yield 'missing-argument' => ['missing-argument', 'http-app-argument-unresolvable'];
        yield 'missing-middleware' => ['missing-middleware', 'http-app-route-middleware-unavailable'];
        yield 'unsupported' => ['unsupported', 'http-app-response-unsupported'];
        yield 'disabled-json' => ['disabled-json', 'http-app-response-disabled'];
        yield 'disabled-html' => ['disabled-html', 'http-app-response-disabled'];
        yield 'unrelated-throwable' => ['unrelated', ''];
    }

    public function testCanonicalMapperRegistryDoesNotClaimDispatchOrUnrelatedFailures(): void
    {
        $builder = $this->builder(self::config(), $this->createStub(RouterInterface::class));
        $container = $builder->register(
            new FoundationServiceProvider(),
            new RedactionServiceProvider(),
            new ErrorsServiceProvider(),
            new HttpAppServiceProvider(),
        )->build();
        $registry = $container->get(ExceptionMapperRegistry::class);
        self::assertInstanceOf(ExceptionMapperRegistry::class, $registry);
        foreach (
            [
                new \RuntimeException('private-runtime-failure'),
                new \LogicException('http-pipeline-continuation-reused'),
                new \Error('private-native-error'),
                new \TypeError('private-invocation-type-error'),
            ] as $failure
        ) {
            self::assertNull($registry->map($failure));
        }
        foreach (
            [
                'http-app-handler-unmapped',
                'http-app-handler-unavailable',
                'http-app-handler-invalid',
                'http-app-argument-unresolvable',
                'http-app-argument-invalid',
                'http-app-response-unsupported',
                'http-app-response-disabled',
                'http-app-response-mapping-failed',
                'http-app-route-state-invalid',
                'http-app-route-input-changed',
                'http-app-route-middleware-invalid',
                'http-app-route-middleware-unavailable',
            ] as $reason
        ) {
            self::assertNull($registry->map(HttpAppDispatchException::withReason($reason)));
        }
    }

    #[DataProvider('missingGraphBindings')]
    public function testMissingConfiguredBindingsFailProductionGraphValidationButNotSourceApplication(
        string $missing,
    ): void {
        $config = self::config();
        $config['http_app']['handlers'] = [['action_id' => 'private.action', 'service_id' => 'private.service']];
        $config['http_app']['arguments']['allow_container_types'] = [ErrorFlowArgument::class];
        $config['http_app']['route_middleware'] = ['private.middleware'];
        $context = new ContainerDefinitionContext($config);
        $base = new ContainerDefinitionBuilder();
        foreach (
            [
                new FoundationServiceProvider(),
                new RedactionServiceProvider(),
                new ErrorsServiceProvider(),
                new ProblemDetailsServiceProvider(),
            ] as $provider
        ) {
            $provider->define($base, $context);
        }
        $app = new ContainerDefinitionBuilder();
        new HttpAppServiceProvider()->define($app, $context);
        $fixtures = new ContainerDefinitionBuilder();
        $completeFixtures = new ContainerDefinitionBuilder();
        foreach ([$fixtures, $completeFixtures] as $bindings) {
            $bindings->classService(RouterInterface::class, ErrorFlowGraphRouter::class)
                ->classService(ResponseFactoryInterface::class, ErrorFlowGraphTransport::class)
                ->classService(StreamFactoryInterface::class, ErrorFlowGraphTransport::class);
        }
        foreach (
            [
                'private.service' => ErrorFlowArgumentAction::class,
                ErrorFlowArgument::class => ErrorFlowArgument::class,
                'private.middleware' => ErrorFlowGraphMiddleware::class,
            ] as $id => $class
        ) {
            $completeFixtures->classService($id, $class);
            if ($id !== $missing) {
                $fixtures->classService($id, $class);
            }
        }
        $appSet = $app->build();
        self::assertContains($missing, $appSet->requiredServiceIds());
        $baseSet = $base->build();
        $compiler = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch());
        $validator = new ContainerGraphCompletenessValidator();
        $complete = ContainerDefinitionSet::merge($baseSet, $appSet, $completeFixtures->build());
        $validator->validate($compiler->compile($complete), $complete);
        $set = ContainerDefinitionSet::merge($baseSet, $appSet, $fixtures->build());
        $source = new ContainerBuilder($config)->instance(
            ConfigRepositoryInterface::class,
            new ArrayConfigRepository($config, []),
        )->applyDefinitions($set)->build();
        self::assertFalse(\in_array($missing, $source->serviceIds(), true));
        self::assertContains(ActionDispatchMiddleware::class, $source->serviceIds());
        $graph = $compiler->compile($set);
        try {
            $validator->validate($graph, $set);
        } catch (ContainerDefinitionInvalidException $exception) {
            self::assertSame('required-service-invalid', $exception->reason());
            self::assertSame(
                'CORETSIA_CONTAINER_DEFINITION_INVALID: container-definition-invalid',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());

            return;
        }
        self::fail('incomplete-production-graph-accepted');
    }

    /** @return iterable<string, array{string}> */
    public static function missingGraphBindings(): iterable
    {
        yield 'handler' => ['private.service'];
        yield 'argument' => [ErrorFlowArgument::class];
        yield 'middleware' => ['private.middleware'];
    }

    /** @return array<string, mixed> */
    private static function config(): array
    {
        $config = [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'errors' => require \dirname(__DIR__, 4) . '/platform/errors/config/errors.php',
            'http_app' => require \dirname(__DIR__, 2) . '/config/http_app.php',
        ];
        $config['foundation']['container']['autowire_concrete'] = true;
        $config['foundation']['container']['allow_reflection_for_concrete'] = true;
        $config['errors']['reporting']['enabled'] = false;

        return $config;
    }

    /** @param array<string, mixed> $config */
    private function builder(array $config, RouterInterface $router): ContainerBuilder
    {
        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config, []));
        $builder->instance(RouterInterface::class, $router);

        return $builder;
    }

    /**
     * @param list<string> $allowed
     * @return array{Pipeline, ResponseInterface, \ArrayObject, \ArrayObject, \ArrayObject}
     */
    private function flow(ContainerBuilder $builder, int $status, array $allowed): array
    {
        $primary = new \ArrayObject();
        $descriptors = new \ArrayObject();
        $normalized = new \ArrayObject();
        // Exact public JSON is an independent oracle, not rendered by the implementation under test.
        $title = match ($status) {
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        };
        $body = '{"status":' . $status . ',"title":"' . $title . '","type":"about:blank"}' . "\n";
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $initial = $this->createMock(ResponseInterface::class);
        $typed = $this->createMock(ResponseInterface::class);
        $cached = $this->createMock(ResponseInterface::class);
        $allowHandled = $this->createMock(ResponseInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $stream = $this->createStub(StreamInterface::class);
        $responses->expects(self::once())->method('createResponse')->with($status)->willReturn($initial);
        $streams->expects(self::once())->method('createStream')->with(self::identicalTo($body))->willReturn($stream);
        $initial->expects(self::once())->method('withHeader')->with(
            'Content-Type',
            'application/problem+json',
        )->willReturn($typed);
        $typed->expects(self::once())->method('withHeader')->with('Cache-Control', 'no-store')->willReturn($cached);
        if ($status === 405) {
            $cached->expects(self::once())->method('withHeader')->with(
                'Allow',
                self::identicalTo(\implode(', ', $allowed)),
            )->willReturn($allowHandled);
            $cached->expects(self::never())->method('withoutHeader');
            $cached->expects(self::never())->method('hasHeader');
        } else {
            $cached->expects(self::never())->method('withHeader');
            $cached->expects(self::once())->method('hasHeader')->with('Allow')->willReturn(true);
            $cached->expects(self::once())->method('withoutHeader')->with('Allow')->willReturn($allowHandled);
        }
        $allowHandled->expects(self::once())->method('withBody')->with(self::identicalTo($stream))->willReturn(
            $response,
        );
        $builder->instance(ResponseFactoryInterface::class, $responses);
        $builder->instance(StreamFactoryInterface::class, $streams);
        $builder->register(
            new FoundationServiceProvider(),
            new RedactionServiceProvider(),
            new ErrorsServiceProvider(),
            new HttpAppServiceProvider(),
            new ProblemDetailsServiceProvider(),
        );
        $realRedactor = $builder->build()->get(SensitiveDataRedactorInterface::class);
        self::assertInstanceOf(SensitiveDataRedactorInterface::class, $realRedactor);
        $redactor = $this->createMock(SensitiveDataRedactorInterface::class);
        $redactor->expects(self::once())->method('redactJsonLike')->willReturnCallback(
            static function (mixed $value, RedactionContext $context) use ($realRedactor, $normalized): mixed {
                $normalized->append($value);

                return $realRedactor->redactJsonLike($value, $context);
            },
        );
        $builder->instance(SensitiveDataRedactorInterface::class, $redactor);
        $container = $builder->build();
        $handler = $container->get(ErrorHandlerInterface::class);
        self::assertInstanceOf(ErrorHandler::class, $handler);
        $registry = $container->get(ExceptionMapperRegistry::class);
        self::assertInstanceOf(ExceptionMapperRegistry::class, $registry);
        $tags = $container->get(TagRegistry::class)->all(ReservedTags::ERROR_MAPPER);
        self::assertCount(1, $tags);
        self::assertSame(HttpAppProblemMapper::class, $tags[0]->id());
        self::assertSame(800, $tags[0]->priority());
        self::assertSame([], $tags[0]->meta());
        self::assertNull($registry->map(new \RuntimeException('private-unrelated')));
        $mappingAttempts = new \ArrayObject();
        $recordingMapper = $this->createMock(ExceptionMapperInterface::class);
        $recordingMapper->expects(self::once())->method('map')->willReturnCallback(
            static function (\Throwable $failure) use ($mappingAttempts): ?ErrorDescriptor {
                $mappingAttempts->append($failure);

                return null;
            },
        );
        // A higher-priority observer proves the real handler enters tag-first discovery once.
        // It does not replace the HttpApp mapper or claim ownership of any failure.
        $builder->instance('test.mapping-observer', $recordingMapper);
        $builder->tag(ReservedTags::ERROR_MAPPER, 'test.mapping-observer', 900);
        $container = $builder->build();
        $handler = $container->get(ErrorHandlerInterface::class);
        self::assertInstanceOf(ErrorHandler::class, $handler);
        $probe = $this->createMock(ErrorHandlerInterface::class);
        $probe->expects(self::once())->method('handle')->willReturnCallback(
            static function (
                \Throwable $failure,
                ?ErrorHandlingContext $context = null,
            ) use ($handler, $primary, $descriptors, $mappingAttempts): ErrorDescriptor {
                $primary->append($failure);
                $descriptor = $handler->handle($failure, $context);
                self::assertSame([$failure], $mappingAttempts->getArrayCopy());
                $descriptors->append($descriptor);

                return $descriptor;
            },
        );
        // The observer delegates exactly once to the real handler. Rendering remains entirely canonical.
        $boundary = new HttpErrorHandlingMiddleware(
            $probe,
            $container->get(ErrorDescriptorToProblemDetailsAdapter::class),
            $container->get(ProblemDetailsJsonRenderer::class),
            $responses,
            $streams,
        );
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = new Pipeline(
            [$boundary, $container->get(RouterMiddleware::class), $container->get(ActionDispatchMiddleware::class)],
            $terminal,
            true,
            [RouteDispatchState::ATTRIBUTE],
            true,
        );

        return [$pipeline, $response, $primary, $descriptors, $normalized];
    }

    /** @param array<string, mixed> $attributes */
    private function request(string $method, string $path, array $attributes = []): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn('example.test');
        $uri->method('getQuery')->willReturn('search=private-query');
        /** @var ServerRequestInterface&Stub $request */
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getAttributes')->willReturn($attributes);
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => \array_key_exists($name, $attributes)
                ? $attributes[$name] : $default,
        );
        $request->method('getQueryParams')->willReturn(['search' => 'private-query']);
        $request->method('getCookieParams')->willReturn(['session' => 'private-cookie']);
        $request->method('getParsedBody')->willReturn(['value' => 'private-body']);
        $request->method('getHeaders')->willReturn(['X-Test' => ['private-header']]);
        $request->method('withAttribute')->willReturnCallback(
            fn (string $name, mixed $value): ServerRequestInterface => $this->request(
                $method,
                $path,
                [...$attributes, $name => $value],
            ),
        );

        return $request;
    }
}

final class ErrorFlowArgument
{
}

final class ErrorFlowArgumentAction
{
    public function __invoke(ErrorFlowArgument $service): never
    {
        throw new \LogicException('unexpected-action-execution');
    }
}

final class ErrorFlowResultAction
{
    public function __construct(
        private readonly string $mode,
        private readonly \ArrayObject $events,
        private readonly \Throwable $primary,
    ) {
    }

    public function __invoke(): mixed
    {
        $this->events->append('action');

        return match ($this->mode) {
            'unsupported' => 1.5,
            'disabled-json' => ['private' => 'private-return-value'],
            'disabled-html' => '<p>private-return-value</p>',
            'unrelated' => throw $this->primary,
            default => throw new \LogicException('unexpected-action-execution'),
        };
    }
}

final class ErrorFlowGraphRouter implements RouterInterface
{
    public function match(string $method, string $path, ?string $host = null): ?RouteMatch
    {
        throw new \LogicException('unexpected-matching');
    }

    public function allowedMethods(string $path, ?string $host = null): array
    {
        throw new \LogicException('unexpected-method-enumeration');
    }
}

final class ErrorFlowGraphTransport implements ResponseFactoryInterface, StreamFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new \LogicException('unexpected-response-construction');
    }

    public function createStream(string $content = ''): StreamInterface
    {
        throw new \LogicException('unexpected-stream-construction');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('unexpected-stream-file');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('unexpected-stream-resource');
    }
}

final class ErrorFlowGraphMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        throw new \LogicException('unexpected-middleware-execution');
    }
}
