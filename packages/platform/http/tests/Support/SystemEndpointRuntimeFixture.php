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

namespace Coretsia\Platform\Http\Tests\Support;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Clock\FrozenClock;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
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
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\Routing\FallbackRouter;
use Coretsia\Platform\Metrics\Provider\MetricsServiceProvider;
use Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Canonical source/compiled HTTP graph with application-local test fixtures.
 *
 * All executable doubles are resolved from declarative class definitions.
 * PHPUnit stubs and failure objects are supplied only after graph construction.
 */
final readonly class SystemEndpointRuntimeFixture
{
    public const int TIMESTAMP = 1700000000;

    public const string CORRELATION_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private function __construct(
        public Container $container,
        private string $root,
        private \Closure $stub,
    ) {
    }

    /** @return iterable<string, array{string}> */
    public static function modes(): iterable
    {
        yield 'source' => ['source'];
        yield 'compiled' => ['compiled'];
    }

    /**
     * @param \Closure(class-string): object $stub
     * @param array<string, mixed> $options
     */
    public static function boot(string $mode, \Closure $stub, array $options = []): self
    {
        $root = \sys_get_temp_dir() . '/coretsia-http-endpoint-' . \bin2hex(\random_bytes(12));
        if (!\mkdir($root, 0700)) {
            throw new \RuntimeException('http-endpoint-fixture-root-failed');
        }

        try {
            $packages = \dirname(__DIR__, 4);
            $http = require __DIR__ . '/../../config/http.php';
            $http['factory'] = 'external';
            $http['maintenance']['enabled'] = false;
            $http['tracing']['enabled'] = false;
            $http['context']['enrich']['enabled'] = false;
            $http['request']['json']['enabled'] = false;
            $http['security_headers']['enabled'] = true;
            $overrides = $options['http'] ?? [];
            $debug = $http['debug'];
            $http = \array_replace_recursive($http, $overrides);

            if (\array_key_exists('debug', $overrides)) {
                $http['debug'] = self::replaceDebugConfiguration($debug, $overrides['debug']);
            }
            if ($options['earlier'] ?? false) {
                $http['middleware']['app_pre'][] = SystemEndpointEarlierMiddleware::class;
            }

            $config = [
                'foundation' => require $packages . '/core/foundation/config/foundation.php',
                'kernel' => require $packages . '/core/kernel/config/kernel.php',
                'http' => $http,
            ];
            $problemDetails = $options['problemDetails'] ?? false;
            $metricsModule = $options['metricsModule'] ?? false;
            $reporter = $options['reporter'] ?? false;
            $providers = [
                ['core.foundation', new FoundationServiceProvider(), []],
                ['core.kernel', new KernelServiceProvider(), ['core.foundation']],
                ['platform.redaction', new RedactionServiceProvider(), ['core.foundation']],
                [
                    'platform.http',
                    new HttpServiceProvider(),
                    ['core.foundation', 'core.kernel', 'platform.redaction'],
                ],
            ];
            if ($metricsModule) {
                $config['metrics'] = require $packages . '/platform/metrics/config/metrics.php';
                $providers[] = ['platform.metrics', new MetricsServiceProvider(), ['core.foundation']];
            }
            if ($problemDetails || $reporter) {
                $config['errors'] = require $packages . '/platform/errors/config/errors.php';
                $config['errors']['reporting']['enabled'] = $reporter;
                $providers[] = [
                    'platform.errors',
                    new ErrorsServiceProvider(),
                    ['core.foundation', 'platform.redaction'],
                ];
            }
            if ($problemDetails) {
                $config['problem_details'] = require $packages . '/platform/problem-details/config/problem_details.php';
                $providers[] = [
                    'platform.problem-details',
                    new ProblemDetailsServiceProvider(),
                    ['core.foundation', 'platform.errors'],
                ];
            }

            $definitions = new ContainerDefinitionBuilder();
            $context = new ContainerDefinitionContext($config);
            $ids = [];
            $entries = [];
            foreach ($providers as [$id, $provider, $requires]) {
                $provider->define($definitions, $context);
                $moduleId = ModuleId::fromString($id);
                $ids[] = $moduleId;
                $entries[] = new ModulePlanEntry(
                    $moduleId,
                    'coretsia/' . \str_replace('.', '-', $id),
                    \array_map(ModuleId::fromString(...), $requires),
                );
            }
            $plan = new ModulePlan('web', $ids, [], $ids, $entries);

            $definitions
                ->classService(SystemEndpointPsrFactories::class, SystemEndpointPsrFactories::class)
                ->alias(ResponseFactoryInterface::class, SystemEndpointPsrFactories::class)
                ->alias(StreamFactoryInterface::class, SystemEndpointPsrFactories::class)
                ->classService(
                    SystemEndpointKernelRuntime::class,
                    SystemEndpointKernelRuntime::class,
                    [ContainerValueReference::service(ContextStore::class)],
                )
                ->alias(KernelRuntimeInterface::class, SystemEndpointKernelRuntime::class)
                ->classService(
                    LoggerInterface::class,
                    SystemEndpointRecordingLogger::class,
                    [$options['throwLogger'] ?? false],
                );

            if ($options['recordMeter'] ?? !$metricsModule) {
                $definitions->classService(
                    MeterPortInterface::class,
                    SystemEndpointRecordingMeter::class,
                    [$options['throwMeter'] ?? false],
                );
            }
            if ($reporter) {
                $definitions->classService(ErrorReporterPortInterface::class, SystemEndpointRecordingReporter::class);
            }
            if ($options['earlier'] ?? false) {
                $definitions->classService(
                    SystemEndpointEarlierMiddleware::class,
                    SystemEndpointEarlierMiddleware::class,
                    [
                        ContainerValueReference::service(ResponseFactoryInterface::class),
                        ContainerValueReference::service(StreamFactoryInterface::class),
                    ],
                );
            }

            $clock = match ($options['clock'] ?? 'frozen') {
                'frozen' => FrozenClock::class,
                'recording' => SystemEndpointRecordingClock::class,
                'sequential' => SystemEndpointSequentialClock::class,
                'throwing' => SystemEndpointThrowingClock::class,
                default => throw new \InvalidArgumentException('http-endpoint-fixture-clock-invalid'),
            };
            $timestamp = $options['timestamp'] ?? self::TIMESTAMP;
            if ($clock === FrozenClock::class) {
                $definitions->classMethodFactory($clock, SystemEndpointClockFactory::class, 'frozen', [$timestamp]);
            } else {
                $arguments = match ($clock) {
                    SystemEndpointRecordingClock::class => [$timestamp],
                    SystemEndpointSequentialClock::class => [
                        $options['timestamps'] ?? [
                            self::TIMESTAMP,
                            self::TIMESTAMP + 1,
                        ],
                    ],
                    default => [],
                };
                $definitions->classService($clock, $clock, $arguments);
            }
            $definitions->alias(ClockInterface::class, $clock);

            $set = $definitions->build();
            $paths = new RuntimePathContext($root, $root . '/var/cache');
            $repository = new ArrayConfigRepository($config, $options['sources'] ?? []);

            // Compiled graph reconstruction alone does not load an artifact generation.
            // A loaded-generation state is an explicitly supplied synthetic fixture input.
            $diagnostics = new RuntimeDiagnosticsInput(
                $options['appEnv'] ?? 'test',
                $options['artifactGenerationLoaded'] ?? false,
            );

            if ($mode === 'source' && $diagnostics->artifactGenerationLoaded()) {
                throw new \InvalidArgumentException('http-endpoint-fixture-generation-state-invalid');
            }
            if ($mode === 'source') {
                $builder = new ContainerBuilder($config);
                $builder->instance(ConfigRepositoryInterface::class, $repository);
                $builder->instance(TagRegistry::class, $builder->tagRegistry());
                $builder->instance(ModulePlan::class, $plan);
                $builder->instance(RuntimeDiagnosticsInput::class, $diagnostics);
                $builder->instance(RuntimePathContext::class, $paths);
                $container = $builder->applyDefinitions($set)->build();
            } elseif ($mode === 'compiled') {
                $compiler = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch());
                $graph = $compiler->compile($set);
                new ContainerGraphCompletenessValidator()->validate($graph, $set);
                $envelope = new ArtifactEnvelopeFactory()->container(
                    \str_repeat('a', 64),
                    ['kind' => 'compiled', 'compiled' => true, ...$graph->toArray()],
                );
                $container = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope(
                    $envelope,
                    new RuntimeContainerSeedSet([
                        ConfigRepositoryInterface::class => $repository,
                        ModulePlan::class => $plan,
                        RuntimeDiagnosticsInput::class => $diagnostics,
                        RuntimePathContext::class => $paths,
                    ]),
                );
            } else {
                throw new \InvalidArgumentException('http-endpoint-fixture-mode-invalid');
            }

            $fixture = new self($container, $root, $stub);
            $fixture->factories()->stub = $stub;

            return $fixture;
        } catch (\Throwable $throwable) {
            self::removeTree($root);
            throw $throwable;
        }
    }

    public function kernel(): HttpKernel
    {
        return $this->container->get(HttpKernel::class);
    }

    public function router(): FallbackRouter
    {
        return $this->container->get(FallbackRouter::class);
    }

    public function clock(): ClockInterface
    {
        return $this->container->get(ClockInterface::class);
    }

    public function factories(): SystemEndpointPsrFactories
    {
        return $this->container->get(SystemEndpointPsrFactories::class);
    }

    public function runtime(): SystemEndpointKernelRuntime
    {
        return $this->container->get(KernelRuntimeInterface::class);
    }

    public function meter(): MeterPortInterface
    {
        return $this->container->get(MeterPortInterface::class);
    }

    public function recordingMeter(): SystemEndpointRecordingMeter
    {
        return $this->container->get(MeterPortInterface::class);
    }

    public function logger(): SystemEndpointRecordingLogger
    {
        return $this->container->get(LoggerInterface::class);
    }

    public function reporter(): SystemEndpointRecordingReporter
    {
        return $this->container->get(ErrorReporterPortInterface::class);
    }

    public function maintenance(): MaintenanceStateStore
    {
        return $this->container->get(MaintenanceStateStore::class);
    }

    public function makeMaintenanceUnavailable(): void
    {
        if (
            !\mkdir($this->root . '/var', 0700)
            || \file_put_contents($this->root . '/var/maintenance', 'blocked') === false
        ) {
            throw new \RuntimeException('http-endpoint-fixture-maintenance-failed');
        }
    }

    /**
     * @param array<string, list<string>> $headers
     * @param array<string, mixed> $serverParams
     * @param array<string, mixed> $attributes
     */
    public function request(
        string $method,
        string $path,
        string $query = '',
        array $headers = [],
        ?\Throwable $uriFailure = null,
        ?\Throwable $pathFailure = null,
        array $serverParams = [],
        array $attributes = [],
    ): ServerRequestInterface {
        $uri = ($this->stub)(UriInterface::class);
        $uri->method('getPath')->willReturnCallback(static function () use ($path, $pathFailure): string {
            if ($pathFailure !== null) {
                throw $pathFailure;
            }

            return $path;
        });
        $uri->method('getQuery')->willReturn($query);
        $uri->method('getScheme')->willReturn('http');
        $uri->method('getHost')->willReturn('example.test');
        $uri->method('getAuthority')->willReturn('example.test');

        $request = ($this->stub)(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getServerParams')->willReturn($serverParams);
        $request->method('getAttributes')->willReturn($attributes);
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => \array_key_exists($name, $attributes)
                ? $attributes[$name]
                : $default,
        );
        $request->method('withAttribute')->willReturnCallback(
            fn (string $name, mixed $value): ServerRequestInterface => $this->request(
                $method,
                $path,
                $query,
                $headers,
                $uriFailure,
                $pathFailure,
                $serverParams,
                \array_replace($attributes, [$name => $value]),
            ),
        );
        $request->method('withoutAttribute')->willReturnCallback(
            fn (string $name): ServerRequestInterface => $this->request(
                $method,
                $path,
                $query,
                $headers,
                $uriFailure,
                $pathFailure,
                $serverParams,
                \array_diff_key($attributes, [$name => true]),
            ),
        );
        $request->method('getUri')->willReturnCallback(static function () use ($uri, $uriFailure): UriInterface {
            if ($uriFailure !== null) {
                throw $uriFailure;
            }

            return $uri;
        });
        $request->method('getBody')->willReturn($this->factories()->stream(''));
        SystemEndpointPsrFactories::configureHeaders($request, $headers);

        return $request;
    }

    public function close(): void
    {
        self::removeTree($this->root);
    }

    /**
     * @param array<string, mixed> $defaults
     */
    private static function replaceDebugConfiguration(array $defaults, mixed $overrides): mixed
    {
        if (!\is_array($overrides) || \array_is_list($overrides)) {
            return $overrides;
        }

        foreach ($overrides as $key => $value) {
            $current = $defaults[$key] ?? null;

            $defaults[$key] = \is_array($current) && !\array_is_list($current)
                ? self::replaceDebugConfiguration($current, $value)
                : $value;
        }

        return $defaults;
    }

    private static function removeTree(string $path): void
    {
        if (\is_dir($path) && !\is_link($path)) {
            foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) {
                self::removeTree($entry->getPathname());
            }
            \rmdir($path);
        } elseif (\file_exists($path) || \is_link($path)) {
            \unlink($path);
        }
    }
}

