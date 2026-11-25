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

namespace Coretsia\Platform\Routing\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Routing\Console\RoutesCommandTargetResolver;
use Coretsia\Platform\Routing\Console\RoutesCompileCommand;
use Coretsia\Platform\Routing\Console\RoutesValidateCommand;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Psr\Log\LoggerInterface;

/**
 * Stateless construction of Routing services requiring runtime inputs.
 *
 * The declarative container controls service sharing and resolution timing.
 * No source host is booted, route artifact is loaded, or provider is discovered
 * during service definition or command construction.
 *
 * @internal
 */
final class RoutingServiceFactory
{
    public static function matchObserver(
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        LoggerInterface $logger,
        Stopwatch $stopwatch,
        ConfigRepositoryInterface $config,
    ): RoutingMatchObserver {
        return new RoutingMatchObserver(
            $tracer,
            $meter,
            $logger,
            $stopwatch,
            self::enabled($config, 'routing.tracing.enabled'),
            self::enabled($config, 'routing.metrics.enabled'),
            self::enabled($config, 'routing.logging.enabled'),
        );
    }

    public static function commandTargetResolver(
        RuntimePathContext $paths,
        RuntimeDiagnosticsInput $diagnostics,
        ModulePlan $modulePlan,
    ): RoutesCommandTargetResolver {
        // The public source-host booter is stateless and is not a runtime graph service.
        // Its target-local boot operation runs only when a CLI command is executed.
        return new RoutesCommandTargetResolver(
            $paths,
            $diagnostics,
            $modulePlan,
            new KernelOpsHostBooter(),
        );
    }

    public static function compileCommand(
        RoutesCommandTargetResolver $targets,
    ): RoutesCompileCommand {
        return new RoutesCompileCommand($targets);
    }

    public static function validateCommand(
        RoutesCommandTargetResolver $targets,
    ): RoutesValidateCommand {
        return new RoutesValidateCommand($targets);
    }

    private static function enabled(ConfigRepositoryInterface $config, string $path): bool
    {
        $value = $config->get($path);
        if (!\is_bool($value)) {
            throw new \InvalidArgumentException('routing-config-invalid');
        }

        return $value;
    }
}
