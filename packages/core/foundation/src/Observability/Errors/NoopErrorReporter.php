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

namespace Coretsia\Foundation\Observability\Errors;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;

/**
 * No-op error reporter implementation for the Foundation baseline.
 *
 * This implementation does not inspect, store, enrich, log, print, or emit
 * descriptors, contexts, throwable payloads, raw SQL, tokens, or private data.
 */
final class NoopErrorReporter implements ErrorReporterPortInterface
{
    public function report(
        ErrorDescriptor $descriptor,
        ?ErrorHandlingContext $context = null,
    ): void {
        unset($descriptor, $context);
    }
}
