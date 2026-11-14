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
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Host\TrustedHostPolicy;
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
use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\Routing\FallbackRouterHandler;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
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
        if (!\is_string($selection) || (
            $selection !== 'external'
                && !isset(HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES[$selection])
        )) {
            throw new \InvalidArgumentException('http-factory-selection-invalid');
        }

        $correlation = HttpServiceFactory::section($http, 'correlation');
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
        $proxyEnabled = HttpServiceFactory::boolean($proxy, 'enabled');
        $contextEnabled = HttpServiceFactory::boolean($enrich, 'enabled');
        $hostsEnabled = HttpServiceFactory::boolean($hosts, 'enabled');
        $jsonEnabled = HttpServiceFactory::boolean($json, 'enabled');
        $maxBodyBytes = HttpServiceFactory::integer($request, 'max_body_bytes');
        $maxJsonDepth = HttpServiceFactory::integer($json, 'max_depth');
        $trustedProxies = HttpServiceFactory::list($proxy, 'trusted_proxies');
        $allowedHosts = HttpServiceFactory::list($hosts, 'allowed');

        $definitions
            ->requireService(ContainerInterface::class)
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(TagRegistry::class)
            ->requireService(KernelRuntimeInterface::class)
            ->requireService(RuntimeDriverResolver::class)
            ->requireService(Stopwatch::class)
            ->requireService(ContextStore::class)
            ->requireService(CorrelationIdProviderInterface::class)
            ->requireService(ContextPropagationInterface::class)
            ->requireService(TracerPortInterface::class)
            ->requireService(MeterPortInterface::class)
            ->requireService(LoggerInterface::class)
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
                TrustedProxyPolicy::class,
                TrustedProxyPolicy::class,
                [$trustedProxies],
            )
            ->classService(
                TrustedHostPolicy::class,
                TrustedHostPolicy::class,
                [$allowedHosts],
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
                FallbackRouterHandler::class,
                FallbackRouterHandler::class,
                [self::ref(ResponseFactoryInterface::class), self::ref(StreamFactoryInterface::class)],
            )
            ->alias(RequestHandlerInterface::class, FallbackRouterHandler::class)
            ->classService(
                TraceContextMiddleware::class,
                TraceContextMiddleware::class,
                [self::ref(ContextPropagationInterface::class), $tracingEnabled],
            )
            ->classService(
                TrustedProxyMiddleware::class,
                TrustedProxyMiddleware::class,
                [self::ref(TrustedProxyPolicy::class), $proxyEnabled],
            )
            ->classService(
                RequestContextMiddleware::class,
                RequestContextMiddleware::class,
                [self::ref(ContextStore::class), $contextEnabled, $proxyEnabled],
            )
            ->classService(
                TrustedHostMiddleware::class,
                TrustedHostMiddleware::class,
                [self::ref(TrustedHostPolicy::class), $hostsEnabled, $proxyEnabled],
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
        $proxyTagMeta = ['toggle' => 'http.proxy.enabled'];
        $contextTagMeta = ['toggle' => 'http.context.enrich.enabled'];
        $hostsTagMeta = ['toggle' => 'http.hosts.enabled'];
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
            ->classService(HttpExceptionMapper::class, HttpExceptionMapper::class)
            ->tag(
                ReservedTags::ERROR_MAPPER,
                HttpExceptionMapper::class,
                priority: 100,
                meta: [],
            )
            ->classService(
                HttpResponseFinalizer::class,
                HttpResponseFinalizer::class,
                [self::ref(CorrelationIdProviderInterface::class), $correlationEnabled, $correlationHeader],
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
                ],
            )
            ->alias(HttpKernelInterface::class, HttpKernel::class);
    }

    private static function ref(string $serviceId): ContainerValueReference
    {
        return ContainerValueReference::service($serviceId);
    }
}