/** Immutable PSR-7 response snapshots built by the selected test-owned PSR-17 factories. */
final class SystemEndpointPsrFactories implements ResponseFactoryInterface, StreamFactoryInterface
{
    public \Closure $stub;

    /** @var list<int> */
    public array $statuses = [];

    /** @var list<string> */
    public array $bodies = [];

    /** @var list<array<mixed>> */
    public array $mutations = [];

    public ?\Throwable $responseFailure = null;

    public ?\Throwable $streamFailure = null;

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        $this->statuses[] = $code;
        if ($this->responseFailure !== null) {
            $failure = $this->responseFailure;
            $this->responseFailure = null;
            throw $failure;
        }

        return $this->response($code, [], $this->stream(''));
    }

    public function createStream(string $content = ''): StreamInterface
    {
        $this->bodies[] = $content;
        if ($this->streamFailure !== null) {
            $failure = $this->streamFailure;
            $this->streamFailure = null;
            throw $failure;
        }

        return $this->stream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('http-endpoint-fixture-file-unsupported');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('http-endpoint-fixture-resource-unsupported');
    }

    public function stream(string $content): StreamInterface
    {
        $stream = ($this->stub)(StreamInterface::class);
        $stream->method('__toString')->willReturn($content);
        $stream->method('getContents')->willReturn($content);
        $stream->method('getSize')->willReturn(\strlen($content));
        $stream->method('isSeekable')->willReturn(true);
        $stream->method('tell')->willReturn(0);
        $stream->method('eof')->willReturn($content === '');

        return $stream;
    }

    /** @param array<string, list<string>> $headers */
    private function response(int $status, array $headers, StreamInterface $stream): ResponseInterface
    {
        $response = ($this->stub)(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($stream);
        self::configureHeaders($response, $headers);
        $response->method('withHeader')->willReturnCallback(
            function (string $name, string|array $value) use ($status, $headers, $stream): ResponseInterface {
                $values = (array) $value;
                $this->mutations[] = ['header', $name, $values];
                foreach (\array_keys($headers) as $existing) {
                    if (\strcasecmp($name, $existing) === 0) {
                        unset($headers[$existing]);
                    }
                }
                $headers[$name] = $values;

                return $this->response($status, $headers, $stream);
            },
        );
        $response->method('withBody')->willReturnCallback(
            function (StreamInterface $body) use ($status, $headers): ResponseInterface {
                $this->mutations[] = ['body', (string) $body];

                return $this->response($status, $headers, $body);
            },
        );

        return $response;
    }

    /** @param array<string, list<string>> $headers */
    public static function configureHeaders(object $message, array $headers): void
    {
        $lookup = \array_change_key_case($headers, CASE_LOWER);
        $message->method('getHeaders')->willReturn($headers);
        $message->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $lookup[\strtolower($name)] ?? [],
        );
        $message->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => \implode(', ', $lookup[\strtolower($name)] ?? []),
        );
        $message->method('hasHeader')->willReturnCallback(
            static fn (string $name): bool => isset($lookup[\strtolower($name)]),
        );
    }
}

