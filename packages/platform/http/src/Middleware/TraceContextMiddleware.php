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

namespace Coretsia\Platform\Http\Middleware;

use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Bounded PSR-7 carrier adapter for canonical W3C context extraction.
 *
 * W3C grammar and sampling semantics belong exclusively to the propagation
 * port. The accepted three-field context is request-local and never changes
 * Kernel correlation identifiers or UnitOfWork state.
 *
 * @internal
 */
final readonly class TraceContextMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE = 'coretsia.http.trace_context';

    private const int MAX_TRACEPARENT_BYTES = 512;
    private const int MAX_TRACESTATE_BYTES = 512;

    public function __construct(
        private ContextPropagationInterface $propagation,
        private bool $enabled,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $request = $request->withoutAttribute(self::ATTRIBUTE);
        $parent = $request->getHeader('traceparent');
        if (\count($parent) !== 1 || !\is_string($parent[0]) || $parent[0] === ''
            || \strlen($parent[0]) > self::MAX_TRACEPARENT_BYTES
        ) {
            return $handler->handle($request);
        }

        $carrier = ['traceparent' => $parent];
        $tracestate = $request->getHeader('tracestate');
        if ($tracestate !== []) {
            if (\count($tracestate) > 16) {
                return $handler->handle($request);
            }

            $bytes = 0;
            foreach ($tracestate as $value) {
                if (!\is_string($value)) {
                    return $handler->handle($request);
                }

                $bytes += \strlen($value);
            }

            if ($bytes > self::MAX_TRACESTATE_BYTES) {
                return $handler->handle($request);
            }

            $carrier['tracestate'] = $tracestate;
        }

        try {
            $context = $this->propagation->extract($carrier);
        } catch (\Throwable) {
            return $handler->handle($request);
        }

        if (self::validContext($context)) {
            $request = $request->withAttribute(self::ATTRIBUTE, $context);
        }

        return $handler->handle($request);
    }

    /**
     * Validates the returned port shape without interpreting traceparent.
     *
     * @param array<string, mixed> $context
     */
    private static function validContext(array $context): bool
    {
        return \count($context) === 3
            && \is_string($context['trace_id'] ?? null)
            && \preg_match('/\A[0-9a-f]{32}\z/D', $context['trace_id']) === 1
            && $context['trace_id'] !== \str_repeat('0', 32)
            && \is_string($context['parent_id'] ?? null)
            && \preg_match('/\A[0-9a-f]{16}\z/D', $context['parent_id']) === 1
            && $context['parent_id'] !== \str_repeat('0', 16)
            && \is_string($context['trace_flags'] ?? null)
            && \preg_match('/\A[0-9a-f]{2}\z/D', $context['trace_flags']) === 1
            && (\hexdec($context['trace_flags']) & ~0x03) === 0;
    }
}
