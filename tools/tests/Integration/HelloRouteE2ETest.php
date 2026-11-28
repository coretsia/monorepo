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

namespace Coretsia\Tools\Tests\Integration;

use Composer\InstalledVersions;
use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Observability\Tracing\NoopSpan;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Module\Exception\ModuleResolutionException;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsExecutionServices;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\HttpKernelInterface;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use Coretsia\Platform\Http\Middleware\Pipeline;
use Coretsia\Platform\Http\Provider\HttpServiceFactory;
use Coretsia\Platform\Http\Routing\FallbackRouterHandler;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointPsrFactories;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRecordingLogger;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRecordingMeter;
use Coretsia\Platform\HttpApp\Middleware\ActionDispatchMiddleware;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

// The transport doubles are existing test-only classes in a shared fixture file.
require_once \dirname(__DIR__, 3) . '/packages/platform/http/tests/Support/SystemEndpointRuntimeFixture.php';

final class HelloRouteE2ETest extends TestCase
{
    private const array MODULES = [
        'core.foundation',
        'core.kernel',
        'platform.redaction',
        'platform.errors',
        'platform.problem-details',
        'platform.http',
        'platform.routing',
        'platform.http-app',
        'platform.hello-middleware',
        'platform.hello-consumer',
    ];

    public function testIsolatedConsumerCompilesAndDispatchesThroughBothRuntimeGraphs(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    /** Runs in the isolated Composer consumer process, not the PHPUnit runner. */
    public static function exercise(string $root): void
    {
        self::installConsumer($root);
        $test = new self('testIsolatedConsumerCompilesAndDispatchesThroughBothRuntimeGraphs');
        $source = RoutingIntegrationFixture::source($root, AppTarget::Web);
        self::assertInstanceOf(Container::class, $source);
        $config = $source->get(ConfigRepositoryInterface::class);
        $plan = $source->get(ModulePlan::class);
        $paths = $source->get(RuntimePathContext::class);
        $artifactRoot = $root . '/var/hello-cache/web';
        self::assertSame($artifactRoot, $paths->artifactRoot());
        self::assertSame('web', $plan->app());
        foreach (self::MODULES as $id) {
            self::assertTrue($plan->hasEnabledModule($id));
        }
        self::assertFalse($plan->hasEnabledModule('platform.routing-fixture-web'));
        self::assertFalse($source->get(RuntimeDiagnosticsInput::class)->artifactGenerationLoaded());
        $routeEnvelope = $source->get(RoutesCompiler::class)->compile();
        self::assertSame('web', $routeEnvelope['payload']['app']);
        self::assertSame(1, $routeEnvelope['_meta']['schemaVersion']);
        self::assertTrue($source->get(RoutesArtifactWriter::class)->write($routeEnvelope));
        $ops = $source->get(KernelOpsInterface::class);
        self::assertInstanceOf(KernelOpsInterface::class, $ops);
        $result = $ops->compileConfig(new KernelOpsRequest('web'));
        self::assertSame('success', $result->outcome(), (string) $result->reason());
        self::assertSame('web', $result->appTarget());
        $generation = RoutingIntegrationFixture::generationBytes($artifactRoot);
        $parser = new StablePhpArrayParser();
        $configEnvelope = $parser->parse($generation['config.php']);
        $containerEnvelope = $parser->parse($generation['container.php']);
        $manifestEnvelope = $parser->parse($generation['module-manifest.php']);
        self::assertSame($configEnvelope['_meta']['fingerprint'], $containerEnvelope['_meta']['fingerprint']);
        self::assertSame($configEnvelope['_meta']['fingerprint'], $manifestEnvelope['_meta']['fingerprint']);
        self::assertSame('web', $manifestEnvelope['payload']['app']);
        self::assertSame('local', $configEnvelope['payload']['appEnv']);
        $graph = $containerEnvelope['payload'];
        self::assertSame(HelloRouteAction::class, $graph['services']['hello.action']['construction']['class']);
        self::assertFalse($graph['services']['hello.action']['shared']);
        self::assertSame(CompiledRouter::class, $graph['aliases'][RouterInterface::class]);
        $execution = $source->get(KernelOpsExecutionServices::class);
        $resolution = $execution->moduleResolutionOrchestrator()->resolve($source->get(BootstrapConfig::class));
        $definitionGraph = $execution->runtimeContainerGraphCompiler()->compile($resolution, $config->all())->toArray();
        foreach (['services', 'aliases', 'parameters', 'tags'] as $key) {
            self::assertSame($definitionGraph[$key], $graph[$key]);
        }
        $compiled = self::compiled($root, $artifactRoot);
        self::assertTrue($compiled->get(RuntimeDiagnosticsInput::class)->artifactGenerationLoaded());
        self::assertSame($artifactRoot, $compiled->get(RuntimePathContext::class)->artifactRoot());
        self::assertSame($plan->toArray(), $compiled->get(ModulePlan::class)->toArray());
        $hydrated = $compiled->get(ConfigRepositoryInterface::class);
        self::assertSame($config->all(), $hydrated->all());
        self::assertSame($configEnvelope['payload']['config'], $hydrated->all());
        self::assertSame(
            \array_map(static fn (ConfigValueSource $item): array => $item->toArray(), $config->explain()),
            \array_map(static fn (ConfigValueSource $item): array => $item->toArray(), $hydrated->explain()),
        );
        self::assertSame(self::appConfig()['handlers'], $hydrated->get('http_app.handlers'));
        self::assertSame([HelloRouteArgument::class], $hydrated->get('http_app.arguments.allow_container_types'));
        self::assertSame(self::appConfig()['route_middleware'], $hydrated->get('http_app.route_middleware'));
        foreach (
            [
                'http_app.handlers[0].action_id',
                'http_app.handlers[0].service_id',
                'http_app.arguments.allow_container_types[0]',
                'http_app.route_middleware[0]',
            ] as $key
        ) {
            $sources = \array_values(
                \array_filter(
                    $hydrated->explain(),
                    static fn (ConfigValueSource $item): bool => $item->keyPath() === $key,
                ),
            );
            self::assertCount(1, $sources);
            $entry = $sources[0]->toArray();
            self::assertSame($key, $entry['keyPath']);
            self::assertSame('apps/web/config/http_app.php', $entry['path']);
            self::assertSame('web', $entry['meta']['appTarget']);
            self::assertFalse($entry['redacted']);
        }
        foreach ([$source, $compiled] as $host) {
            self::assertInstanceOf(Container::class, $host);
            foreach (\array_keys($graph['services'] + $graph['aliases']) as $id) {
                self::assertContains($id, $host->serviceIds());
            }
            self::assertSame($graph['tags'], self::tags($host->get(TagRegistry::class)));
            self::assertSame(
                $routeEnvelope['payload']['routes'],
                \array_map(
                    static fn (RouteDefinition $route): array => $route->toArray(),
                    $host->get(RoutesArtifactReader::class)->read(),
                ),
            );
        }
        self::assertPreflightFailures($source);
        self::assertPreflightFailures($compiled);
        self::assertSame($test->dispatch($source), $test->dispatch($compiled));
        self::assertSame($generation, RoutingIntegrationFixture::generationBytes($artifactRoot));
        self::assertSame($routeEnvelope, $parser->parse((string) \file_get_contents($artifactRoot . '/routes.php')));
        self::assertGraphFailures($source);
        self::assertReadinessFailures($root, $artifactRoot);
        self::assertMissingModuleFailures($root, $source);
    }

    /** @return array<string, mixed> */
    private static function appConfig(): array
    {
        return [
            'handlers' => [['action_id' => 'hello.show:detail', 'service_id' => 'hello.action']],
            'arguments' => ['allow_container_types' => [HelloRouteArgument::class]],
            'route_middleware' => ['hello.z', 'hello.a', 'hello.deny', 'hello.mutate', 'hello.restore', 'hello.repeat'],
        ];
    }

    private static function installConsumer(string $root): void
    {
        $composer = \dirname(new \ReflectionClass(InstalledVersions::class)->getFileName());
        self::assertSame($root . '/vendor/composer', \str_replace('\\', '/', $composer));
        $metadata = \json_decode(
            (string) \file_get_contents($composer . '/installed.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $installed = require $composer . '/installed.php';
        foreach (
            [
                'middleware' => [HelloRouteMiddlewareDefinitions::class, ['core.foundation']],
                'consumer' => [
                    HelloRouteDefinitions::class,
                    ['core.foundation', 'platform.routing', 'platform.hello-middleware'],
                ],
            ] as $name => [$provider, $requires]
        ) {
            $package = 'coretsia/test-hello-' . $name;
            $path = $root . '/vendor/' . $package;
            $record = [
                'name' => $package,
                'type' => 'library',
                'version' => '0.0.0',
                'require' => [
                    'php' => '^8.4',
                    'coretsia/core-foundation' => '^0.14.0',
                    'psr/http-server-middleware' => '^1.0',
                ],
                'extra' => [
                    'coretsia' => [
                        'kind' => 'runtime',
                        'moduleId' => 'platform.hello-' . $name,
                        'providers' => [$provider],
                        'requires' => $requires,
                        'conflicts' => [],
                    ],
                ],
            ];
            if ($name === 'consumer') {
                $record['require']['coretsia/platform-routing'] = '^0.14.0';
                $record['require']['coretsia/test-hello-middleware'] = '0.0.0';
            }
            if ($name === 'middleware') {
                self::assertNotContains('platform.http', $requires);
                self::assertFalse(isset($record['require']['coretsia/platform-http']));
            }
            $metadata['packages'][] = $record + ['dev_requirement' => false];
            $installed['versions'][$package] = [
                'pretty_version' => '0.0.0',
                'version' => '0.0.0.0',
                'reference' => null,
                'type' => 'library',
                'install_path' => $path,
                'aliases' => [],
                'dev_requirement' => false,
            ];
            RoutingIntegrationFixture::write($path . '/composer.json', \json_encode($record, \JSON_THROW_ON_ERROR));
        }
        RoutingIntegrationFixture::php($composer . '/installed.php', $installed);
        InstalledVersions::reload($installed);
        RoutingIntegrationFixture::write($composer . '/installed.json', \json_encode($metadata, \JSON_THROW_ON_ERROR));
        RoutingIntegrationFixture::php($root . '/config/app.php', [
            'appEnv' => 'local',
            'preset' => 'hello-fixture',
            'artifactsCacheDir' => 'var/hello-cache',
        ]);
        RoutingIntegrationFixture::php($root . '/config/modes/hello-fixture.php', [
            'schemaVersion' => 1,
            'name' => 'hello-fixture',
            'description' => 'Isolated hello route consumer.',
            'required' => self::MODULES,
            'modules' => [],
            'featureBundles' => [],
            'metadata' => [],
        ]);
        RoutingIntegrationFixture::php($root . '/apps/web/config/http_app.php', self::appConfig());
        RoutingIntegrationFixture::php($root . '/apps/web/config/http.php', [
            'factory' => 'external',
            'maintenance' => ['enabled' => false],
            'tracing' => ['enabled' => false],
            'context' => ['enrich' => ['enabled' => false]],
            'request' => ['json' => ['enabled' => false]],
            'security_headers' => ['enabled' => true],
            'middleware' => ['app' => [HelloRouteManualApp::class], 'route' => [HelloRouteManualRoute::class]],
        ]);
    }

    private static function compiled(string $root, string $artifactRoot): ContainerInterface
    {
        return new ArtifactRuntimeBooter()->boot(new ArtifactRuntimeInput($root, $artifactRoot));
    }

    private static function ready(ContainerInterface $host): void
    {
        $host->get(HttpRuntimeEntrypointGuard::class)->assertEntrypointAllowed(
            $host->get(ConfigRepositoryInterface::class),
            $host->get(ModulePlan::class),
            RuntimeDriverContributions::fromDrivers([], []),
            $host,
        );
    }

    /** @return array<string, mixed> */
    private static function tags(TagRegistry $registry): array
    {
        $tags = [];
        foreach ($registry->tagNames() as $tag) {
            $tags[$tag] = \array_map(static fn ($item): array => [
                'id' => $item->id(),
                'meta' => $item->meta(),
                'priority' => $item->priority(),
            ], $registry->all($tag));
        }

        return $tags;
    }

    /** @return list<array<mixed>> */
    private function dispatch(ContainerInterface $host): array
    {
        self::ready($host);
        self::assertInstanceOf(CompiledRouter::class, $host->get(RouterInterface::class));
        self::assertInstanceOf(FallbackRouterHandler::class, $host->get(RequestHandlerInterface::class));
        $pipeline = $host->get(Pipeline::class);
        self::assertInstanceOf(Pipeline::class, $pipeline);
        self::assertTrue(new \ReflectionProperty(Pipeline::class, 'guardedContinuation')->getValue($pipeline));
        self::assertSame(
            [RouteDispatchState::ATTRIBUTE],
            new \ReflectionProperty(Pipeline::class, 'protectedRequestAttributes')->getValue($pipeline),
        );
        self::assertTrue(new \ReflectionProperty(Pipeline::class, 'protectRoutingInputs')->getValue($pipeline));
        $stack = new \ReflectionProperty(Pipeline::class, 'middleware')->getValue($pipeline);
        self::assertSame($host->get(HttpErrorHandlingMiddleware::class), $stack[0]);
        self::assertSame($host->get(ActionDispatchMiddleware::class), $stack[\array_key_last($stack)]);
        $kernel = $host->get(HttpKernelInterface::class);
        $state = $host->get(HelloRouteEvidence::class);
        $factories = $host->get(SystemEndpointPsrFactories::class);
        $factories->stub = fn (string $class): object => $this->createStub($class);
        $meter = $host->get(MeterPortInterface::class);
        $logger = $host->get(LoggerInterface::class);
        self::assertInstanceOf(SystemEndpointRecordingLogger::class, $logger);
        $tracer = $host->get(TracerPortInterface::class);
        self::assertInstanceOf(HelloRouteTracer::class, $tracer);
        $context = $host->get(ContextStore::class);
        $serviceIds = $host->serviceIds();
        $tagSnapshot = self::tags($host->get(TagRegistry::class));
        $previousContinuations = [];
        $continuationClass = null;
        self::assertInstanceOf(SystemEndpointRecordingMeter::class, $meter);
        self::assertSame([], $state->events);
        self::assertSame(
            [ContextStore::class, HelloRouteEvidence::class],
            \array_column($tagSnapshot[ReservedTags::KERNEL_RESET], 'id'),
        );
        self::assertSame(
            [ContextStore::class],
            \array_column($tagSnapshot[ReservedTags::KERNEL_STATEFUL], 'id'),
        );
        foreach ([ReservedTags::KERNEL_HOOK_BEFORE_UOW, ReservedTags::KERNEL_HOOK_AFTER_UOW] as $tag) {
            self::assertSame(
                [HelloRouteEvidence::class],
                \array_column($tagSnapshot[$tag], 'id'),
            );
        }
        $snapshot = [];
        $identities = [];
        $global = ['high', 'low', 'manual-app', 'route-pre', 'route', 'manual-route'];
        foreach (
            [
                ['GET', '/hello/a%2Fb', 200, ['z', 'a'], 1, null, 'normal'],
                ['GET', '/alternate/second', 200, ['a', 'z'], 1, null, 'normal'],
                ['GET', '/protected/denied', 403, ['z', 'deny'], 0, null, 'normal'],
                ['GET', '/hello/short', 403, [], 0, null, 'short'],
                ['GET', '/plain/third', 200, [], 1, null, 'normal'],
                ['GET', '/missing', 404, [], 0, null, 'normal'],
                ['PUT', '/hello/other', 405, [], 0, null, 'normal'],
                ['GET', '/_alive', 200, [], 0, null, 'normal'],
                ['GET', '/health', 501, [], 0, null, 'normal'],
                ['GET', '/invalid/fourth', 500, [], 0, 'http-app-route-middleware-invalid', 'normal'],
                ['GET', '/hello/state', 500, [], 0, 'http-pipeline-protected-attribute-changed', 'state'],
                ['GET', '/hello/path', 500, [], 0, 'http-pipeline-protected-routing-input-changed', 'path'],
                ['GET', '/hello/method', 500, [], 0, 'http-pipeline-protected-routing-input-changed', 'method'],
                ['GET', '/hello/host', 500, [], 0, 'http-pipeline-protected-routing-input-changed', 'host'],
                [
                    'GET',
                    '/mutation/fifth',
                    500,
                    ['mutate'],
                    0,
                    'http-pipeline-protected-routing-input-changed',
                    'normal',
                ],
                [
                    'GET',
                    '/mutation/fifth',
                    500,
                    ['mutate'],
                    0,
                    'http-pipeline-protected-routing-input-changed',
                    'local-method',
                ],
                [
                    'GET',
                    '/mutation/fifth',
                    500,
                    ['mutate'],
                    0,
                    'http-pipeline-protected-routing-input-changed',
                    'local-host',
                ],
                ['GET', '/hello/repeat', 500, ['z', 'a'], 1, 'http-pipeline-continuation-reused', 'repeat'],
                ['GET', '/repeated/sixth', 500, ['repeat'], 1, 'http-pipeline-continuation-reused', 'normal'],
                ['GET', '/plain/last', 200, [], 1, null, 'normal'],
            ] as [$method, $path, $status, $local, $actions, $reason, $mode]
        ) {
            $state->events = [];
            $state->failures = [];
            $state->caught = null;
            $state->mode = $mode;
            $state->retained = [];
            $count = $state->constructed;
            $invoked = $state->invoked;
            $spanCount = $tracer->starts;
            $ends = $tracer->ends;
            $logCount = \count($logger->recordsFor('http.action.completed'));
            $counter = \count($meter->incrementsFor('http.action_total'));
            $durations = self::actionDurations($meter);
            $request = $this->request($factories, $method, $path);
            foreach ($previousContinuations as $continuation) {
                self::assertSame(
                    'http-pipeline-continuation-expired',
                    self::failure(static fn () => $continuation->handle($request))->getMessage(),
                );
            }
            $response = $kernel->handle($request);
            self::assertSame($status, $response->getStatusCode(), $method . ' ' . $path);
            $correlation = $response->getHeaderLine('X-Correlation-Id');
            self::assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $correlation);
            self::assertNotContains($correlation, $identities);
            $identities[] = $correlation;
            self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
            self::assertSame($count + $actions, $state->constructed);
            self::assertSame($invoked + $actions, $state->invoked);
            self::assertSame($spanCount + $actions, $tracer->starts);
            self::assertSame($ends + $actions, $tracer->ends);
            self::assertSame($logCount + $actions, \count($logger->recordsFor('http.action.completed')));
            if ($actions === 1) {
                $labels = ['method' => $method, 'status' => 200, 'outcome' => 'returned'];
                self::assertSame(
                    ['http.action_total', 1, $labels],
                    $meter->incrementsFor('http.action_total')[$counter],
                );
                self::assertSame(
                    ['debug', 'http.action.completed', $labels],
                    $logger->recordsFor('http.action.completed')[$logCount],
                );
            }
            self::assertSame($counter + $actions, \count($meter->incrementsFor('http.action_total')));
            self::assertSame($durations + $actions, self::actionDurations($meter));
            self::assertSame('before', $state->events[0]);
            self::assertSame(['after', 'reset'], \array_slice($state->events, -2));
            self::assertSame(1, \count(\array_keys($state->events, 'before', true)));
            self::assertSame(1, \count(\array_keys($state->events, 'after', true)));
            self::assertSame(1, \count(\array_keys($state->events, 'reset', true)));
            self::assertFalse($state->active);
            self::assertSame([], $context->all());
            self::assertSame($state->correlation, $correlation);
            $expected = match (true) {
                $status === 404 || $status === 405 => ['high'],
                \in_array($mode, ['short', 'state', 'path', 'method', 'host'], true) => ['high', 'low'],
                default => [...$global, ...$local],
            };
            $entered = \array_values(
                \array_filter($state->events, static fn (string $event): bool => \str_starts_with($event, 'enter:')),
            );
            self::assertSame(\array_map(static fn (string $id): string => 'enter:' . $id, $expected), $entered);
            $resolved = \array_values(
                \array_filter(
                    $state->events,
                    static fn (string $event): bool => \str_starts_with($event, 'resolve:'),
                ),
            );
            $selected = $path === '/mutation/fifth' ? ['mutate', 'restore'] : $local;
            self::assertSame(\array_map(static fn (string $id): string => 'resolve:' . $id, $selected), $resolved);
            if ($actions === 1) {
                self::assertContains('construct', $state->events);
                self::assertContains('invoke', $state->events);
                $position = \array_search('construct', $state->events, true);
                self::assertSame('enter:' . $expected[\array_key_last($expected)], $state->events[$position - 1]);
                self::assertSame('invoke', $state->events[$position + 1]);
            }
            $body = (string) $response->getBody();
            if ($status === 200 && $actions === 1) {
                $slug = \substr($path, \strrpos($path, '/') + 1);
                self::assertSame('{"argument":"hello-service","method":"GET","slug":"' . $slug . '"}' . "\n", $body);
                self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
            } elseif ($path === '/_alive') {
                self::assertSame('ALIVE', $body);
            } elseif ($path === '/health') {
                self::assertSame('{"error":"owner missing"}' . "\n", $body);
            } elseif ($status === 403) {
                self::assertSame('denied', $body);
            } else {
                $title = match ($status) {
                    404 => 'Not Found',
                    405 => 'Method Not Allowed',
                    default => 'Internal Server Error'
                };
                self::assertSame(
                    '{"status":' . $status . ',"title":"' . $title . '","type":"about:blank"}' . "\n",
                    $body,
                );
                self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
            }
            self::assertSame($status === 405 ? 'GET, POST' : '', $response->getHeaderLine('Allow'));
            if ($status < 400 || $status === 403 || $status === 501) {
                $decorated = \array_reverse($expected);
                self::assertSame(\implode(',', $decorated), $response->getHeaderLine('X-Hello-Order'));
            }
            if ($reason !== null) {
                self::assertCount(1, $state->failures);
                self::assertSame($reason, $state->failures[0]->getMessage());
                if (\str_starts_with($reason, 'http-pipeline-')) {
                    self::assertSame(\LogicException::class, $state->failures[0]::class);
                    self::assertSame($state->caught, $state->failures[0]);
                }
            }
            $before = $state->events;
            foreach ($state->retained as $continuation) {
                $continuationClass ??= $continuation::class;
                self::assertSame($continuationClass, $continuation::class);
                self::assertNotContains($continuation::class, $host->serviceIds());
                $failure = self::failure(static fn () => $continuation->handle($request));
                self::assertSame(\LogicException::class, $failure::class);
                self::assertSame('http-pipeline-continuation-expired', $failure->getMessage());
                self::assertSame(null, new \ReflectionProperty($continuation, 'next')->getValue($continuation));
                self::assertSame(
                    [],
                    new \ReflectionProperty($continuation, 'protectedValues')->getValue($continuation),
                );
                self::assertSame(
                    null,
                    new \ReflectionProperty($continuation, 'routingInputs')->getValue($continuation),
                );
            }
            self::assertSame($before, $state->events);
            self::assertSame($count + $actions, $state->constructed);
            self::assertSame($spanCount + $actions, $tracer->starts);
            self::assertSame($ends + $actions, $tracer->ends);
            self::assertSame($counter + $actions, \count($meter->incrementsFor('http.action_total')));
            self::assertSame($logCount + $actions, \count($logger->recordsFor('http.action.completed')));
            self::assertSame($durations + $actions, self::actionDurations($meter));
            foreach ($state->retained as $continuation) {
                self::assertNotContains($continuation, $previousContinuations);
            }
            $previousContinuations = $state->retained;
            self::assertSame($serviceIds, $host->serviceIds());
            self::assertSame($tagSnapshot, self::tags($host->get(TagRegistry::class)));
            $snapshot[] = [
                $status,
                $body,
                $response->getHeaderLine('Allow'),
                $response->getHeaderLine('X-Hello-Order'),
                $before,
            ];
        }
        return $snapshot;
    }

    private static function actionDurations(SystemEndpointRecordingMeter $meter): int
    {
        return \count(
            \array_filter(
                $meter->observations,
                static fn (array $item): bool => $item[0] === 'http.action_duration_ms',
            ),
        );
    }

    /** @param array<string, mixed> $attributes */
    private function request(
        SystemEndpointPsrFactories $factories,
        string $method,
        string $path,
        array $attributes = [],
        string $host = 'example.test',
    ): ServerRequestInterface {
        /** @var UriInterface&Stub $uri */
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn($host);
        $uri->method('getScheme')->willReturn('https');
        $uri->method('getQuery')->willReturn('');
        $uri->method('withPath')->willReturnCallback(
            fn (string $value): UriInterface => $this->request(
                $factories,
                $method,
                $value,
                $attributes,
                $host,
            )->getUri(),
        );
        $uri->method('withHost')->willReturnCallback(
            fn (string $value): UriInterface => $this->request(
                $factories,
                $method,
                $path,
                $attributes,
                $value,
            )->getUri(),
        );
        /** @var ServerRequestInterface&Stub $request */
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getAttributes')->willReturn($attributes);
        $request->method('getAttribute')->willReturnCallback(
            static fn (string $name, mixed $default = null): mixed => \array_key_exists($name, $attributes)
                ? $attributes[$name] : $default,
        );
        $request->method('getServerParams')->willReturn([]);
        $request->method('getBody')->willReturn($factories->stream(''));
        SystemEndpointPsrFactories::configureHeaders($request, []);
        $request->method('withAttribute')->willReturnCallback(
            fn (string $name, mixed $value): ServerRequestInterface => $this->request(
                $factories,
                $method,
                $path,
                [...$attributes, $name => $value],
                $host,
            ),
        );
        $request->method('withMethod')->willReturnCallback(
            fn (string $value): ServerRequestInterface => $this->request(
                $factories,
                $value,
                $path,
                $attributes,
                $host,
            ),
        );
        // Test-only transport adaptations preserve the unchanged routing inputs and attributes.
        $request->method('withUri')->willReturnCallback(
            fn (UriInterface $value, bool $preserveHost = false): ServerRequestInterface => $this->request(
                $factories,
                $method,
                $value->getPath(),
                $attributes,
                $value->getHost(),
            ),
        );
        return $request;
    }

    private static function assertPreflightFailures(ContainerInterface $host): void
    {
        $config = $host->get(ConfigRepositoryInterface::class);
        $plan = $host->get(ModulePlan::class);
        $terminal = $host->get(RequestHandlerInterface::class);
        $registry = $host->get(TagRegistry::class);
        $evidence = $host->get(HelloRouteEvidence::class);
        $before = $evidence->events;
        foreach (
            [
                [[RouterMiddleware::class], false, 'http-middleware-app-dispatch-pair-invalid'],
                [[ActionDispatchMiddleware::class], false, 'http-middleware-app-dispatch-pair-invalid'],
                [
                    [RouterMiddleware::class, ActionDispatchMiddleware::class],
                    false,
                    'http-middleware-app-dispatch-binding-invalid',
                ],
                [[RouterMiddleware::class], true, 'http-middleware-app-dispatch-invalid'],
                [[ActionDispatchMiddleware::class], true, 'http-middleware-app-dispatch-invalid'],
                [[HttpErrorHandlingMiddleware::class], false, 'http-middleware-app-error-boundary-invalid'],
            ] as [$ids, $malformed, $reason]
        ) {
            $tags = new TagRegistry();
            foreach ($registry->tagNames() as $tag) {
                foreach ($registry->all($tag) as $item) {
                    if (!\in_array($item->id(), $ids, true)) {
                        $tags->add($tag, $item->id(), $item->priority(), $item->meta());
                    } elseif ($malformed) {
                        $tags->add($tag, $item->id(), $item->priority() + 1, $item->meta());
                    }
                }
            }
            $failure = self::failure(static fn () => HttpServiceFactory::pipeline(
                $host,
                $config,
                new HttpMiddlewareStackBuilder($tags),
                $terminal,
                $plan,
            ));
            self::assertSame(\InvalidArgumentException::class, $failure::class);
            self::assertSame($reason, $failure->getMessage());
        }
        foreach (
            [
                RouterMiddleware::class,
                ActionDispatchMiddleware::class,
                HttpErrorHandlingMiddleware::class,
            ] as $id
        ) {
            $override = new HelloRouteRoleOverride($host, $id);
            $failure = self::failure(static fn () => HttpServiceFactory::pipeline(
                $override,
                $config,
                new HttpMiddlewareStackBuilder($registry),
                $terminal,
                $plan,
            ));
            self::assertSame(\InvalidArgumentException::class, $failure::class);
            self::assertSame(
                $id === HttpErrorHandlingMiddleware::class
                    ? 'http-middleware-app-error-boundary-invalid' : 'http-middleware-app-dispatch-binding-invalid',
                $failure->getMessage(),
            );
        }
        self::assertSame($before, $evidence->events);
    }

    private static function assertGraphFailures(ContainerInterface $source): void
    {
        $execution = $source->get(KernelOpsExecutionServices::class);
        $resolution = $execution->moduleResolutionOrchestrator()->resolve($source->get(BootstrapConfig::class));
        $config = $source->get(ConfigRepositoryInterface::class)->all();
        foreach (['handler', 'argument', 'middleware'] as $kind) {
            $bad = $config;
            match ($kind) {
                'handler' => $bad['http_app']['handlers'][0]['service_id'] = 'hello.missing',
                'argument' => $bad['http_app']['arguments']['allow_container_types'][] = HelloRouteMissingArgument::class,
                'middleware' => $bad['http_app']['route_middleware'][] = 'hello.missing',
            };
            $failure = self::failure(
                static fn () => $execution->runtimeContainerGraphCompiler()->compile($resolution, $bad),
            );
            self::assertInstanceOf(ContainerDefinitionInvalidException::class, $failure);
            self::assertSame('required-service-invalid', $failure->reason());
        }
    }

    private static function assertReadinessFailures(string $root, string $artifactRoot): void
    {
        $path = $artifactRoot . '/routes.php';
        $bytes = \file_get_contents($path);
        self::assertIsString($bytes);
        try {
            foreach (['missing', 'invalid'] as $mode) {
                if ($mode === 'missing') {
                    self::assertTrue(\unlink($path));
                } else {
                    RoutingIntegrationFixture::write($path, '<?php return false;');
                }
                \clearstatcache(true, $path);
                foreach (
                    [
                        RoutingIntegrationFixture::source($root, AppTarget::Web),
                        self::compiled($root, $artifactRoot),
                    ] as $host
                ) {
                    $state = $host->get(HelloRouteEvidence::class);
                    $failure = self::failure(static fn () => self::ready($host));
                    self::assertInstanceOf(ContainerException::class, $failure);
                    if ($host->get(RuntimeDiagnosticsInput::class)->artifactGenerationLoaded()) {
                        self::assertSame('container-compiled-service-reference-failed', $failure->reason());
                        self::assertNull($failure->getPrevious());
                    } else {
                        self::assertSame('container-definition-class-instantiation-failed', $failure->reason());
                        self::assertInstanceOf(RoutesArtifactInvalidException::class, $failure->getPrevious());
                    }
                    // The compiled container deliberately drops nested construction failures.
                    // Inspect the same real reader independently for the routing-owned reason.
                    $routingFailure = self::failure(static fn () => $host->get(RoutesArtifactReader::class)->read());
                    self::assertInstanceOf(RoutesArtifactInvalidException::class, $routingFailure);
                    self::assertSame(
                        $mode === 'missing' ? 'routes-artifact-missing' : 'routes-artifact-malformed',
                        $routingFailure->reason(),
                    );
                    self::assertSame([], $state->events);
                    self::assertSame(0, $state->constructed);
                }
            }
        } finally {
            RoutingIntegrationFixture::write($path, $bytes);
        }
    }

    private static function assertMissingModuleFailures(string $root, ContainerInterface $source): void
    {
        $composer = \dirname(new \ReflectionClass(InstalledVersions::class)->getFileName());
        $path = $composer . '/installed.json';
        $bytes = \file_get_contents($path);
        self::assertIsString($bytes);
        $metadata = \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
        $execution = $source->get(KernelOpsExecutionServices::class);
        $bootstrap = $source->get(BootstrapConfig::class);
        $presetPath = $root . '/config/modes/hello-fixture.php';
        $presetBytes = \file_get_contents($presetPath);
        self::assertIsString($presetBytes);
        $preset = require $presetPath;
        // Isolate the dependency closure from explicitly selected roots for these failures.
        $preset['required'] = ['platform.http-app'];
        try {
            RoutingIntegrationFixture::php($presetPath, $preset);
            $resolution = $execution->moduleResolutionOrchestrator()->resolve($bootstrap);
            foreach (
                [
                    'core.foundation',
                    'platform.errors',
                    'platform.http',
                    'platform.problem-details',
                    'platform.routing',
                ] as $id
            ) {
                self::assertTrue($resolution->plan()->hasEnabledModule($id));
            }
            foreach (
                [
                    'core-foundation',
                    'platform-errors',
                    'platform-http',
                    'platform-problem-details',
                    'platform-routing',
                ] as $package
            ) {
                $bad = $metadata;
                $bad['packages'] = \array_values(
                    \array_filter(
                        $metadata['packages'],
                        static fn (array $record): bool => $record['name'] !== 'coretsia/' . $package,
                    ),
                );
                RoutingIntegrationFixture::write($path, \json_encode($bad, \JSON_THROW_ON_ERROR));
                $failure = self::failure(
                    static fn () => $execution->moduleResolutionOrchestrator()->resolve($bootstrap),
                );
                self::assertInstanceOf(ModuleResolutionException::class, $failure);
                self::assertSame('CORETSIA_MODULE_REQUIRED_MISSING', $failure->errorCode());
                self::assertSame('dependency-required-module-missing', $failure->reason());
                self::assertSame(\implode('.', \explode('-', $package, 2)), $failure->context()['missingModuleId']);
            }
        } finally {
            RoutingIntegrationFixture::write($path, $bytes);
            RoutingIntegrationFixture::write($presetPath, $presetBytes);
        }
    }

    private static function failure(\Closure $operation): \Throwable
    {
        try {
            $operation();
        } catch (\Throwable $failure) {
            return $failure;
        }
        self::fail('Expected canonical failure.');
    }
}

/** Test-only application provider; all definitions are serializable through container@1. */
final class HelloRouteDefinitions implements ContainerDefinitionProviderInterface
{
    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $evidence = ContainerValueReference::service(HelloRouteEvidence::class);
        $definitions
            ->classService(HelloRouteProvider::class, HelloRouteProvider::class)
            ->tag(ReservedTags::ROUTING_ROUTE_PROVIDER, HelloRouteProvider::class)
            ->classService('hello.action', HelloRouteAction::class, [$evidence], false)
            ->classService(HelloRouteArgument::class, HelloRouteArgument::class)
            ->classService(SystemEndpointPsrFactories::class, SystemEndpointPsrFactories::class)
            ->alias(ResponseFactoryInterface::class, SystemEndpointPsrFactories::class)
            ->alias(StreamFactoryInterface::class, SystemEndpointPsrFactories::class)
            ->classService(MeterPortInterface::class, SystemEndpointRecordingMeter::class, [false])
            ->classService(LoggerInterface::class, SystemEndpointRecordingLogger::class, [false])
            ->classService(TracerPortInterface::class, HelloRouteTracer::class)
            ->classService(HelloRouteErrorProbe::class, HelloRouteErrorProbe::class, [$evidence])
            ->tag(ReservedTags::ERROR_MAPPER, HelloRouteErrorProbe::class, 2000);
    }
}

/** A middleware contributor needs only Foundation registration and the PSR-15 contract. */
final class HelloRouteMiddlewareDefinitions implements ContainerDefinitionProviderInterface
{
    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $evidence = ContainerValueReference::service(HelloRouteEvidence::class);
        $factory = ContainerValueReference::service(ResponseFactoryInterface::class);
        $streams = ContainerValueReference::service(StreamFactoryInterface::class);
        $definitions
            ->classService(
                HelloRouteEvidence::class,
                HelloRouteEvidence::class,
                [ContainerValueReference::service(ContextStore::class)],
            )
            ->tag(ReservedTags::KERNEL_HOOK_BEFORE_UOW, HelloRouteEvidence::class)
            ->tag(ReservedTags::KERNEL_HOOK_AFTER_UOW, HelloRouteEvidence::class)
            ->tag(ReservedTags::KERNEL_RESET, HelloRouteEvidence::class);
        foreach (
            [
                ['high', ReservedTags::HTTP_MIDDLEWARE_APP, 200],
                ['low', ReservedTags::HTTP_MIDDLEWARE_APP, 0],
                ['route-pre', ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE, 0],
                ['route', ReservedTags::HTTP_MIDDLEWARE_ROUTE, -2000],
            ] as [$id, $tag, $priority]
        ) {
            $definitions->classService(
                'hello.' . $id,
                HelloRouteMiddleware::class,
                [$evidence, $factory, $streams, $id],
            )
                ->tag($tag, 'hello.' . $id, $priority);
        }
        foreach (['z', 'a', 'deny', 'mutate', 'restore', 'repeat'] as $id) {
            $definitions->classService(
                'hello.' . $id,
                HelloRouteMiddleware::class,
                [$evidence, $factory, $streams, $id],
                false,
            );
        }
        $definitions
            ->classService(
                HelloRouteManualApp::class,
                HelloRouteManualApp::class,
                [$evidence, $factory, $streams, 'manual-app'],
            )
            ->classService(
                HelloRouteManualRoute::class,
                HelloRouteManualRoute::class,
                [$evidence, $factory, $streams, 'manual-route'],
            );
    }
}

