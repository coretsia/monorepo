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

namespace Coretsia\Platform\Http\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextWriterInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Console\MaintenanceDisableCommand;
use Coretsia\Platform\Http\Console\MaintenanceEnableCommand;
use Coretsia\Platform\Http\Console\MaintenanceStatusCommand;
use Coretsia\Platform\Http\Debug\DebugCacheHandler;
use Coretsia\Platform\Http\Debug\DebugConfigHandler;
use Coretsia\Platform\Http\Debug\DebugGuard;
use Coretsia\Platform\Http\Debug\DebugHeadersHandler;
use Coretsia\Platform\Http\Debug\DebugModuleListHandler;
use Coretsia\Platform\Http\Debug\DebugRouter;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Host\TrustedHostPolicy;
use Coretsia\Platform\Http\HttpKernel;
use Coretsia\Platform\Http\HttpKernelInterface;
use Coretsia\Platform\Http\HttpsRedirect\HttpsRedirectPolicy;
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
use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Coretsia\Platform\Http\Routing\FallbackRouter;
use Coretsia\Platform\Http\Routing\FallbackRouterHandler;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Canonical declarative HTTP runtime, middleware and PSR-17 factory wiring.
 *
 * All definitions and tags are independent of feature-toggle values. The
 * middleware stack builder owns effective toggle evaluation and ordering.
 * Application providers may override bindings under Foundation's existing
 * later-binding-wins policy; HTTP readiness checks their final factory types.
 *
 * @internal
 */
