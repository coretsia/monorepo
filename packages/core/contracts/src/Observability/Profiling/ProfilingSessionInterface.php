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

namespace Coretsia\Contracts\Observability\Profiling;

/**
 * Profiling session handle returned by ProfilerPortInterface.
 *
 * Implementations may be no-op. Calling stop() more than once SHOULD be
 * noop-safe and MAY return null after the first completed stop.
 */
interface ProfilingSessionInterface
{
    /**
     * Stops profiling and returns the captured artifact when available.
     *
     * The returned ProfileArtifact payload is opaque. Consumers MUST NOT log,
     * print, trace, use as metric labels, embed into error descriptor
     * extensions, or expose raw payload contents through diagnostics.
     */
    public function stop(): ?ProfileArtifact;
}
