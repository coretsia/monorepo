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

namespace Coretsia\Kernel\Provider;

use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;

/**
 * Kernel DI wiring entrypoint.
 *
 * This provider registers Kernel-owned runtime services without changing
 * provider ordering semantics. ContainerBuilder still preserves the exact
 * caller-supplied provider order.
 *
 * Wiring decisions:
 *
 * - the Kernel-owned config subset used by UnitOfWork shapes is validated
 *   early and deterministically;
 * - HookInvoker is registered as the Kernel hook invocation service;
 * - KernelRuntime is registered as the Kernel-owned UnitOfWork lifecycle
 *   orchestrator;
 * - KernelRuntimeInterface is bound to the KernelRuntime concrete service;
 * - HookInvoker receives the builder-owned TagRegistry so hook discovery order
 *   stays owned by Foundation TagRegistry;
 * - KernelRuntime receives context, reset, id, time, hook, logging, tracing,
 *   and metrics dependencies through DI via KernelServiceFactory;
 * - core/kernel does not define reset tag constants; the reset discovery tag
 *   remains owned by core/foundation.
 *
 * This provider must not emit stdout/stderr, must not use tooling-only
 * packages, must not introduce static mutable snapshots, must not trigger
 * reset orchestration, and must not start a UnitOfWork during registration.
 */
final class KernelServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $kernelConfig = $builder->configRoot('kernel');

        /*
         * Preserve the existing Kernel-owned config validation behavior.
         *
         * This validates only the UnitOfWork attributes defensive limits and
         * does not construct runtime lifecycle state.
         */
        KernelServiceFactory::unitOfWorkAttributeLimits($kernelConfig);

        $tagRegistry = $builder->tagRegistry();

        $builder->factory(
            HookInvoker::class,
            static fn (Container $container): HookInvoker => KernelServiceFactory::hookInvoker(
                container: $container,
                tagRegistry: $tagRegistry,
            ),
        );

        $builder->factory(
            KernelRuntime::class,
            static fn (Container $container): KernelRuntime => KernelServiceFactory::kernelRuntime(
                container: $container,
            ),
        );

        $builder->factory(
            KernelRuntimeInterface::class,
            static function (Container $container): KernelRuntimeInterface {
                $runtime = $container->get(KernelRuntime::class);

                if (!$runtime instanceof KernelRuntimeInterface) {
                    throw new ContainerException('kernel-runtime-interface-binding-invalid');
                }

                return $runtime;
            },
        );
    }
}
