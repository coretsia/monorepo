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
 * Vendor-neutral tracing sampler port.
 *
 * Sampling decisions MUST be deterministic for the same declared inputs unless
 * an implementation explicitly documents probabilistic sampling policy outside
 * contracts.
 */
interface SamplerInterface
{
    /**
     * Decides whether a span or trace should be sampled.
     *
     * Attributes MUST be a safe json-like map:
     * null, bool, int, string, list, or string-keyed map. Floats are forbidden.
     *
     * Sampling inputs MUST NOT include raw request bodies, response bodies,
     * tokens, credentials, cookies, raw SQL, profile payloads, or private
     * customer data.
     *
     * @param array<string,mixed> $attributes
     */
    public function shouldSample(string $spanName, array $attributes = []): SamplingDecision;
}
