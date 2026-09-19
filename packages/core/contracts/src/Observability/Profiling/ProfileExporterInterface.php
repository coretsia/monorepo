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
 * Vendor-neutral profile artifact exporter port.
 *
 * Exporter implementations own concrete storage, transport, backend, and
 * rendering behavior. This contract MUST NOT require Blackfire, Xdebug,
 * Tideways, OpenTelemetry SDK, PSR-7, concrete filesystem, or concrete network
 * client types.
 */
interface ProfileExporterInterface
{
    /**
     * Returns exporter stable name.
     *
     * The name MUST be safe, deterministic, and suitable for diagnostics.
     *
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * Exports a profile artifact to an implementation-owned sink.
     *
     * Implementations MUST NOT expose raw profile payloads in logs, metrics,
     * spans, error descriptors, health output, or unsafe diagnostics.
     *
     * Safe diagnostics MAY expose only safe derivations such as hash(value) or
     * len(value), never the raw payload contents.
     */
    public function export(ProfileArtifact $artifact): void;
}
