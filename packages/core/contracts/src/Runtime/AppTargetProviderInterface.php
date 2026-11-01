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

namespace Coretsia\Contracts\Runtime;

/**
 * Read-only access to the already selected canonical application target.
 *
 * The Kernel-owned ModulePlan is the authority for this value. Implementations
 * MUST NOT select, discover, override, cache, or infer a second app target.
 * This port does not own configuration, context, or runtime lifecycle state.
 *
 * Supported values are web, api, console, and worker.
 */
interface AppTargetProviderInterface
{
    /**
     * Returns the canonical app target selected for the current application.
     *
     * @return 'web'|'api'|'console'|'worker'
     */
    public function app(): string;
}
