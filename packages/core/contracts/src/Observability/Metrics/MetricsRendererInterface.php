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

namespace Coretsia\Contracts\Observability\Metrics;

/**
 * Vendor-neutral metrics renderer port.
 *
 * Rendering format is implementation-owned. This contract MUST NOT require
 * Prometheus classes, OpenTelemetry SDK classes, PSR-7 objects, or concrete
 * vendor renderers.
 */
interface MetricsRendererInterface
{
    /**
     * Returns the rendered metrics content type.
     *
     * The value is implementation-owned and MUST NOT require concrete vendor
     * classes. Example: "text/plain; version=0.0.4".
     *
     * @return non-empty-string
     */
    public function contentType(): string;

    /**
     * Renders implementation-owned metric state.
     *
     * The returned representation MUST NOT contain secrets, raw payloads, raw
     * SQL, tokens, cookies, private customer data, profile payloads, or other
     * unsafe diagnostics.
     */
    public function render(): string;
}
