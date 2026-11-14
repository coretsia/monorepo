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
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
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
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\BadJsonException;
use Coretsia\Platform\Http\Exception\BodyTooLargeException;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Middleware\RequestBodySizeLimitMiddleware;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
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
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ProblemDetailsOutermostPipelineIntegrationTest extends TestCase
{
    #[DataProvider('selectionCases')]
    public function testSelectionAloneControlsOutermostBoundaryInSourceAndCompiledTagGraphs(bool $selected): void
    {
        [$set, $http] = self::definitions($selected);
        $operations = $set->toDescriptorStream();
        $sourceBuilder = new ContainerBuilder(['http' => $http]);
        $sourceBuilder->applyDefinitions($set);
        $source = $sourceBuilder->tagRegistry();
        $compiled = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch())
            ->compile($set)->toArray();

        $sourceEntries = \array_map(
            static fn ($entry): array => [
                'id' => $entry->id(),
                'meta' => $entry->meta(),
                'priority' => $entry->priority(),
            ],
            $source->all(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE),
        );
        self::assertSame($sourceEntries, $compiled['tags'][ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE]);
        $boundaryEntries = \array_values(
            \array_filter(
                $sourceEntries,
                static fn (array $entry): bool => $entry['id'] === HttpErrorHandlingMiddleware::class,
            ),
        );
        self::assertCount($selected ? 1 : 0, $boundaryEntries);
        if ($selected) {
            self::assertSame(1000, $boundaryEntries[0]['priority']);
            self::assertSame([], $boundaryEntries[0]['meta']);
        }
        $ordered = new HttpMiddlewareStackBuilder($source)->build($http);
        self::assertSame(
            $selected ? HttpErrorHandlingMiddleware::class : RequestBodySizeLimitMiddleware::class,
            $ordered[0],
        );
        self::assertCount(
            $selected ? 7 : 6,
            \array_filter(
                $operations,
                static fn (array $op): bool => ($op['kind'] ?? null) === 'tag'
                    && ($op['tag'] ?? null) === ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            ),
        );
    }

    /** @return iterable<string, array{bool}> */
    public static function selectionCases(): iterable
    {
        yield 'explicitly-selected' => [true];
        yield 'not-selected' => [false];
    }

    public function testSelectedProblemBoundaryResolvesThroughCanonicalSourceAndCompiledContainers(): void
    {
        [$set, $http] = self::definitions(true);
        $config = [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'http' => $http,
        ];
        $sourceBuilder = new ContainerBuilder($config);
        $sourceBuilder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config));
        $sourceBuilder->instance(TagRegistry::class, $sourceBuilder->tagRegistry());
        $source = $sourceBuilder->applyDefinitions($set)->build();
        $graph = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch())
            ->compile($set);
        new ContainerGraphCompletenessValidator()->validate($graph, $set);
        $envelope = new ArtifactEnvelopeFactory()->container(
            \str_repeat('a', 64),
            ['kind' => 'compiled', 'compiled' => true, ...$graph->toArray()],
        );
        $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
            $envelope,
            new RuntimeContainerSeedSet([
                ConfigRepositoryInterface::class => new ArrayConfigRepository($config),
                ModulePlan::class => new ModulePlan('web', [], [], [], []),
                RuntimePathContext::class => new RuntimePathContext('/test', '/test/var/cache'),
            ]),
        );

        foreach ([$source, $compiled] as $container) {
            self::assertInstanceOf(HttpProblemParityFactories::class, $container->get(ResponseFactoryInterface::class));
            self::assertInstanceOf(StreamFactoryInterface::class, $container->get(StreamFactoryInterface::class));
            self::assertInstanceOf(ErrorHandlerInterface::class, $container->get(ErrorHandlerInterface::class));
            self::assertInstanceOf(
                HttpErrorHandlingMiddleware::class,
                $container->get(HttpErrorHandlingMiddleware::class),
            );
            self::assertSame(
                HttpErrorHandlingMiddleware::class,
                $container->get(TagRegistry::class)->all(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE)[0]->id(),
            );
        }
    }

    #[DataProvider('mappedAndEmergencyFailures')]
    public function testRealSelectedOuterBoundaryNormalizesExactlyOnceAndRendersSafeResponse(
        string $case,
        int $expectedStatus,
    ): void {
        $throwable = match ($case) {
            'bad-json' => BadJsonException::invalid(),
            'body-too-large' => BodyTooLargeException::tooLarge(),
            'emergency' => new \RuntimeException('PRIVATE_ERROR_TOKEN'),
        };
        $handler = $this->createMock(ErrorHandlerInterface::class);
        $normalization = $handler->expects(self::once())->method('handle')->with(self::identicalTo($throwable));
        if ($case === 'emergency') {
            $normalization->willThrowException(new \RuntimeException('PRIVATE_MAPPING_FAILURE'));
        } else {
            $descriptor = new HttpExceptionMapper()->map($throwable);
            self::assertNotNull($descriptor);
            self::assertSame($expectedStatus, $descriptor->httpStatus());
            $normalization->willReturn($descriptor);
        }
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($expectedStatus);
        $headers = [];
        $response->expects(self::exactly(2))->method('withHeader')
            ->willReturnCallback(
                static function (string $name, string $value) use (&$headers, $response): ResponseInterface {
                    $headers[$name] = $value;

                    return $response;
                },
            );
        $response->expects(self::once())->method('withBody')->willReturnSelf();
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with($expectedStatus)->willReturn($response);
        $stream = $this->createStub(StreamInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $bodies = [];
        $streams->expects(self::once())->method('createStream')
            ->willReturnCallback(static function (string $content) use ($stream, &$bodies): StreamInterface {
                $bodies[] = $content;

                return $stream;
            });
        $boundary = new HttpErrorHandlingMiddleware(
            $handler,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $streams,
        );
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willThrowException($throwable);
        $pipeline = $this->pipeline(true, $boundary, $terminal);
        $request = $this->createStub(ServerRequestInterface::class);

        self::assertSame($response, $pipeline->handle($request));
        self::assertSame([
            'Content-Type' => 'application/problem+json',
            'Cache-Control' => 'no-store',
        ], $headers);
        self::assertCount(1, $bodies);
        $public = \json_decode($bodies[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expectedStatus, $public['status']);
        self::assertSame(['status', 'title', 'type'], \array_keys($public));
        self::assertSame('about:blank', $public['type']);
        self::assertStringNotContainsString('PRIVATE_', $bodies[0]);
        self::assertStringNotContainsString($throwable->getMessage(), $bodies[0]);
    }

    /** @return iterable<string, array{string, int}> */
    public static function mappedAndEmergencyFailures(): iterable
    {
        yield 'mapped-400' => ['bad-json', 400];
        yield 'mapped-413' => ['body-too-large', 413];
        yield 'emergency-500' => ['emergency', 500];
    }

    public function testMissingProblemDetailsSelectionPreservesOriginalThrowableWithoutNormalization(): void
    {
        $original = BadJsonException::invalid();
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willThrowException($original);
        $boundary = $this->createMock(MiddlewareInterface::class);
        $boundary->expects(self::never())->method('process');
        $request = $this->createStub(ServerRequestInterface::class);
        try {
            $this->pipeline(false, $boundary, $terminal)->handle($request);
        } catch (\Throwable $caught) {
            self::assertSame($original, $caught);

            return;
        }
        self::fail('Unselected Problem Details must not silently normalize HTTP exceptions.');
    }

    public function testSuccessfulDownstreamResponsePassesThroughSelectedErrorBoundaryUnmodified(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::never())->method('handle');
        $factories = $this->createMock(ResponseFactoryInterface::class);
        $factories->expects(self::never())->method('createResponse');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $response->expects(self::never())->method('withHeader');
        $response->expects(self::never())->method('withBody');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->willReturn($response);
        $boundary = new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $factories,
            $streams,
        );
        $request = $this->createStub(ServerRequestInterface::class);
        self::assertSame($response, $this->pipeline(true, $boundary, $terminal)->handle($request));
    }

    #[DataProvider('constructionFailures')]
    public function testFailedProblemResponseConstructionPreservesOriginalDownstreamThrowable(string $failure): void
    {
        $original = new \RuntimeException('PRIMARY_PRIVATE_ERROR');
        $errors = $this->createMock(ErrorHandlerInterface::class);
        $errors->expects(self::once())->method('handle')->willThrowException(
            new \RuntimeException('normalizer-offline'),
        );
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withHeader')->willReturnSelf();
        $responses = $this->createStub(ResponseFactoryInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $terminal = $this->createStub(RequestHandlerInterface::class);
        $terminal->method('handle')->willThrowException($original);
        if ($failure === 'response') {
            $responses->method('createResponse')->willThrowException(new \RuntimeException('factory-down'));
            $streams->expects(self::never())->method('createStream');
        } else {
            $responses->method('createResponse')->willReturn($response);
            $streams->expects(self::once())->method('createStream')->willThrowException(
                new \RuntimeException('stream-down'),
            );
        }
        $boundary = new HttpErrorHandlingMiddleware(
            $errors,
            new ErrorDescriptorToProblemDetailsAdapter(),
            new ProblemDetailsJsonRenderer(),
            $responses,
            $streams,
        );
        try {
            $this->pipeline(true, $boundary, $terminal)->handle($this->createStub(ServerRequestInterface::class));
        } catch (\Throwable $caught) {
            self::assertSame($original, $caught);

            return;
        }
        self::fail('Transport construction must not replace the original downstream Throwable.');
    }

    /** @return iterable<string, array{string}> */
    public static function constructionFailures(): iterable
    {
        yield 'response-factory' => ['response'];
        yield 'stream-factory' => ['stream'];
    }

    private function pipeline(
        bool $selected,
        MiddlewareInterface $boundary,
        RequestHandlerInterface $terminal,
    ): Pipeline {
        [$set, $http] = self::definitions($selected);
        $tags = new TagRegistry();
        foreach ($set->toDescriptorStream() as $operation) {
            if (($operation['kind'] ?? null) === 'tag') {
                $tags->add($operation['tag'], $operation['serviceId'], $operation['priority'], $operation['meta']);
            }
        }
        $bodyLimit = $this->createMock(MiddlewareInterface::class);
        $bodyLimit->expects(self::once())->method('process')
            ->willReturnCallback(
                static fn (
                    ServerRequestInterface $request,
                    RequestHandlerInterface $next,
                ): ResponseInterface => $next->handle($request),
            );
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): MiddlewareInterface => match ($id) {
                HttpErrorHandlingMiddleware::class => $boundary,
                RequestBodySizeLimitMiddleware::class => $bodyLimit,
                default => throw new \LogicException('unexpected-http-middleware'),
            },
        );

        return HttpServiceFactory::pipeline(
            $container,
            new ArrayConfigRepository(['http' => $http]),
            new HttpMiddlewareStackBuilder($tags),
            $terminal,
        );
    }

    /** @return array{\Coretsia\Foundation\Container\Definition\ContainerDefinitionSet, array<string, mixed>} */
    private static function definitions(bool $selected): array
    {
        $http = require __DIR__ . '/../../config/http.php';
        $http['tracing']['enabled'] = false;
        $http['proxy']['enabled'] = false;
        $http['context']['enrich']['enabled'] = false;
        $http['hosts']['enabled'] = false;
        $http['request']['json']['enabled'] = false;
        $config = [
            'foundation' => require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php',
            'http' => $http,
        ];
        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);
        new FoundationServiceProvider()->define($definitions, $context);
        new HttpServiceProvider()->define($definitions, $context);
        if ($selected) {
            new ProblemDetailsServiceProvider()->define($definitions, $context);
        }
        // Compile-time fixtures satisfy canonical references; runtime behavior
        // below uses injected PSR/Errors spies instead of these descriptors.
        $definitions
            ->classService(KernelRuntimeInterface::class, \stdClass::class)
            ->classService(RuntimeDriverResolver::class, RuntimeDriverResolver::class)
            ->classService(ResponseFactoryInterface::class, HttpProblemParityFactories::class)
            ->classService(StreamFactoryInterface::class, HttpProblemParityFactories::class)
            ->classService(ErrorHandlerInterface::class, HttpProblemParityErrorHandler::class);

        return [$definitions->build(), $http];
    }
}

/** Isolated PSR-17 port fixture: no concrete provider dependency is introduced. */
final class HttpProblemParityFactories implements ResponseFactoryInterface, StreamFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new \LogicException('http-problem-fixture-response-not-executable');
    }

    public function createStream(string $content = ''): StreamInterface
    {
        throw new \LogicException('http-problem-fixture-stream-not-executable');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('http-problem-fixture-file-not-executable');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('http-problem-fixture-resource-not-executable');
    }
}

final class HttpProblemParityErrorHandler implements ErrorHandlerInterface
{
    public function handle(\Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor
    {
        throw new \LogicException('http-problem-fixture-error-handler-not-executable');
    }
}