final class SystemEndpointClockFactory
{
    public static function frozen(int $timestamp): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable('@' . $timestamp));
    }
}

final class SystemEndpointRecordingClock implements ClockInterface
{
    public int $calls = 0;

    public function __construct(private readonly int $timestamp)
    {
    }

    public function now(): \DateTimeImmutable
    {
        ++$this->calls;

        return new \DateTimeImmutable('@' . $this->timestamp);
    }
}

final class SystemEndpointSequentialClock implements ClockInterface
{
    public int $calls = 0;

    /** @param list<int> $timestamps */
    public function __construct(private readonly array $timestamps)
    {
    }

    public function now(): \DateTimeImmutable
    {
        $timestamp = $this->timestamps[$this->calls++] ?? throw new \LogicException('http-endpoint-clock-exhausted');

        return new \DateTimeImmutable('@' . $timestamp);
    }
}

final class SystemEndpointThrowingClock implements ClockInterface
{
    public int $calls = 0;

    public ?\Throwable $failure = null;

    public function now(): \DateTimeImmutable
    {
        ++$this->calls;

        throw $this->failure ?? new \LogicException('http-endpoint-clock-failure-not-set');
    }
}

final class SystemEndpointRecordingMeter implements MeterPortInterface
{
    /** @var list<array{string, int, array<string, mixed>}> */
    public array $increments = [];