final class HttpServiceProvider implements ServiceProviderInterface, ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $http = $context->configRoot('http');
        $selection = $http['factory'] ?? null;
        if (
            !\is_string($selection)
            || ($selection !== 'external' && !isset(HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES[$selection]))
        ) {
            throw new \InvalidArgumentException('http-factory-selection-invalid');
        }

        $correlation = HttpServiceFactory::section($http, 'correlation');
        $requestId = HttpServiceFactory::section($http, 'request_id');
        $maintenance = HttpServiceFactory::section($http, 'maintenance');
        $proxy = HttpServiceFactory::section($http, 'proxy');
        $hosts = HttpServiceFactory::section($http, 'hosts');
        $request = HttpServiceFactory::section($http, 'request');
        $json = HttpServiceFactory::section($request, 'json');
        $enrich = HttpServiceFactory::section(
            HttpServiceFactory::section($http, 'context'),
            'enrich',
        );

        $correlationEnabled = HttpServiceFactory::boolean($correlation, 'enabled');
        $correlationHeader = HttpServiceFactory::string($correlation, 'header_name');
        $requestIdEnabled = HttpServiceFactory::boolean($requestId, 'enabled');
        $requestIdHeader = HttpServiceFactory::string($requestId, 'header_name');
        $requestIdAcceptFromUpstream = HttpServiceFactory::boolean($requestId, 'accept_from_upstream');
        $requestIdMaxLength = HttpServiceFactory::integer($requestId, 'max_length');
        $tracingEnabled = HttpServiceFactory::boolean(
            HttpServiceFactory::section($http, 'tracing'),
            'enabled',
        );
        $metricsEnabled = HttpServiceFactory::boolean(
            HttpServiceFactory::section($http, 'metrics'),
            'enabled',
        );
        $accessLogEnabled = HttpServiceFactory::boolean(
            HttpServiceFactory::section($http, 'access_log'),
            'enabled',
        );
        $maintenanceEnabled = HttpServiceFactory::boolean($maintenance, 'enabled');
        $maintenanceRetryAfterDefaultSeconds = HttpServiceFactory::integer($maintenance, 'retry_after_default_seconds');
        $proxyEnabled = HttpServiceFactory::boolean($proxy, 'enabled');
        $contextEnabled = HttpServiceFactory::boolean($enrich, 'enabled');
        $hostsEnabled = HttpServiceFactory::boolean($hosts, 'enabled');
        $jsonEnabled = HttpServiceFactory::boolean($json, 'enabled');
        $maxBodyBytes = HttpServiceFactory::integer($request, 'max_body_bytes');
        $maxJsonDepth = HttpServiceFactory::integer($json, 'max_depth');
        $allowedHosts = HttpServiceFactory::list($hosts, 'allowed');

        $definitions
            ->requireService(ContainerInterface::class)
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(TagRegistry::class)
            ->requireService(KernelRuntimeInterface::class)
            ->requireService(ModulePlan::class)
            ->requireService(RuntimeDriverResolver::class)
            ->requireService(RuntimeDiagnosticsInput::class)
            ->requireService(RuntimePathContext::class)
            ->requireService(ClockInterface::class)
            ->requireService(Stopwatch::class)
            ->requireService(ScopedFileLock::class)
            ->requireService(ContextWriterInterface::class)
            ->requireService(ContextAccessorInterface::class)
            ->requireService(UlidGenerator::class)
            ->requireService(CorrelationIdProviderInterface::class)
            ->requireService(ContextPropagationInterface::class)
            ->requireService(TracerPortInterface::class)
            ->requireService(MeterPortInterface::class)
            ->requireService(LoggerInterface::class)
            ->requireService(SensitiveDataRedactorInterface::class)
            ->requireService(SensitiveKeyClassifierInterface::class)
            ->requireService(ResponseFactoryInterface::class)
            ->requireService(StreamFactoryInterface::class);

        if ($selection !== 'external') {
            $factories = HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES[$selection];
            $definitions->classService($factories['response'], $factories['response']);
            if ($factories['stream'] !== $factories['response']) {
                $definitions->classService($factories['stream'], $factories['stream']);
            }

            $definitions
                ->alias(ResponseFactoryInterface::class, $factories['response'])
                ->alias(StreamFactoryInterface::class, $factories['stream']);
        }

        $definitions
            ->classService(
                MaintenanceStateStore::class,
                MaintenanceStateStore::class,
                [self::ref(RuntimePathContext::class), self::ref(ScopedFileLock::class)],
            )
            ->classMethodFactory(
                TrustedProxyPolicy::class,
                HttpServiceFactory::class,
                'trustedProxy',
                [self::ref(ConfigRepositoryInterface::class)],
            )
            ->classMethodFactory(
                DebugGuard::class,
                HttpServiceFactory::class,
                'debugGuard',
                [
                    self::ref(ConfigRepositoryInterface::class),
                    self::ref(RuntimeDiagnosticsInput::class),
                    self::ref(ModulePlan::class),
                    self::ref(TrustedProxyPolicy::class),
                ],
            )
            ->classService(
                TrustedHostPolicy::class,
                TrustedHostPolicy::class,
                [$allowedHosts],
            )
            ->classMethodFactory(
                SecurityHeadersPolicy::class,
                HttpServiceFactory::class,
                'securityHeaders',
                [self::ref(ConfigRepositoryInterface::class)],
            )
            ->classMethodFactory(
                HttpsRedirectPolicy::class,
                HttpServiceFactory::class,
                'httpsRedirect',
                [self::ref(ConfigRepositoryInterface::class)],
            )
            ->classService(
                HttpMiddlewareStackBuilder::class,
                HttpMiddlewareStackBuilder::class,
                [self::ref(TagRegistry::class)],
            )
            ->classService(
                HttpRuntimeEntrypointGuard::class,
                HttpRuntimeEntrypointGuard::class,
                [self::ref(RuntimeDriverResolver::class)],
            )
            ->classService(
                FallbackResponses::class,
                FallbackResponses::class,
                [self::ref(ResponseFactoryInterface::class), self::ref(StreamFactoryInterface::class)],
            )
            ->classService(
                DebugHeadersHandler::class,
                DebugHeadersHandler::class,
                [
                    self::ref(SensitiveDataRedactorInterface::class),
                    self::ref(FallbackResponses::class),
                ],
            )
            ->classService(
                DebugModuleListHandler::class,
                DebugModuleListHandler::class,
                [
                    self::ref(ModulePlan::class),
                    self::ref(FallbackResponses::class),
                ],
            )
            ->classService(
                DebugConfigHandler::class,
                DebugConfigHandler::class,
                [
                    self::ref(ConfigRepositoryInterface::class),
                    self::ref(DebugGuard::class),
                    self::ref(SensitiveDataRedactorInterface::class),
                    self::ref(SensitiveKeyClassifierInterface::class),
                    self::ref(FallbackResponses::class),
                ],
            )
            ->classService(
                DebugCacheHandler::class,
                DebugCacheHandler::class,
                [
                    self::ref(RuntimeDiagnosticsInput::class),
                    self::ref(FallbackResponses::class),
                ],
            )
            ->classService(
                DebugRouter::class,
                DebugRouter::class,
                [
                    self::ref(DebugGuard::class),
                    self::ref(DebugHeadersHandler::class),
                    self::ref(DebugModuleListHandler::class),
                    self::ref(DebugConfigHandler::class),
                    self::ref(DebugCacheHandler::class),
                    self::ref(MeterPortInterface::class),
                    self::ref(LoggerInterface::class),
                    $metricsEnabled,
                    $accessLogEnabled,
                ],
            )
            ->classService(
                FallbackRouter::class,
                FallbackRouter::class,
                [
                    self::ref(FallbackResponses::class),
                    self::ref(ClockInterface::class),
                    self::ref(MeterPortInterface::class),
                    $metricsEnabled,
                    self::ref(DebugRouter::class),
                ],
            )
            ->classService(
                FallbackRouterHandler::class,
                FallbackRouterHandler::class,
                [self::ref(FallbackRouter::class), self::ref(FallbackResponses::class)],
            )
            ->alias(RequestHandlerInterface::class, FallbackRouterHandler::class)
            ->classService(
                TraceContextMiddleware::class,
                TraceContextMiddleware::class,
                [self::ref(ContextPropagationInterface::class), $tracingEnabled],
            )
            ->classService(
                MaintenanceMiddleware::class,
                MaintenanceMiddleware::class,
                [
                    self::ref(MaintenanceStateStore::class),
                    self::ref(ResponseFactoryInterface::class),
                    self::ref(StreamFactoryInterface::class),
                    self::ref(MeterPortInterface::class),
                    self::ref(LoggerInterface::class),
                    $maintenanceEnabled,
                    $maintenanceRetryAfterDefaultSeconds,
                    $metricsEnabled,
                    $accessLogEnabled,
                ],
            )
            ->classService(
                TrustedProxyMiddleware::class,
                TrustedProxyMiddleware::class,
                [self::ref(TrustedProxyPolicy::class), $proxyEnabled],
            )
            ->classService(
                RequestContextMiddleware::class,
                RequestContextMiddleware::class,
                [self::ref(ContextWriterInterface::class), $contextEnabled, $proxyEnabled],
            )
            ->classService(
                TrustedHostMiddleware::class,
                TrustedHostMiddleware::class,
                [self::ref(TrustedHostPolicy::class), $hostsEnabled, $proxyEnabled],
            )
            ->classService(
                HttpsRedirectMiddleware::class,
                HttpsRedirectMiddleware::class,
                [
                    self::ref(HttpsRedirectPolicy::class),
                    self::ref(ResponseFactoryInterface::class),
                    self::ref(MeterPortInterface::class),
                    self::ref(LoggerInterface::class),
                    $proxyEnabled,
                    $metricsEnabled,
                    $accessLogEnabled,
                ],
            )
            ->classService(
                RequestBodySizeLimitMiddleware::class,
                RequestBodySizeLimitMiddleware::class,
                [self::ref(StreamFactoryInterface::class), $maxBodyBytes],
            )
            ->classService(
                JsonBodyParserMiddleware::class,
                JsonBodyParserMiddleware::class,
                [self::ref(StreamFactoryInterface::class), $jsonEnabled, $maxJsonDepth, $maxBodyBytes],
            );

        $tracingTagMeta = ['toggle' => 'http.tracing.enabled'];
        $maintenanceTagMeta = ['toggle' => 'http.maintenance.enabled'];
        $proxyTagMeta = ['toggle' => 'http.proxy.enabled'];
        $contextTagMeta = ['toggle' => 'http.context.enrich.enabled'];
        $hostsTagMeta = ['toggle' => 'http.hosts.enabled'];
        $httpsRedirectTagMeta = ['toggle' => 'http.https_redirect.enabled'];
        $jsonTagMeta = ['toggle' => 'http.request.json.enabled'];

        $definitions
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                TraceContextMiddleware::class,
                priority: 930,
                meta: $tracingTagMeta,
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                MaintenanceMiddleware::class,
                priority: 900,
                meta: $maintenanceTagMeta,
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                TrustedProxyMiddleware::class,
                priority: 880,
                meta: $proxyTagMeta,
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                RequestContextMiddleware::class,
                priority: 870,
                meta: $contextTagMeta,
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                TrustedHostMiddleware::class,
                priority: 860,
                meta: $hostsTagMeta,
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                HttpsRedirectMiddleware::class,
                priority: 850,
                meta: $httpsRedirectTagMeta,
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                RequestBodySizeLimitMiddleware::class,
                priority: 830,
                meta: [],
            )
            ->tag(
                ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
                JsonBodyParserMiddleware::class,
                priority: 800,
                meta: $jsonTagMeta,
            );

        $definitions
            ->classService(
                MaintenanceEnableCommand::class,
                MaintenanceEnableCommand::class,
                [self::ref(MaintenanceStateStore::class)],
            )
            ->classService(
                MaintenanceDisableCommand::class,
                MaintenanceDisableCommand::class,
                [self::ref(MaintenanceStateStore::class)],
            )
            ->classService(
                MaintenanceStatusCommand::class,
                MaintenanceStatusCommand::class,
                [self::ref(MaintenanceStateStore::class)],
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                MaintenanceEnableCommand::class,
                meta: self::commandMeta(
                    MaintenanceEnableCommand::NAME,
                    MaintenanceEnableCommand::SUMMARY,
                    MaintenanceEnableCommand::GROUP,
                    MaintenanceEnableCommand::HIDDEN,
                    MaintenanceEnableCommand::ARGUMENTS,
                    MaintenanceEnableCommand::OPTIONS,
                ),
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                MaintenanceDisableCommand::class,
                meta: self::commandMeta(
                    MaintenanceDisableCommand::NAME,
                    MaintenanceDisableCommand::SUMMARY,
                    MaintenanceDisableCommand::GROUP,
                    MaintenanceDisableCommand::HIDDEN,
                    MaintenanceDisableCommand::ARGUMENTS,
                    MaintenanceDisableCommand::OPTIONS,
                ),
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                MaintenanceStatusCommand::class,
                meta: self::commandMeta(
                    MaintenanceStatusCommand::NAME,
                    MaintenanceStatusCommand::SUMMARY,
                    MaintenanceStatusCommand::GROUP,
                    MaintenanceStatusCommand::HIDDEN,
                    MaintenanceStatusCommand::ARGUMENTS,
                    MaintenanceStatusCommand::OPTIONS,
                ),
            );

        $definitions
            ->classService(HttpExceptionMapper::class, HttpExceptionMapper::class)
            ->tag(
                ReservedTags::ERROR_MAPPER,
                HttpExceptionMapper::class,
                priority: 100,
                meta: [],
            )
            ->classService(
                RequestIdPolicy::class,
                RequestIdPolicy::class,
                [
                    self::ref(ContextWriterInterface::class),
                    self::ref(ContextAccessorInterface::class),
                    self::ref(UlidGenerator::class),
                    $requestIdEnabled,
                    $requestIdHeader,
                    $requestIdAcceptFromUpstream,
                    $requestIdMaxLength,
                ],
            )
            ->classService(
                HttpResponseFinalizer::class,
                HttpResponseFinalizer::class,
                [
                    self::ref(CorrelationIdProviderInterface::class),
                    $correlationEnabled,
                    $correlationHeader,
                    self::ref(RequestIdPolicy::class),
                    self::ref(SecurityHeadersPolicy::class),
                ],
            )
            ->classService(
                HttpRequestMetricsRecorder::class,
                HttpRequestMetricsRecorder::class,
                [self::ref(MeterPortInterface::class), $metricsEnabled],
            )
            ->classService(
                HttpAccessLogWriter::class,
                HttpAccessLogWriter::class,
                [self::ref(LoggerInterface::class), $accessLogEnabled],
            )
            ->classService(
                HttpRequestSpanRecorder::class,
                HttpRequestSpanRecorder::class,
                [self::ref(TracerPortInterface::class), $tracingEnabled],
            )
            ->classMethodFactory(
                Pipeline::class,
                HttpServiceFactory::class,
                'pipeline',
                [
                    self::ref(ContainerInterface::class),
                    self::ref(ConfigRepositoryInterface::class),
                    self::ref(HttpMiddlewareStackBuilder::class),
                    self::ref(RequestHandlerInterface::class),
                    self::ref(ModulePlan::class),
                ],
            )
            ->classService(
                HttpKernel::class,
                HttpKernel::class,
                [
                    self::ref(KernelRuntimeInterface::class),
                    self::ref(Stopwatch::class),
                    self::ref(Pipeline::class),
                    self::ref(HttpResponseFinalizer::class),
                    self::ref(HttpRequestMetricsRecorder::class),
                    self::ref(HttpAccessLogWriter::class),
                    self::ref(HttpRequestSpanRecorder::class),
                    self::ref(RequestIdPolicy::class),
                ],
            )
            ->alias(HttpKernelInterface::class, HttpKernel::class);
    }

    /**
     * @param list<array<string, mixed>> $arguments
     * @param list<array<string, mixed>> $options
     *
     * @return array{
     *     name: string,
     *     summary: string,
     *     group: string,
     *     hidden: bool,
     *     arguments: list<array<string, mixed>>,
     *     options: list<array<string, mixed>>
     * }
     */
    private static function commandMeta(
        string $name,
        string $summary,
        string $group,
        bool $hidden,
        array $arguments,
        array $options,
    ): array {
        return \compact(
            'name',
            'summary',
            'group',
            'hidden',
            'arguments',
            'options',
        );
    }

    private static function ref(string $serviceId): ContainerValueReference
    {
        return ContainerValueReference::service($serviceId);
    }
}
