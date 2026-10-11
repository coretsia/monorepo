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

namespace Coretsia\Platform\Redaction\Redaction;

use Coretsia\Contracts\Security\Exception\RedactionException;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonEncoder;

/**
 * Config-free deterministic redaction for sensitive strings and json-like values.
 *
 * Foundation owns structural validation and resource budgets. This class owns
 * only redaction semantics and never treats redaction as sink authorization.
 * All unexpected failures become safe, reason-only redaction exceptions.
 */
final class DefaultSensitiveDataRedactor implements SensitiveDataRedactorInterface
{
    private const int MAX_DEPTH = 32;
    private const int MAX_NODES = 10_000;
    private const int MAX_STRING_BYTES = 65_536;
    private const int MAX_TOTAL_STRING_BYTES = 1_048_576;

    public function __construct(
        private readonly SensitiveKeyClassifier $keyClassifier,
        private readonly SensitiveValueClassifier $valueClassifier,
        private readonly StableRedactionHasher $hasher,
    ) {
    }

    public function redactValue(
        string $value,
        RedactionKind $kind,
        RedactionContext $context,
    ): RedactedValue {
        try {
            try {
                $normalized = JsonLikeNormalizer::normalize(
                    $value,
                    limits: self::limits(),
                );
            } catch (JsonLikeNormalizationException $exception) {
                throw self::inputNormalizationFailure($exception);
            }

            if (!\is_string($normalized) || $normalized !== $value) {
                throw RedactionException::inputInvalid();
            }

            return $this->summarizeString($normalized, $kind, $context);
        } catch (RedactionException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw RedactionException::internalFailure();
        }
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    public function redactJsonLike(mixed $value, RedactionContext $context): mixed
    {
        try {
            $limits = self::limits();

            try {
                $normalized = JsonLikeNormalizer::normalize($value, limits: $limits);
            } catch (JsonLikeNormalizationException $exception) {
                throw self::inputNormalizationFailure($exception);
            }

            $result = $this->redactNormalized($normalized, $context);

            try {
                return JsonLikeNormalizer::normalize($result, limits: $limits);
            } catch (JsonLikeNormalizationException) {
                throw RedactionException::outputInvalid();
            }
        } catch (RedactionException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw RedactionException::internalFailure();
        }
    }

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    private function redactNormalized(mixed $value, RedactionContext $context): mixed
    {
        if (\is_string($value)) {
            $kind = $this->valueClassifier->classify($value);

            return $kind === null
                ? $value
                : $this->summarizeString($value, $kind, $context)->toArray();
        }

        if (!\is_array($value)) {
            return $value;
        }

        if (\array_is_list($value)) {
            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = $this->redactNormalized($item, $context);
            }

            return $normalized;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (\preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
                throw RedactionException::inputInvalid();
            }

            $keyKind = $this->keyClassifier->classify($key);

            if ($keyKind !== null) {
                $normalized[$key] = $this->summarizeBranch($item, $keyKind, $context);
                continue;
            }

            if ($this->valueClassifier->classify($key) !== null) {
                throw RedactionException::sensitiveMapKey();
            }

            $normalized[$key] = $this->redactNormalized($item, $context);
        }

        return $normalized;
    }

    private function summarizeString(
        string $value,
        RedactionKind $kind,
        RedactionContext $context,
    ): RedactedValue {
        $mode = $context->mode();

        return match ($mode) {
            RedactionMode::Placeholder => new RedactedValue($kind, $mode, null, null),
            RedactionMode::Length => new RedactedValue($kind, $mode, \strlen($value), null),
            RedactionMode::Hash => new RedactedValue(
                $kind,
                $mode,
                null,
                $this->hasher->hashString($value, $kind, $context),
            ),
            RedactionMode::HashAndLength => new RedactedValue(
                $kind,
                $mode,
                \strlen($value),
                $this->hasher->hashString($value, $kind, $context),
            ),
        };
    }

    /**
     * @return array{
     *     hash: ?string,
     *     kind: string,
     *     length: ?int,
     *     mode: string,
     *     redacted: true,
     *     schemaVersion: 1,
     * }
     */
    private function summarizeBranch(
        mixed $value,
        RedactionKind $kind,
        RedactionContext $context,
    ): array {
        $mode = $context->mode();

        if ($mode === RedactionMode::Placeholder) {
            return new RedactedValue($kind, $mode, null, null)->toArray();
        }

        if (\is_string($value)) {
            return $this->summarizeString($value, $kind, $context)->toArray();
        }

        try {
            $bytes = \substr(StableJsonEncoder::encodeStable($value), 0, -1);
        } catch (\Throwable) {
            throw RedactionException::inputInvalid();
        }

        $length = $mode === RedactionMode::Length || $mode === RedactionMode::HashAndLength
            ? \strlen($bytes)
            : null;
        $hash = $mode === RedactionMode::Hash || $mode === RedactionMode::HashAndLength
            ? $this->hasher->hashJsonLike($bytes, $kind, $context)
            : null;

        return new RedactedValue($kind, $mode, $length, $hash)->toArray();
    }

    private static function limits(): JsonLikeNormalizationLimits
    {
        return new JsonLikeNormalizationLimits(
            self::MAX_DEPTH,
            self::MAX_NODES,
            self::MAX_STRING_BYTES,
            self::MAX_TOTAL_STRING_BYTES,
        );
    }

    private static function inputNormalizationFailure(
        JsonLikeNormalizationException $exception,
    ): RedactionException {
        return match ($exception->reason()) {
            JsonLikeNormalizationException::REASON_MAX_DEPTH_EXCEEDED,
            JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED,
            JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
            JsonLikeNormalizationException::REASON_TOTAL_STRING_BYTES_EXCEEDED => RedactionException::inputLimitExceeded(),
            default => RedactionException::inputInvalid(),
        };
    }
}