final class HelloRouteProvider implements RouteProviderInterface
{
    public function id(): string
    {
        return 'hello.consumer';
    }

    public function routes(): array
    {
        $routes = [];
        foreach (
            [
                ['hello', ['GET', 'POST'], ['hello.z', 'hello.a']],
                ['alternate', ['GET'], ['hello.a', 'hello.z']],
                ['protected', ['GET'], ['hello.z', 'hello.deny']],
                ['plain', ['GET'], []],
                ['invalid', ['GET'], ['hello.z', 'hello.unadmitted']],
                ['mutation', ['GET'], ['hello.mutate', 'hello.restore']],
                ['repeated', ['GET'], ['hello.repeat']],
            ] as [$name, $methods, $middleware]
        ) {
            $routes[] = new RouteDefinition(
                $name,
                $methods,
                '/' . $name . '/{slug}',
                'hello.show:detail',
                metadata: ['http_app_middleware' => $middleware],
            );
        }
        return $routes;
    }
}

final class HelloRouteEvidence implements BeforeUowHookInterface, AfterUowHookInterface, ResetInterface
{
    /** @var list<string> */
    public array $events = [];
    /** @var list<\Throwable> */
    public array $failures = [];
    /** @var list<RequestHandlerInterface> */
    public array $retained = [];
    public ?\Throwable $caught = null;
    public int $constructed = 0;
    public int $invoked = 0;
    public string $mode = 'normal';
    public bool $active = false;
    public ?string $correlation = null;
    private ?string $uow = null;

