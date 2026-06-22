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

namespace Coretsia\Foundation\Context;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\Exception\ContextInvalidKeyException;
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;

/**
 * Always-on safe-write guard for ContextStore.
 *
 * The policy accepts only keys declared by the public ContextKeys contract
 * registry and json-like deterministic values:
 *
 * - null
 * - bool
 * - int
 * - string
 * - list<value>
 * - array<string,value>
 *
 * ContextStorePolicy owns context-specific write policy:
 *
 * - public ContextKeys contract allowlist;
 * - reserved @* key rejection;
 * - context-specific exception mapping.
 *
 * The context key vocabulary is owned by core/contracts. This class owns
 * write validation only.
 *
 * Baseline json-like value validation is delegated to JsonLikeNormalizer.
 *
 * Failure messages are deterministic and safe: they include only context keys,
 * safe path-to-value, and stable reason tokens.
 */
final class ContextStorePolicy
{
    public function assertCanWrite(string $key, mixed $value): void
    {
        $this->assertKey($key);
        $this->assertValue($value, $key);
    }

    public function assertKey(string $key): void
    {
        if ($key === '') {
            throw new ContextInvalidKeyException($key, 'context-key-empty');
        }

        if (\str_starts_with($key, '@')) {
            throw new ContextInvalidKeyException($key, 'context-key-reserved');
        }

        if (!ContextKeys::isKnown($key)) {
            throw new ContextInvalidKeyException($key, 'context-key-unknown');
        }
    }

    public function assertValue(mixed $value, string $path = 'value'): void
    {
        try {
            JsonLikeNormalizer::normalize($value, $path);
        } catch (JsonLikeNormalizationException $exception) {
            throw new ContextWriteForbiddenException(
                $exception->path(),
                self::mapJsonLikeReason($exception->reason()),
                $exception,
            );
        }
    }

    private static function mapJsonLikeReason(string $reason): string
    {
        return match ($reason) {
            JsonLikeNormalizationException::REASON_FLOAT_FORBIDDEN => 'context-write-forbidden-float',
            JsonLikeNormalizationException::REASON_CLOSURE_FORBIDDEN => 'context-write-forbidden-closure',
            JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN => 'context-write-forbidden-object',
            JsonLikeNormalizationException::REASON_RESOURCE_FORBIDDEN => 'context-write-forbidden-resource',
            JsonLikeNormalizationException::REASON_MAP_KEY_MUST_BE_STRING => 'context-write-forbidden-map-key',
            JsonLikeNormalizationException::REASON_TYPE_FORBIDDEN => 'context-write-forbidden-type',
            default => 'context-write-forbidden-type',
        };
    }
}
