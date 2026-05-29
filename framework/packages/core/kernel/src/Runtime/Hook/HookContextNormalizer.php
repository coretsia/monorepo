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

namespace Coretsia\Kernel\Runtime\Hook;

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\UnitOfWorkContext;
use Coretsia\Kernel\Runtime\UnitOfWorkResult;

/**
 * Internal Kernel hook payload normalizer.
 *
 * This class converts Kernel-owned UnitOfWork export objects into normalized
 * json-like hook payload arrays.
 *
 * It intentionally delegates baseline json-like validation and deterministic
 * map sorting to Foundation JsonLikeNormalizer. Kernel must not define a
 * second json-like policy.
 *
 * Known internal Kernel result objects are normalized before generic object
 * rejection. In particular, UnitOfWorkResult may hold an internal
 * ErrorDescriptor object, but the exported hook payload always contains only a
 * normalized json-like error map.
 *
 * This class is stateless by design. It must not be registered as a DI service
 * in this epic and must not keep mutable runtime state, caches, buffers, or
 * request/unit-of-work-local data.
 *
 * @internal Kernel-owned hook payload normalization primitive.
 */
final class HookContextNormalizer
{
    private function __construct()
    {
    }

    /**
     * Normalizes a UnitOfWork context export for before-uow hooks.
     *
     * @param array<string, mixed>|UnitOfWorkContext $context
     *
     * @return array<string, mixed>
     */
    public static function normalizeContext(UnitOfWorkContext|array $context): array
    {
        $payload = $context instanceof UnitOfWorkContext
            ? $context->toArray()
            : $context;

        return self::normalizeMapPayload($payload, 'context');
    }

    /**
     * Normalizes a UnitOfWork result export for after-uow hooks.
     *
     * @param array<string, mixed>|UnitOfWorkResult $result
     *
     * @return array<string, mixed>
     */
    public static function normalizeResult(UnitOfWorkResult|array $result): array
    {
        $payload = $result instanceof UnitOfWorkResult
            ? $result->toArray()
            : $result;

        return self::normalizeMapPayload($payload, 'result');
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function normalizeMapPayload(array $payload, string $path): array
    {
        try {
            $normalized = JsonLikeNormalizer::normalize($payload, $path);
        } catch (JsonLikeNormalizationException $exception) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_HOOK_PAYLOAD_INVALID,
                $exception,
            );
        }

        if (!\is_array($normalized) || \array_is_list($normalized)) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_HOOK_PAYLOAD_INVALID,
            );
        }

        /**
         * JsonLikeNormalizer guarantees that non-list arrays are string-keyed
         * maps and recursively contain only json-like values.
         *
         * @var array<string, mixed> $normalized
         */
        return $normalized;
    }
}
