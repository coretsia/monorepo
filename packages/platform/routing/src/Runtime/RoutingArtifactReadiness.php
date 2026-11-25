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

namespace Coretsia\Platform\Routing\Runtime;

use Coretsia\Contracts\Routing\RoutingArtifactReadinessInterface;

/**
 * Readiness of the exact immutable route table used by CompiledRouter.
 *
 * The mandatory dependency is fully hydrated during construction. No second
 * artifact read, source discovery or matching operation is performed here.
 *
 * @internal
 */
final readonly class RoutingArtifactReadiness implements RoutingArtifactReadinessInterface
{
    public function __construct(
        // @phpstan-ignore property.onlyWritten (Retains the shared validated route table as the readiness dependency.)
        private CompiledRouteTable $routeTable,
    ) {
    }

    public function assertReady(): void
    {
        // Successful construction of the shared route table proves readiness.
    }
}
