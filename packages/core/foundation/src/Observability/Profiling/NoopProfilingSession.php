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

namespace Coretsia\Foundation\Observability\Profiling;

use Coretsia\Contracts\Observability\Profiling\ProfileArtifact;
use Coretsia\Contracts\Observability\Profiling\ProfilingSessionInterface;

/**
 * No-op profiling session handle for the Foundation observability baseline.
 *
 * This implementation captures no profile artifact, stores no payload, and
 * emits no diagnostics. Repeated `stop()` calls are noop-safe.
 */
final class NoopProfilingSession implements ProfilingSessionInterface
{
    public function stop(): ?ProfileArtifact
    {
        return null;
    }
}
