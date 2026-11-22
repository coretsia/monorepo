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
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Clock\FrozenClock;
use Coretsia\Foundation\Clock\SystemClock;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Debug\DebugRouter;
use Coretsia\Platform\Http\HttpKernelInterface;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Coretsia\Platform\Http\Routing\FallbackRouter;
use Coretsia\Platform\Http\Routing\FallbackRouterHandler;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpPsrFactoryBootstrapReadinessTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMissingExplicitBuiltInImplementationFailsBeforeHttpConstructionOrUnitOfWork(): void
    {
        $missing = 'nyholm';
        $restoreAutoload = self::hideSelectedFactory($missing);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $runtime = $this->createMock(KernelRuntimeInterface::class);
        $runtime->expects(self::never())->method('beginUnitOfWork');
        $meter = $this->createMock(MeterPortInterface::class);
        $meter->expects(self::never())->method('increment');
        $meter->expects(self::never())->method('observe');
        $tracer = $this->createMock(TracerPortInterface::class);
        $tracer->expects(self::never())->method('startSpan');
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects(self::never())->method('process');

        try {
            self::guard()->assertEntrypointAllowed(
                self::config($missing),
                self::modulePlan(),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
            $container->get(HttpKernelInterface::class);
        } catch (\RuntimeException $exception) {
            self::assertSame('http-factory-implementation-unavailable', $exception->getMessage());

            return;
        } finally {
            $restoreAutoload();
        }

        self::fail('A missing selected implementation must not be replaced by an installed alternative.');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('invalidBuiltInBindings')]
    public function testInvalidSelectedFactoryContractsOrLaterOverridesFailBeforeHttpConstruction(
        string $case,
        string $expectedFailure,
    ): void {
        $class = HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES['nyholm']['response'];
        if (!\class_exists($class)) {
            \class_alias(HttpBootstrapAutoloadFixture::class, $class);
        }

        $responses = $case === 'invalid-response' || $case === 'invalid-both'
            ? new \stdClass() : $this->createStub(ResponseFactoryInterface::class);
        $streams = $case === 'invalid-stream' || $case === 'invalid-both'
            ? new \stdClass() : $this->createStub(StreamFactoryInterface::class);
        $lookups = [];
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $id) use ($responses, $streams, &$lookups): object {
                $lookups[] = $id;

                return match ($id) {
                    ResponseFactoryInterface::class => $responses,
                    StreamFactoryInterface::class => $streams,
                    default => throw new \LogicException('http-entrypoint-resolved-before-readiness'),
                };
            },
        );

        try {
            self::guard()->assertEntrypointAllowed(
                self::config('nyholm'),
                self::modulePlan(),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
            $container->get(HttpKernelInterface::class);
        } catch (\RuntimeException $exception) {
            self::assertSame($expectedFailure, $exception->getMessage());
            self::assertSame([ResponseFactoryInterface::class, StreamFactoryInterface::class], $lookups);

            return;
        }

        self::fail('Invalid effective PSR-17 factories must fail before constructing HttpKernel.');
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidBuiltInBindings(): iterable
    {
        yield 'incompatible-response' => ['invalid-response', 'http-factory-contract-invalid'];
        yield 'incompatible-stream' => ['invalid-stream', 'http-factory-contract-invalid'];
        yield 'incompatible-both' => ['invalid-both', 'http-factory-contract-invalid'];
        yield 'later-compatible-service-or-alias-override' => [
            'later-compatible-override',
            'http-factory-selection-mismatch',
        ];
    }

    public function testExternalCompatibleEffectiveBindingsPermitOnlyPostGuardHttpResolution(): void
    {
        $responses = $this->createStub(ResponseFactoryInterface::class);
        $streams = $this->createStub(StreamFactoryInterface::class);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $calls = [];
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $id) use ($responses, $streams, $kernel, &$calls): object {
                $calls[] = $id;

                return match ($id) {
                    ResponseFactoryInterface::class => $responses,
                    StreamFactoryInterface::class => $streams,
                    HttpKernelInterface::class => $kernel,
                    default => throw new \LogicException('unexpected-http-service'),
                };
            },
        );

        self::guard()->assertEntrypointAllowed(
            self::config('external'),
            self::modulePlan(),
            RuntimeDriverContributions::fromDrivers([], []),
            $container,
        );
        self::assertSame($kernel, $container->get(HttpKernelInterface::class));
        self::assertSame([
            ResponseFactoryInterface::class,
            StreamFactoryInterface::class,
            HttpKernelInterface::class,
        ], $calls);
    }

    #[DataProvider('missingExternalPorts')]
    public function testMissingExternalPortFailsCanonicalGraphCompletenessBeforeAnyHttpLifecycle(
        bool $withResponse,
        bool $withStream,
    ): void {
        $http = require __DIR__ . '/../../config/http.php';
        $providerDefinitions = new ContainerDefinitionBuilder();
        new HttpServiceProvider()->define($providerDefinitions, new ContainerDefinitionContext(['http' => $http]));
        $required = $providerDefinitions->build()->requiredServiceIds();
        self::assertContains(ResponseFactoryInterface::class, $required);
        self::assertContains(StreamFactoryInterface::class, $required);
        self::assertContains(ClockInterface::class, $required);
        self::assertContains(ModulePlan::class, $required);
        self::assertContains(RuntimeDiagnosticsInput::class, $required);
        self::assertContains(SensitiveDataRedactorInterface::class, $required);
        self::assertContains(SensitiveKeyClassifierInterface::class, $required);
        $definitions = new ContainerDefinitionBuilder();
        $definitions->requireService(ResponseFactoryInterface::class);
        $definitions->requireService(StreamFactoryInterface::class);
        if ($withResponse) {
            $definitions->classService(ResponseFactoryInterface::class, HttpBootstrapAutoloadFixture::class);
        }
        if ($withStream) {
            $definitions->classService(StreamFactoryInterface::class, HttpBootstrapAutoloadFixture::class);
        }

        $set = $definitions->build();
        $graph = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch())
            ->compile($set);
        $this->expectException(ContainerDefinitionInvalidException::class);
        new ContainerGraphCompletenessValidator()->validate($graph, $set);
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function missingExternalPorts(): iterable
    {
        yield 'both-missing' => [false, false];
        yield 'response-missing' => [false, true];
        yield 'stream-missing' => [true, false];
    }

    public function testMissingClockFailsCanonicalGraphCompletenessWithValidExternalFactories(): void
    {
        $http = require __DIR__ . '/../../config/http.php';
        $providerDefinitions = new ContainerDefinitionBuilder();

        new HttpServiceProvider()->define(
            $providerDefinitions,
            new ContainerDefinitionContext(['http' => $http]),
        );

        $required = $providerDefinitions->build()->requiredServiceIds();
        self::assertContains(ClockInterface::class, $required);
        self::assertContains(ResponseFactoryInterface::class, $required);
        self::assertContains(StreamFactoryInterface::class, $required);

        $definitions = new ContainerDefinitionBuilder();
        $definitions
            ->requireService(ClockInterface::class)
            ->requireService(ResponseFactoryInterface::class)
            ->requireService(StreamFactoryInterface::class)
            ->classService(
                ResponseFactoryInterface::class,
                HttpBootstrapCompatibleFactory::class,
            )
            ->classService(
                StreamFactoryInterface::class,
                HttpBootstrapCompatibleFactory::class,
            );

        $set = $definitions->build();
        $graph = new ContainerCompiler(
            new NoopTracer(),
            new NoopMeter(),
            new NoopLogger(),
            new Stopwatch(),
        )->compile($set);

        $services = $graph->toArray()['services'];

        self::assertSame(
            HttpBootstrapCompatibleFactory::class,
            $services[ResponseFactoryInterface::class]['construction']['class'],
        );
        self::assertSame(
            HttpBootstrapCompatibleFactory::class,
            $services[StreamFactoryInterface::class]['construction']['class'],
        );
        self::assertArrayNotHasKey(ClockInterface::class, $services);
        self::assertArrayNotHasKey(ClockInterface::class, $graph->toArray()['aliases']);

        try {
            new ContainerGraphCompletenessValidator()->validate($graph, $set);
            self::fail('A missing required clock binding must be rejected.');
        } catch (ContainerDefinitionInvalidException $exception) {
            self::assertSame(
                ContainerDefinitionInvalidException::REASON_REQUIRED_SERVICE_INVALID,
                $exception->reason(),
            );
        }
    }

    public function testFoundationClockAndFinalExternalBindingsAreConsumedByTerminalServices(): void
    {
        $http = require __DIR__ . '/../../config/http.php';
        $http['factory'] = 'external';
        $http['metrics']['enabled'] = false;

        $config = [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'http' => $http,
            'kernel' => ['runtime' => ['http_driver' => 'http.classic']],
        ];

        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);

        new FoundationServiceProvider()->define($definitions, $context);
        new RedactionServiceProvider()->define($definitions, $context);
        new HttpServiceProvider()->define($definitions, $context);

        // The lifecycle boundary is not executed by these terminal tests.
        $definitions
            ->classService(KernelRuntimeInterface::class, \stdClass::class)
            ->classService(RuntimeDriverResolver::class, RuntimeDriverResolver::class)
            ->classService(
                HttpBootstrapCompatibleFactory::class,
                HttpBootstrapCompatibleFactory::class,
            )
            ->alias(
                ResponseFactoryInterface::class,
                HttpBootstrapCompatibleFactory::class,
            )
            ->alias(
                StreamFactoryInterface::class,
                HttpBootstrapCompatibleFactory::class,
            );

        $set = $definitions->build();
        $compiler = new ContainerCompiler(
            new NoopTracer(),
            new NoopMeter(),
            new NoopLogger(),
            new Stopwatch(),
        );

        $graph = $compiler->compile($set);
        new ContainerGraphCompletenessValidator()->validate($graph, $set);

        self::assertContains(ClockInterface::class, $set->requiredServiceIds());
        self::assertSame(
            SystemClock::class,
            $graph->toArray()['aliases'][ClockInterface::class],
        );
        self::assertSame(
            HttpBootstrapCompatibleFactory::class,
            $graph->toArray()['aliases'][ResponseFactoryInterface::class],
        );
        self::assertSame(
            HttpBootstrapCompatibleFactory::class,
            $graph->toArray()['aliases'][StreamFactoryInterface::class],
        );

        $withoutClock = ContainerDefinitionSet::fromValidatedState(
            \array_values(
                \array_filter(
                    $set->toDescriptorStream(),
                    static fn (array $operation): bool => $operation['kind'] !== 'alias'
                        || $operation['alias'] !== ClockInterface::class,
                ),
            ),
            $set->requiredServiceIds(),
        );

        try {
            $compiler->compile($withoutClock);
            self::fail('The HTTP graph must not compile without its clock binding.');
        } catch (ContainerCompileFailedException $exception) {
            self::assertSame(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
                $exception->reason(),
            );
        }

        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config, []));
        $builder->instance(TagRegistry::class, $builder->tagRegistry());
        $builder->instance(ModulePlan::class, self::modulePlan());
        $builder->instance(RuntimeDiagnosticsInput::class, new RuntimeDiagnosticsInput('test', false));
        $builder->instance(
            RuntimePathContext::class,
            new RuntimePathContext('/test', '/test/var/cache'),
        );
        $builder->applyDefinitions($set);

        $baseline = $builder->build();

        $systemClock = $baseline->get(ClockInterface::class);
        self::assertInstanceOf(SystemClock::class, $systemClock);
        self::assertSame(
            $baseline->get(SystemClock::class),
            $systemClock,
        );

        $baselineRouter = $baseline->get(FallbackRouter::class);
        self::assertInstanceOf(FallbackRouter::class, $baselineRouter);
        self::assertSame(
            $baseline->get(DebugRouter::class),
            new \ReflectionProperty(FallbackRouter::class, 'debug')->getValue($baselineRouter),
        );
        self::assertSame(
            $systemClock,
            new \ReflectionProperty(FallbackRouter::class, 'clock')->getValue($baselineRouter),
        );

        $clock = new FrozenClock(new \DateTimeImmutable('@1700000000'));

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn("{\"ok\":true,\"ts\":1700000000}\n");

        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())
            ->method('createStream')
            ->with("{\"ok\":true,\"ts\":1700000000}\n")
            ->willReturn($stream);

        $response = $this->createMock(ResponseInterface::class);
        $typedResponse = $this->createMock(ResponseInterface::class);
        $cacheControlledResponse = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createStub(ResponseInterface::class);

        $finalResponse->method('getStatusCode')->willReturn(200);
        $finalResponse->method('getBody')->willReturn($stream);

        $response->expects(self::once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($typedResponse);
        $response->expects(self::never())->method('withBody');

        $typedResponse->expects(self::once())
            ->method('withHeader')
            ->with('Cache-Control', 'no-store')
            ->willReturn($cacheControlledResponse);
        $typedResponse->expects(self::never())->method('withBody');

        $cacheControlledResponse->expects(self::never())->method('withHeader');
        $cacheControlledResponse->expects(self::once())
            ->method('withBody')
            ->with(self::identicalTo($stream))
            ->willReturn($finalResponse);

        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())
            ->method('createResponse')
            ->with(200)
            ->willReturn($response);

        $builder->instance(ResponseFactoryInterface::class, $responses);
        $builder->instance(StreamFactoryInterface::class, $streams);
        $builder->instance(ClockInterface::class, $clock);

        $container = $builder->build();

        self::guard()->assertEntrypointAllowed(
            new ArrayConfigRepository($config, []),
            self::modulePlan(),
            RuntimeDriverContributions::fromDrivers([], []),
            $container,
        );

        self::assertSame($responses, $container->get(ResponseFactoryInterface::class));
        self::assertSame($streams, $container->get(StreamFactoryInterface::class));
        self::assertSame($clock, $container->get(ClockInterface::class));

        $router = $container->get(FallbackRouter::class);
        $fallbackResponses = $container->get(FallbackResponses::class);
        $handler = $container->get(RequestHandlerInterface::class);

        self::assertInstanceOf(FallbackRouter::class, $router);
        self::assertInstanceOf(FallbackResponses::class, $fallbackResponses);
        self::assertInstanceOf(FallbackRouterHandler::class, $handler);

        self::assertSame($container->get(FallbackRouterHandler::class), $handler);
        self::assertSame(
            $router,
            new \ReflectionProperty(FallbackRouterHandler::class, 'router')->getValue($handler),
        );
        self::assertSame(
            $clock,
            new \ReflectionProperty(FallbackRouter::class, 'clock')->getValue($router),
        );
        self::assertSame(
            $fallbackResponses,
            new \ReflectionProperty(FallbackRouter::class, 'responses')->getValue($router),
        );
        self::assertSame(
            $responses,
            new \ReflectionProperty(FallbackResponses::class, 'responses')->getValue($fallbackResponses),
        );
        self::assertSame(
            $streams,
            new \ReflectionProperty(FallbackResponses::class, 'streams')->getValue($fallbackResponses),
        );

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/ping');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $actual = $handler->handle($request);

        self::assertSame($finalResponse, $actual);
        self::assertSame(200, $actual->getStatusCode());
        self::assertSame("{\"ok\":true,\"ts\":1700000000}\n", (string) $actual->getBody());
    }

    public function testReadinessUsesExplicitKernelDriverContributionsWithoutRewritingDriverSelection(): void
    {
        $responses = $this->createStub(ResponseFactoryInterface::class);
        $streams = $this->createStub(StreamFactoryInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturnCallback(
            static fn (string $id): object => match ($id) {
                ResponseFactoryInterface::class => $responses,
                StreamFactoryInterface::class => $streams,
                default => throw new \LogicException('unexpected-http-service'),
            },
        );
        $contributions = RuntimeDriverContributions::fromDrivers([HttpDriver::WORKER], []);
        self::guard()->assertEntrypointAllowed(
            self::config('external'),
            self::modulePlan(),
            $contributions,
            $container,
        );
        self::assertSame([HttpDriver::WORKER], $contributions->httpDrivers());
        self::assertSame(
            HttpDriver::WORKER,
            new RuntimeDriverResolver()->resolve(self::config('external'), $contributions)->httpDriver(),
        );
    }

    public function testUnselectedHttpModuleFailsBeforeFactoryOrKernelResolution(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('http-runtime-module-not-enabled');
        self::guard()->assertEntrypointAllowed(
            self::config('external'),
            new ModulePlan('web', [], [], [], []),
            RuntimeDriverContributions::fromDrivers([], []),
            $container,
        );
    }

    public function testConflictingOwnerDriversFailBeforeAnyPsrFactoryOrHttpServiceResolution(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $contributions = RuntimeDriverContributions::fromDrivers(
            [HttpDriver::WORKER, HttpDriver::FRANKENPHP],
            [],
        );

        try {
            self::guard()->assertEntrypointAllowed(
                self::config('external'),
                self::modulePlan(),
                $contributions,
                $container,
            );
        } catch (RuntimeDriverConflictException $exception) {
            self::assertSame(
                RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
                $exception->reason(),
            );

            return;
        }

        self::fail('Conflicting owner-supplied HTTP drivers must not be discarded.');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAutoloadableUnselectedFactoryCannotReplaceMissingSelectedImplementation(): void
    {
        $missing = 'nyholm';
        $restoreAutoload = self::hideSelectedFactory($missing);
        $unselected = HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES['guzzlehttp'];

        foreach (\array_unique([$unselected['response'], $unselected['stream']]) as $class) {
            if (!\class_exists($class, false)) {
                \class_alias(HttpBootstrapCompatibleFactory::class, $class);
            }

            self::assertTrue(\class_exists($class, false));
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('http-factory-implementation-unavailable');
        try {
            self::guard()->assertEntrypointAllowed(
                self::config($missing),
                self::modulePlan(),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
        } finally {
            $restoreAutoload();
        }
    }

    /** @return \Closure(): void */
    private static function hideSelectedFactory(string $selection): \Closure
    {
        $blocked = \array_values(\array_unique(HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES[$selection]));
        foreach ($blocked as $class) {
            self::assertFalse(\class_exists($class, false));
        }

        $autoloaders = \spl_autoload_functions();
        foreach ($autoloaders as $loader) {
            \spl_autoload_unregister($loader);
        }

        $filtered = static function (string $class) use ($blocked, $autoloaders): void {
            if (\in_array($class, $blocked, true)) {
                return;
            }

            foreach ($autoloaders as $loader) {
                $loader($class);
                if (\class_exists($class, false)
                    || \interface_exists($class, false)
                    || \trait_exists($class, false)
                    || \enum_exists($class, false)
                ) {
                    return;
                }
            }
        };
        \spl_autoload_register($filtered);

        return static function () use ($filtered, $autoloaders): void {
            \spl_autoload_unregister($filtered);
            foreach ($autoloaders as $loader) {
                \spl_autoload_register($loader);
            }
        };
    }

    private static function guard(): HttpRuntimeEntrypointGuard
    {
        return new HttpRuntimeEntrypointGuard(new RuntimeDriverResolver());
    }

    private static function config(string $factory): ConfigRepositoryInterface
    {
        return new ArrayConfigRepository([
            'kernel' => ['runtime' => ['http_driver' => 'http.classic']],
            'http' => ['factory' => $factory],
        ], []);
    }

    private static function modulePlan(): ModulePlan
    {
        $redaction = ModuleId::fromString('platform.redaction');
        $http = ModuleId::fromString('platform.http');

        return new ModulePlan(
            app: 'web',
            enabled: [$redaction, $http],
            excluded: [],
            topologicalOrder: [$redaction, $http],
            modules: [
                new ModulePlanEntry($redaction, 'coretsia/platform-redaction'),
                new ModulePlanEntry($http, 'coretsia/platform-http', [$redaction]),
            ],
        );
    }
}

/** Only marks the configured vendor FQCN autoloadable in isolated failure tests. */
final class HttpBootstrapAutoloadFixture
{
}

/** Test-only compatible implementation used to model an installed unselected provider. */
final class HttpBootstrapCompatibleFactory implements ResponseFactoryInterface, StreamFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new \LogicException('http-bootstrap-fixture-response-not-executable');
    }

    public function createStream(string $content = ''): StreamInterface
    {
        throw new \LogicException('http-bootstrap-fixture-stream-not-executable');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('http-bootstrap-fixture-file-not-executable');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('http-bootstrap-fixture-resource-not-executable');
    }
}
