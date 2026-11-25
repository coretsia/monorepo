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

namespace Coretsia\Platform\Routing\Console;

use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Routing\Exception\RoutesCommandTargetInvalidException;
use Psr\Container\ContainerInterface;

/**
 * Resolves an explicitly selected target-local Routing source host.
 *
 * Both Routing commands use the Console host's authoritative application root.
 * No source host is booted until command execution and explicit target validation.
 *
 * @internal
 */
final readonly class RoutesCommandTargetResolver
{
    public function __construct(
        private RuntimePathContext $paths,
        private RuntimeDiagnosticsInput $diagnostics,
        private ModulePlan $modulePlan,
        private KernelOpsHostBooter $hostBooter,
    ) {
    }

    public function resolve(InputInterface $input, string $commandName): ContainerInterface
    {
        if (
            $this->modulePlan->app() !== AppTarget::Console->value
            || $this->diagnostics->artifactGenerationLoaded()
        ) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        if (
            $input->commandName() !== $commandName
            || $input->arguments() !== []
            || \array_diff_key($input->options(), ['app' => true]) !== []
        ) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_TARGET_INVALID,
            );
        }

        if (!$input->hasOption('app') || $input->option('app') === null) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_TARGET_REQUIRED,
            );
        }

        $target = match ($input->option('app')) {
            AppTarget::Web->value => AppTarget::Web,
            AppTarget::Api->value => AppTarget::Api,
            default => null,
        };
        if ($target === null) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_TARGET_INVALID,
            );
        }

        try {
            $hostInput = new KernelOpsHostInput($this->paths->applicationRoot());
        } catch (\InvalidArgumentException) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        // Preserve Kernel's existing safe host-boot failure boundary unchanged.
        $container = $this->hostBooter->bootForTarget($hostInput, $target);

        try {
            $targetPlan = $container->get(ModulePlan::class);
            $targetDiagnostics = $container->get(RuntimeDiagnosticsInput::class);
        } catch (\Throwable) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        if (
            !$targetPlan instanceof ModulePlan
            || $targetPlan->app() !== $target->value
            || !$targetDiagnostics instanceof RuntimeDiagnosticsInput
            || $targetDiagnostics->artifactGenerationLoaded()
        ) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        if (!$targetPlan->hasEnabledModule('platform.routing')) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_MODULE_NOT_ENABLED,
            );
        }

        return $container;
    }
}
