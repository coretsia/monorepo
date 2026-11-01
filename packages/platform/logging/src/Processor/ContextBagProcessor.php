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

namespace Coretsia\Platform\Logging\Processor;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;

/**
 * Stateless, read-only enrichment from the current canonical UoW context.
 *
 * The caller supplies already admitted context. This processor reads only
 * canonical, bounded correlation/UoW identifiers and an optional route template.
 * It never copies ContextStore state, reads raw transport data, or persists
 * values between log invocations. The logging sink policy remains the final
 * authority on admitted context fields.
 */
final readonly class ContextBagProcessor
{
    public function __construct(
        private ContextAccessorInterface $context,
        private CorrelationIdProviderInterface $correlations,
    ) {
    }

    /**
     * @param array<string, mixed>|null $admittedContext
     * @return array{
     *     correlation_id: ?string,
     *     uow_id: ?string,
     *     uow_type: ?string,
     *     context: array<string, mixed>|null
     * }
     */
    public function process(?array $admittedContext): array
    {
        $context = $admittedContext ?? [];
        $pathTemplate = $this->safeContextValue(ContextKeys::PATH_TEMPLATE);

        if (self::isRouteTemplate($pathTemplate)) {
            // Only a bounded structural route template may enrich context.
            $context['path_template'] = $pathTemplate;
        }

        if ($context !== []) {
            \ksort($context, \SORT_STRING);
        }

        $correlationId = null;

        try {
            $correlationId = $this->correlations->correlationId();
        } catch (\Throwable) {
            // Missing, invalid, or throwing canonical context is not exported.
        }

        $uowId = $this->safeContextValue(ContextKeys::UOW_ID);
        $uowType = $this->safeContextValue(ContextKeys::UOW_TYPE);

        return [
            'correlation_id' => $correlationId,
            'uow_id' => self::isUowId($uowId) ? $uowId : null,
            'uow_type' => \in_array($uowType, ['http', 'cli', 'queue', 'scheduler'], true) ? $uowType : null,
            'context' => $context === [] ? null : $context,
        ];
    }

    private function safeContextValue(string $key): mixed
    {
        try {
            return $this->context->has($key) ? $this->context->get($key) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function isUowId(mixed $value): bool
    {
        return \is_string($value)
            && \strlen($value) <= 128
            && \preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/', $value) === 1;
    }

    private static function isRouteTemplate(mixed $value): bool
    {
        return \is_string($value)
            && \strlen($value) <= 256
            && ($value === '/' || \preg_match(
                '/\A\/(?:[a-z_-][a-z0-9_-]*|\{[A-Za-z_][A-Za-z0-9_]*})(?:\/(?:[a-z_-][a-z0-9_-]*|\{[A-Za-z_][A-Za-z0-9_]*}))*\z/',
                $value,
            ) === 1);
    }
}
