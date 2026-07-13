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
 * It accepts only Kernel-owned UnitOfWorkContext and UnitOfWorkResult
 * instances. Raw arrays are not accepted because they could bypass the
 * UoW-specific root, unsafe-key, limit, and exception-mapping policy enforced
 * by JsonLikeShapeNormalizer.
 *
 * JsonLikeShapeNormalizer validates and normalizes UoW-owned nested fields.
 * This class performs only the final whole-export baseline normalization
 * before the payload crosses the hook boundary.
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
     * Raw arrays are intentionally not accepted so callers cannot bypass the
     * UoW-specific validation owned by UnitOfWorkContext and
     * JsonLikeShapeNormalizer.
     *
     * @return array<string, mixed>
     */
    public static function normalizeContext(UnitOfWorkContext $context): array
    {
        return self::normalizeMapPayload(
            $context->toArray(),
            'context',
        );
    }

    /**
     * Normalizes a UnitOfWork result export for after-uow hooks.
     *
     * Raw arrays are intentionally not accepted so callers cannot bypass the
     * UoW-specific validation owned by UnitOfWorkResult and
     * JsonLikeShapeNormalizer.
     *
     * @return array<string, mixed>
     */
    public static function normalizeResult(UnitOfWorkResult $result): array
    {
        return self::normalizeMapPayload(
            $result->toArray(),
            'result',
        );
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