    public function __construct(private readonly ContextStore $context)
    {
    }

    public function assertCanonicalContext(?string $pathTemplate): void
    {
        $expected = [
            ContextKeys::CORRELATION_ID => $this->correlation,
            ContextKeys::UOW_ID => $this->uow,
            ContextKeys::UOW_TYPE => 'http',
        ];

        if ($pathTemplate !== null) {
            $expected[ContextKeys::PATH_TEMPLATE] = $pathTemplate;
        }

        TestCase::assertSame($expected, $this->context->all());
    }

    public function beforeUow(array $context): void
    {
        TestCase::assertFalse($this->active);
        TestCase::assertSame('http', $context['type']);
        $this->uow = $context['uowId'];
        $this->correlation = $context['correlationId'];
        $this->active = true;
        $this->events[] = 'before';
    }

    public function afterUow(array $context, array $result): void
    {
        TestCase::assertTrue($this->active);
        TestCase::assertSame($this->uow, $context['uowId']);
        $this->events[] = 'after';
    }

    public function reset(): void
    {
        TestCase::assertTrue($this->active);
        $this->events[] = 'reset';
        $this->active = false;
        $this->uow = null;
    }
}

final readonly class HelloRouteArgument
{
    public function value(): string
    {
        return 'hello-service';
    }
}

