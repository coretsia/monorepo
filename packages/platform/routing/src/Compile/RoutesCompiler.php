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

namespace Coretsia\Platform\Routing\Compile;

use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Exception\RoutesCommandTargetInvalidException;

/**
 * Explicit target-local source compilation without filesystem publication.
 *
 * The authoritative ModulePlan and diagnostics input are supplied by the
 * selected Kernel source host. Registry ordering is never altered here.
 *
 * @internal
 */
final readonly class RoutesCompiler
{
    public function __construct(
        private ModulePlan $modulePlan,
        private RuntimeDiagnosticsInput $diagnostics,
        private RouteProviderRegistry $providers,
        private RoutesArtifactSchema $schema,
    ) {
    }

    /**
     * Validates all flattened declarations and returns a publication-ready
     * canonical routes@1 envelope. No artifact is read or written.
     *
     * @return array{_meta: array<string, int|string>, payload: array{app: string, routes: list<array<string, mixed>>}}
     */
    public function compile(): array
    {
        $app = $this->modulePlan->app();
        if (!\in_array($app, ['web', 'api'], true)) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_TARGET_INVALID,
            );
        }

        if ($this->diagnostics->artifactGenerationLoaded()) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        if (!$this->modulePlan->hasEnabledModule('platform.routing')) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_MODULE_NOT_ENABLED,
            );
        }

        $routes = $this->providers->routes();

        // The schema owns duplicate names/domains, reserved domains, metadata,
        // fingerprint, complete-envelope limits, and canonical serialized bytes.
        return $this->schema->createEnvelope($routes, $app, $this->diagnostics->appEnv());
    }
}
