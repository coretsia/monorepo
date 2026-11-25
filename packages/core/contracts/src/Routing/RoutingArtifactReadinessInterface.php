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

namespace Coretsia\Contracts\Routing;

/**
 * Transport-neutral readiness boundary for owner-managed routing artifacts.
 *
 * Implementations own artifact selection and validation. The port exposes
 * neither artifact paths or payloads nor filesystem or router operations.
 */
interface RoutingArtifactReadinessInterface
{
    /**
     * Completes normally only when routing is ready for runtime consumption.
     *
     * Readiness failures are reported through implementation-owned exceptions.
     */
    public function assertReady(): void;
}
