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

namespace Coretsia\Foundation\Serialization;

/**
 * Optional owner-supplied resource budget for json-like normalization.
 *
 * The baseline value model remains unchanged when no limits instance is
 * supplied. Owners that accept untrusted or potentially large structures may
 * provide a mandatory bounded policy through this immutable value object.
 * Aggregate string-byte accounting is optional and defaults to unbounded.
 */
final readonly class JsonLikeNormalizationLimits
{
    /**
     * @param int<1, max> $maxDepth
     * @param int<1, max> $maxNodes
     * @param int<1, max> $maxStringBytes
     * @param int<1, max>|null $maxTotalStringBytes
     */
    public function __construct(
        public int $maxDepth,
        public int $maxNodes,
        public int $maxStringBytes,
        public ?int $maxTotalStringBytes = null,
    ) {
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException('json-like-normalization-max-depth-invalid');
        }

        if ($maxNodes < 1) {
            throw new \InvalidArgumentException('json-like-normalization-max-nodes-invalid');
        }

        if ($maxStringBytes < 1) {
            throw new \InvalidArgumentException('json-like-normalization-max-string-bytes-invalid');
        }

        if ($maxTotalStringBytes !== null && $maxTotalStringBytes < 1) {
            throw new \InvalidArgumentException('json-like-normalization-max-total-string-bytes-invalid');
        }
    }
}
