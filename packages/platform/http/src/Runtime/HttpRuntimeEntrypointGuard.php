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

namespace Coretsia\Platform\Http\Runtime;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Platform\Http\Module\HttpModule;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * HTTP-owned entrypoint compatibility and effective PSR-17 readiness boundary.
 *
 * The owning entrypoint supplies the already-selected runtime contributions
 * after canonical container composition and before HTTP runtime construction.
 * Kernel alone owns driver matrix validation. Transport-specific readiness is
 * separate and remains with the owning HTTP entrypoint.
 *
 * @internal
 */
final readonly class HttpRuntimeEntrypointGuard
{
    /**
     * Fixed HTTP-owned mapping shared with declarative factory selection.
     *
     * @var array<string, array{response: string, stream: string}>
     */
    public const array BUILTIN_FACTORY_CLASSES = [
        'nyholm' => [
            'response' => 'Nyholm\\Psr7\\Factory\\Psr17Factory',
            'stream' => 'Nyholm\\Psr7\\Factory\\Psr17Factory',
        ],
        'guzzlehttp' => [
            'response' => 'GuzzleHttp\\Psr7\\HttpFactory',
            'stream' => 'GuzzleHttp\\Psr7\\HttpFactory',
        ],
        'laminas' => [
            'response' => 'Laminas\\Diactoros\\ResponseFactory',
            'stream' => 'Laminas\\Diactoros\\StreamFactory',
        ],
        'slim' => [
            'response' => 'Slim\\Psr7\\Factory\\ResponseFactory',
            'stream' => 'Slim\\Psr7\\Factory\\StreamFactory',
        ],
    ];

    public function __construct(
        private RuntimeDriverResolver $runtimeDriverResolver,
    ) {
    }

    /**
     * Validates only HTTP module, Kernel driver matrix and effective factories.
     * No UnitOfWork, HTTP pipeline, transport, or factory fallback is started.
     */
    public function assertEntrypointAllowed(
        ConfigRepositoryInterface $config,
        ModulePlan $modulePlan,
        RuntimeDriverContributions $contributions,
        ContainerInterface $container,
    ): void {
        if (!$modulePlan->hasEnabledModule(HttpModule::MODULE_ID)) {
            throw new \RuntimeException('http-runtime-module-not-enabled');
        }

        $this->runtimeDriverResolver->resolve($config, $contributions);

        $selection = $config->get('http.factory');
        if (!\is_string($selection) || (
            $selection !== 'external' && !isset(self::BUILTIN_FACTORY_CLASSES[$selection])
        )) {
            throw new \InvalidArgumentException('http-factory-selection-invalid');
        }

        $expected = self::BUILTIN_FACTORY_CLASSES[$selection] ?? null;
        if ($expected !== null && (
            !self::factoryClassExists($expected['response'])
                || !self::factoryClassExists($expected['stream'])
        )) {
            throw new \RuntimeException('http-factory-implementation-unavailable');
        }

        // Resolve final effective bindings, including later application overrides.
        // Canonical container resolution owns missing-reference diagnostics.
        $responses = $container->get(ResponseFactoryInterface::class);
        $streams = $container->get(StreamFactoryInterface::class);

        if (!$responses instanceof ResponseFactoryInterface
            || !$streams instanceof StreamFactoryInterface
        ) {
            throw new \RuntimeException('http-factory-contract-invalid');
        }

        if ($expected !== null && (
            $responses::class !== $expected['response']
                || $streams::class !== $expected['stream']
        )) {
            throw new \RuntimeException('http-factory-selection-mismatch');
        }
    }

    private static function factoryClassExists(string $factoryClass): bool
    {
        return \class_exists($factoryClass);
    }
}