    /** @var list<array{string, int, array<string, mixed>}> */
    public array $observations = [];

    public function __construct(private readonly bool $throws)
    {
    }

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->increments[] = [$name, $delta, $labels];
        if ($this->throws) {
            throw new \RuntimeException('http-endpoint-meter-failure');
        }
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->observations[] = [$name, $value, $labels];
        if ($this->throws) {
            throw new \RuntimeException('http-endpoint-meter-failure');
        }
    }

    /** @return list<array{string, int, array<string, mixed>}> */
    public function incrementsFor(string $name): array
    {
        return \array_values(\array_filter($this->increments, static fn (array $entry): bool => $entry[0] === $name));
    }
}

final class SystemEndpointRecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<string, mixed>}> */
    public array $records = [];

    public function __construct(private readonly bool $throws)
    {
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
        if ($this->throws) {
            throw new \RuntimeException('http-endpoint-logger-failure');
        }
    }

    /** @return list<array{mixed, string, array<string, mixed>}> */
    public function recordsFor(string $message): array
    {
        return \array_values(\array_filter($this->records, static fn (array $entry): bool => $entry[1] === $message));
    }
}

final class SystemEndpointRecordingReporter implements ErrorReporterPortInterface
{
    /** @var list<array{ErrorDescriptor, ?ErrorHandlingContext}> */
    public array $reports = [];

