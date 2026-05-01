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

namespace Coretsia\Contracts\Observability;

/**
 * Format-neutral correlation id provider.
 *
 * The provider does not prescribe HTTP header names, request objects,
 * propagation format, storage mechanism, or generator algorithm.
 *
 * A correlation id returned by this port is safe for correlation use, but it
 * MUST NOT be treated as a metrics label unless a future SSoT explicitly
 * allows that label key.
 */
interface CorrelationIdProviderInterface
{
    /**
     * Returns the current safe correlation id, or null when no correlation id
     * is available in the current runtime boundary.
     */
    public function correlationId(): ?string;
}
