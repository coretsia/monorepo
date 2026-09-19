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

namespace Coretsia\Contracts\Observability\Tracing;

/**
 * Vendor-neutral span exporter port.
 *
 * Exporter implementations own backend-specific emission. This interface MUST
 * NOT expose OpenTelemetry SDK classes, vendor span classes, PSR-7 objects,
 * Prometheus objects, or concrete network clients.
 */
interface SpanExporterInterface
{
    /**
     * Exports completed or export-ready spans.
     *
     * Exporter implementations MUST apply redaction before emission and MUST
     * NOT export raw payload values, raw request data, raw SQL, headers,
     * cookies, tokens, credentials, or profile payloads.
     *
     * @param iterable<SpanInterface> $spans
     */
    public function export(iterable $spans): void;
}