    public function report(ErrorDescriptor $descriptor, ?ErrorHandlingContext $context = null): void
    {
        $this->reports[] = [$descriptor, $context];
    }
}

/** Observe the canonical HTTP-to-Kernel UnitOfWork boundary, without running unrelated hooks. */
final class SystemEndpointKernelRuntime implements KernelRuntimeInterface
{
    /** @var list<UnitOfWorkHandle> */
    public array $begins = [];

    /** @var list<array{UnitOfWorkHandle, string, ?\Throwable}> */
    public array $completions = [];

    public function __construct(private readonly ContextStore $context)
    {
    }

    public function runUnitOfWork(string $type, callable $body, array $attributes = []): mixed
    {
        throw new \LogicException('http-endpoint-low-level-uow-required');
    }

    public function beginUnitOfWork(string $type, array $attributes = []): UnitOfWorkHandle
    {
        $id = SystemEndpointRuntimeFixture::CORRELATION_ID;
        $this->context->set(ContextKeys::CORRELATION_ID, $id);
        $this->context->set(ContextKeys::UOW_ID, $id);
        $this->context->set(ContextKeys::UOW_TYPE, $type);
        $handle = new UnitOfWorkHandle([
            'attributes' => $attributes,
            'correlationId' => $id,
            'type' => $type,
            'uowId' => $id,
        ]);
        $this->begins[] = $handle;

        return $handle;
    }

    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        $this->completions[] = [$handle, $outcome, $error];
        $this->context->reset();

        return [];
    }
}

final class SystemEndpointEarlierMiddleware implements MiddlewareInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ++$this->calls;

        return $this->responses->createResponse(202)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->streams->createStream('earlier response'));
    }
}
