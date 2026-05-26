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

namespace Coretsia\Foundation\Observability;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Foundation\Context\ContextKeys;

/**
 * Foundation correlation id provider.
 *
 * This implementation reads the current correlation id from the canonical
 * runtime context accessor. It intentionally does not generate, normalize,
 * store, propagate, log, or emit correlation ids.
 *
 * Correlation id generation belongs to the unit-of-work owner. Transport
 * extraction/injection policy belongs to platform packages.
 *
 * Malformed or unsafe context values resolve to null without being logged,
 * traced, emitted, normalized, or exposed through diagnostics.
 */
final readonly class CorrelationIdProvider implements CorrelationIdProviderInterface
{
    private const string CANONICAL_CORRELATION_ID_PATTERN = '/\A[0-9A-HJKMNP-TV-Z]{26}\z/';

    public function __construct(
        private ContextAccessorInterface $context,
    ) {
    }

    public function correlationId(): ?string
    {
        if (!$this->context->has(ContextKeys::CORRELATION_ID)) {
            return null;
        }

        $correlationId = $this->context->get(ContextKeys::CORRELATION_ID);

        if (!\is_string($correlationId)) {
            return null;
        }

        if (\preg_match(self::CANONICAL_CORRELATION_ID_PATTERN, $correlationId) !== 1) {
            return null;
        }

        return $correlationId;
    }
}