final class HelloRouteMissingArgument
{
}

final readonly class HelloRouteAction
{
    public function __construct(private HelloRouteEvidence $evidence)
    {
        TestCase::assertTrue($evidence->active);
        ++$evidence->constructed;
        $evidence->events[] = 'construct';
    }

    public function __invoke(ServerRequestInterface $request, string $slug, HelloRouteArgument $argument): array
    {
        TestCase::assertTrue($this->evidence->active);
        $match = RouteDispatchState::fromRequest($request)->match();
        $this->evidence->assertCanonicalContext($match?->pathTemplate());
        TestCase::assertSame($slug, $match?->parameters()['slug']);
        ++$this->evidence->invoked;
        $this->evidence->events[] = 'invoke';

        return ['slug' => $slug, 'method' => $request->getMethod(), 'argument' => $argument->value()];
    }
}

class HelloRouteMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HelloRouteEvidence $evidence,
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
        private readonly string $id,
    ) {
        if (\in_array($id, ['z', 'a', 'deny', 'mutate', 'restore', 'repeat'], true)) {
            TestCase::assertTrue($evidence->active);
            $evidence->events[] = 'resolve:' . $id;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        TestCase::assertTrue($this->evidence->active);
        $this->evidence->events[] = 'enter:' . $this->id;
        $this->evidence->retained[] = $handler;
        $mode = $this->evidence->mode;
        if ($this->id === 'high') {
            TestCase::assertFalse(\array_key_exists(RouteDispatchState::ATTRIBUTE, $request->getAttributes()));
            $this->evidence->assertCanonicalContext(null);
        } else {
            $state = RouteDispatchState::fromRequest($request);
            $this->evidence->assertCanonicalContext($state->match()?->pathTemplate());
            if (
                $this->id !== 'restore'
                && !($this->id === 'manual-app' && \in_array($mode, ['path', 'method', 'host'], true))
            ) {
                $state->assertRequest($request);
            }
            if ($this->id === 'low') {
                $path = $request->getUri()->getPath();
                TestCase::assertSame(
                    \in_array($path, ['/_alive', '/health'], true) ? null : 'hello.show:detail',
                    $state->match()?->handler(),
                );
            }
        }
        try {
            if ($this->id === 'deny' || ($this->id === 'low' && $mode === 'short')) {
                $response = $this->responses->createResponse(403)->withBody($this->streams->createStream('denied'));
            } elseif ($this->id === 'low' && $mode === 'state') {
                $state = RouteDispatchState::fromRequest($request);
                $match = $state->match() ?? throw new \LogicException('expected-match');
                $response = $handler->handle(
                    $request->withAttribute(
                        RouteDispatchState::ATTRIBUTE,
                        RouteDispatchState::matched($request, $match),
                    ),
                );
            } elseif (
                $this->id === 'mutate'
                || ($this->id === 'low' && \in_array($mode, ['path', 'method', 'host'], true))
            ) {
                $changed = match ($mode) {
                    'method', 'local-method' => $request->withMethod('POST'),
                    'host', 'local-host' => $request->withUri($request->getUri()->withHost('changed.test')),
                    default => $request->withUri($request->getUri()->withPath('/changed')),
                };
                $response = $handler->handle($changed);
            } elseif (
                $this->id === 'restore'
                || ($this->id === 'manual-app' && \in_array($mode, ['path', 'method', 'host'], true))
            ) {
                $path = $this->id === 'restore' ? '/mutation/fifth' : '/hello/path';
                $restored = match ($mode) {
                    'method', 'local-method' => $request->withMethod('GET'),
                    'host', 'local-host' => $request->withUri($request->getUri()->withHost('example.test')),
                    default => $request->withUri($request->getUri()->withPath($path)),
                };
                $response = $handler->handle($restored);
            } else {
                $response = $handler->handle($request);
                if ($this->id === 'repeat' || ($this->id === 'low' && $mode === 'repeat')) {
                    $response = $handler->handle($request);
                }
            }
        } catch (\LogicException $failure) {
            $this->evidence->caught ??= $failure;
            throw $failure;
        }
        $this->evidence->events[] = 'leave:' . $this->id;
        $order = $response->getHeaderLine('X-Hello-Order');

        return $response->withHeader('X-Hello-Order', $order === '' ? $this->id : $order . ',' . $this->id);
    }
}

