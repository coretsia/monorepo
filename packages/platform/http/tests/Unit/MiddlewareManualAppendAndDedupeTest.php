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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareManualAppendAndDedupeTest extends TestCase
{
    public function testTaggedFirstThenManualAppendPreservesFoundationOrderAndFirstOccurrence(): void
    {
        $tags = new TagRegistry();
        $tag = ReservedTags::HTTP_MIDDLEWARE_SYSTEM;
        $tags->add($tag, ManualMiddlewareGamma::class, 10);
        $tags->add($tag, ManualMiddlewareBeta::class, 50);
        $tags->add($tag, ManualMiddlewareAlpha::class, 50);
        $tags->add($tag, ManualMiddlewareBeta::class, 999); // First tag registration wins.

        $http = self::httpConfig();
        $http['middleware']['system'] = [
            ManualMiddlewareAlpha::class,
            ManualMiddlewareDelta::class,
            ManualMiddlewareDelta::class,
            ManualMiddlewareGamma::class,
        ];
        $http['middleware']['app'] = [ManualMiddlewareEpsilon::class];

        self::assertSame(
            [
                ManualMiddlewareAlpha::class,
                ManualMiddlewareBeta::class,
                ManualMiddlewareGamma::class,
            ],
            \array_map(static fn ($entry): string => $entry->id(), $tags->all($tag)),
        );
        self::assertSame(
            [
                ManualMiddlewareAlpha::class,
                ManualMiddlewareBeta::class,
                ManualMiddlewareGamma::class,
                ManualMiddlewareDelta::class,
                ManualMiddlewareEpsilon::class,
            ],
            new HttpMiddlewareStackBuilder($tags)->build($http),
        );
    }

    public function testDisabledTaggedServiceDoesNotBlockManualParticipation(): void
    {
        $http = self::httpConfig();
        $http['metrics']['enabled'] = false;
        $http['middleware']['app'] = [ManualMiddlewareAlpha::class];
        $tags = new TagRegistry();
        $tags->add(
            ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            ManualMiddlewareAlpha::class,
            20,
            ['toggle' => 'http.metrics.enabled'],
        );

        self::assertSame(
            [ManualMiddlewareAlpha::class],
            new HttpMiddlewareStackBuilder($tags)->build($http),
        );
    }

    public function testCrossSlotDuplicateFailsWithFixedReason(): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM, ManualMiddlewareAlpha::class);
        $http = self::httpConfig();
        $http['middleware']['route'] = [ManualMiddlewareAlpha::class];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-middleware-cross-slot-duplicate');
        new HttpMiddlewareStackBuilder($tags)->build($http);
    }

    public function testExplicitlyRegisteredManualServiceUsesOnlyCanonicalContainer(): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareAlpha::class];
        $service = new ManualMiddlewareAlpha();
        $builder = new ContainerBuilder(self::containerConfig(false));
        $builder->instance(ManualMiddlewareAlpha::class, $service);
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);
        $pipeline = self::pipeline($builder->build(), $http, $terminal);

        // Verify the same instance is resolved by the canonical PSR-11 container.
        self::assertSame($service, $builder->build()->get(ManualMiddlewareAlpha::class));
        self::assertSame($response, $pipeline->handle($request));
        self::assertSame(1, $service->calls);
    }

    #[DataProvider('autowiringSettings')]
    public function testUnregisteredConcreteClassRequiresFoundationAutowiring(
        bool $enabled,
        bool $reflection,
        bool $shouldResolve,
    ): void {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareBeta::class];
        $container = new ContainerBuilder(self::containerConfig($enabled, $reflection))->build();

        if (!$shouldResolve) {
            $this->expectException(ContainerExceptionInterface::class);
            self::pipeline($container, $http);

            return;
        }

        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willReturn($response);
        $pipeline = self::pipeline($container, $http, $terminal);
        self::assertInstanceOf(Pipeline::class, $pipeline);
        self::assertSame($response, $pipeline->handle($request));
    }

    /** @return iterable<string, array{bool, bool, bool}> */
    public static function autowiringSettings(): iterable
    {
        yield 'off' => [false, false, false];
        yield 'reflection-off' => [true, false, false];
        yield 'concrete-off' => [false, true, false];
        yield 'enabled' => [true, true, true];
    }

    #[DataProvider('unresolvableManualIds')]
    public function testUnresolvableManualServiceIdsFailDeterministically(string $id): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [$id];
        $container = new ContainerBuilder(self::containerConfig(true, true))->build();

        $this->expectException(ContainerExceptionInterface::class);
        self::pipeline($container, $http);
    }

    /** @return iterable<string, array{string}> */
    public static function unresolvableManualIds(): iterable
    {
        yield 'nonexistent' => ['Coretsia\\Tests\\ThisMiddlewareDoesNotExist'];
        yield 'abstract' => [ManualAbstractMiddleware::class];
        yield 'interface' => [MiddlewareInterface::class];
        yield 'unresolvable-constructor' => [ManualMiddlewareNeedsScalar::class];
    }

    public function testIncompatibleManualServiceIsRejectedAfterCanonicalResolution(): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareAlpha::class];
        $builder = new ContainerBuilder(self::containerConfig(false));
        $builder->instance(ManualMiddlewareAlpha::class, new \stdClass());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-middleware-instance-invalid');
        self::pipeline($builder->build(), $http);
    }

    public function testSourceAndCompiledRuntimeResolveTheSameManualMiddleware(): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareAlpha::class];
        $config = self::containerConfig(false);
        $config['http'] = $http;
        $definitions = new ContainerDefinitionBuilder()
            ->classService(ManualMiddlewareAlpha::class, ManualMiddlewareAlpha::class)
            ->build();
        $source = new ContainerBuilder($config)->applyDefinitions($definitions)->build();

        $graph = new ContainerCompiler(
            new NoopTracer(),
            new NoopMeter(),
            new NoopLogger(),
            new Stopwatch(),
        )->compile($definitions);
        $envelope = new CompiledContainerBuilder(new ArtifactEnvelopeFactory())->build(
            $graph,
            \str_repeat('a', 64),
        );
        $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $envelope,
            new RuntimeContainerSeedSet([
                ConfigRepositoryInterface::class => new ArrayConfigRepository($config),
                ModulePlan::class => new ModulePlan('web', [], [], [], []),
                RuntimePathContext::class => new RuntimePathContext('/app', '/app/var/cache'),
            ]),
        );

        foreach ([$source, $compiled] as $container) {
            $middleware = $container->get(ManualMiddlewareAlpha::class);
            self::assertInstanceOf(ManualMiddlewareAlpha::class, $middleware);

            $request = $this->createStub(ServerRequestInterface::class);
            $response = $this->createStub(ResponseInterface::class);
            $terminal = $this->createMock(RequestHandlerInterface::class);
            $terminal->expects(self::once())->method('handle')->with($request)->willReturn($response);

            $pipeline = self::pipeline($container, $http, $terminal);
            self::assertSame($response, $pipeline->handle($request));
            self::assertSame(1, $middleware->calls);
        }

        // Compiled hydration preserves the same explicit-service resolution when
        // the global Foundation autowiring switch is disabled.
        self::assertSame(
            $source->get(ManualMiddlewareAlpha::class)::class,
            $compiled->get(ManualMiddlewareAlpha::class)::class,
        );
    }

    public function testSourceAndCompiledConcreteAutowiringPolicyHasTheSameResult(): void
    {
        $http = self::httpConfig();
        $http['middleware']['app'] = [ManualMiddlewareBeta::class];

        foreach ([false, true] as $enabled) {
            $config = self::containerConfig($enabled, $enabled);
            $config['http'] = $http;
            $definitions = new ContainerDefinitionBuilder()->build();
            $source = new ContainerBuilder($config)->build();
            $compiled = self::compiledEmptyContainer($definitions, $config);

            foreach ([$source, $compiled] as $container) {
                if ($enabled) {
                    self::assertInstanceOf(ManualMiddlewareBeta::class, $container->get(ManualMiddlewareBeta::class));
                    self::assertInstanceOf(
                        Pipeline::class,
                        self::pipeline($container, $http),
                    );
                } else {
                    try {
                        self::pipeline($container, $http);
                        self::fail(
                            'Unregistered concrete middleware must not enter the pipeline with autowiring disabled.',
                        );
                    } catch (ContainerExceptionInterface) {
                        self::assertTrue(true);
                    }
                }
            }
        }
    }

    public function testStackBuilderAndFactoryDelegateResolutionWithoutPrivateAutowiring(): void
    {
        foreach ([HttpMiddlewareStackBuilder::class, HttpServiceFactory::class] as $class) {
            $source = \file_get_contents(new \ReflectionClass($class)->getFileName());
            self::assertIsString($source);
            foreach (
                [
                    'new \\ReflectionClass(',
                    'new \\ReflectionMethod(',
                    'class_exists(',
                    'get_declared_classes(',
                    'canAutowire(',
                    'getDefinitions(',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    /** @param array<string, mixed> $http */
    private static function pipeline(
        ContainerInterface $container,
        array $http,
        ?RequestHandlerInterface $terminal = null,
    ): Pipeline {
        return HttpServiceFactory::pipeline(
            $container,
            new ArrayConfigRepository(['http' => $http]),
            new HttpMiddlewareStackBuilder(new TagRegistry()),
            $terminal ?? new class() implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \LogicException('terminal-not-supplied');
                }
            },
        );
    }

    /** @return array<string, mixed> */
    private static function httpConfig(): array
    {
        return require __DIR__ . '/../../config/http.php';
    }

    /** @return array<string, mixed> */
    private static function containerConfig(bool $autowire, ?bool $reflection = null): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => $autowire,
                    'allow_reflection_for_concrete' => $reflection ?? $autowire,
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $config */
    private static function compiledEmptyContainer(
        ContainerDefinitionSet $set,
        array $config,
    ): Container {
        $graph = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch())->compile(
            $set,
        );
        $envelope = new CompiledContainerBuilder(new ArtifactEnvelopeFactory())->build($graph, \str_repeat('b', 64));

        return new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $envelope,
            new RuntimeContainerSeedSet([
                ConfigRepositoryInterface::class => new ArrayConfigRepository($config),
                ModulePlan::class => new ModulePlan('web', [], [], [], []),
                RuntimePathContext::class => new RuntimePathContext('/app', '/app/var/cache'),
            ]),
        );
    }
}

final class ManualMiddlewareAlpha implements MiddlewareInterface
{
    public int $calls = 0;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ++$this->calls;

        return $handler->handle($request);
    }
}

class ManualMiddlewareBeta implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final class ManualMiddlewareGamma extends ManualMiddlewareBeta
{}

final class ManualMiddlewareDelta extends ManualMiddlewareBeta
{}

final class ManualMiddlewareEpsilon extends ManualMiddlewareBeta
{}

abstract class ManualAbstractMiddleware implements MiddlewareInterface
{}

final class ManualMiddlewareNeedsScalar implements MiddlewareInterface
{
    public function __construct(string $required)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}