final class HelloRouteManualApp extends HelloRouteMiddleware
{
}

final class HelloRouteManualRoute extends HelloRouteMiddleware
{
}

final readonly class HelloRouteErrorProbe implements ExceptionMapperInterface
{
    public function __construct(private HelloRouteEvidence $evidence)
    {
    }

    public function map(\Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
    {
        $this->evidence->failures[] = $throwable;

        return null;
    }
}

/** Fault-injected effective binding view for the real HTTP composition boundary only. */
final readonly class HelloRouteRoleOverride implements ContainerInterface
{
    public function __construct(private ContainerInterface $container, private string $id)
    {
    }

    public function get(string $id): mixed
    {
        return $id === $this->id ? $this->container->get('hello.high') : $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }
}

/** Record action-only span lifecycle without perturbing other canonical spans. */
final class HelloRouteTracer implements TracerPortInterface
{
    public int $starts = 0;
    public int $ends = 0;

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        if ($name !== 'http.action') {
            return new NoopSpan();
        }
        ++$this->starts;

        return new HelloRouteSpan($this);
    }

    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        $span = $this->startSpan($name, $attributes);
        try {
            return $callback($span);
        } finally {
            $span->end();
        }
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final readonly class HelloRouteSpan implements SpanInterface
{
    public function __construct(private HelloRouteTracer $tracer)
    {
    }

    public function name(): string
    {
        return 'http.action';
    }

    public function setAttribute(string $key, mixed $value): void
    {
    }

    public function setAttributes(array $attributes): void
    {
    }

    public function addEvent(string $name, array $attributes = []): void
    {
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
    }

    public function end(): void
    {
        ++$this->tracer->ends;
    }
}
