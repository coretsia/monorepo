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

namespace Coretsia\Platform\Routing\Exception;

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\Exception\PhpArraySerializationException;

/**
 * Deterministic Routing artifact read, decoding, and hydration failure.
 *
 * The public message contains only the fixed Routing error code and one
 * allowlisted reason token. Artifact bytes, paths, rejected descriptors,
 * filesystem diagnostics, and previous throwable messages are never retained.
 *
 * @internal
 */
final class RoutesArtifactInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ROUTES_ARTIFACT_INVALID';

    public const string REASON_INVALID = 'routes-artifact-invalid';
    public const string REASON_MISSING = 'routes-artifact-missing';
    public const string REASON_UNREADABLE = 'routes-artifact-unreadable';
    public const string REASON_READ_FAILED = 'routes-artifact-read-failed';
    public const string REASON_UNSAFE = 'routes-artifact-unsafe';
    public const string REASON_OVERSIZED = 'routes-artifact-oversized';
    public const string REASON_MALFORMED = 'routes-artifact-malformed';
    public const string REASON_INCOMPATIBLE = 'routes-artifact-incompatible';
    public const string REASON_HEADER_INVALID = 'routes-artifact-header-invalid';
    public const string REASON_PAYLOAD_INVALID = 'routes-artifact-payload-invalid';
    public const string REASON_FINGERPRINT_INVALID = 'routes-artifact-fingerprint-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_MISSING => true,
        self::REASON_UNREADABLE => true,
        self::REASON_READ_FAILED => true,
        self::REASON_UNSAFE => true,
        self::REASON_OVERSIZED => true,
        self::REASON_MALFORMED => true,
        self::REASON_INCOMPATIBLE => true,
        self::REASON_HEADER_INVALID => true,
        self::REASON_PAYLOAD_INVALID => true,
        self::REASON_FINGERPRINT_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('routes-artifact-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE . ': ' . $reason);
    }

    public static function withReason(string $reason): self
    {
        return new self($reason);
    }

    /**
     * Translate shared decoder failures without retaining the original throwable.
     */
    public static function fromDecodingFailure(\Throwable $failure): self
    {
        return new self(
            self::isResourceLimitFailure($failure)
                ? self::REASON_OVERSIZED
                : self::REASON_MALFORMED,
        );
    }

    /**
     * Translate route descriptor hydration failures without retaining their data.
     */
    public static function fromHydrationFailure(\Throwable $failure): self
    {
        if (self::isResourceLimitFailure($failure)) {
            return new self(self::REASON_OVERSIZED);
        }

        if ($failure instanceof ReservedRouteViolationException) {
            return new self(self::REASON_UNSAFE);
        }

        return new self(self::REASON_PAYLOAD_INVALID);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    private static function isResourceLimitFailure(\Throwable $failure): bool
    {
        if ($failure instanceof PhpArraySerializationException) {
            return \in_array($failure->reason(), [
                PhpArraySerializationException::REASON_MAX_DEPTH_EXCEEDED,
                PhpArraySerializationException::REASON_MAX_NODES_EXCEEDED,
                PhpArraySerializationException::REASON_STRING_BYTES_EXCEEDED,
                PhpArraySerializationException::REASON_TOTAL_STRING_BYTES_EXCEEDED,
            ], true);
        }

        if ($failure instanceof JsonLikeNormalizationException) {
            return \in_array($failure->reason(), [
                JsonLikeNormalizationException::REASON_MAX_DEPTH_EXCEEDED,
                JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED,
                JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
                JsonLikeNormalizationException::REASON_TOTAL_STRING_BYTES_EXCEEDED,
            ], true);
        }

        return $failure instanceof RouteDefinitionInvalidException
            && $failure->reason() === RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED;
    }
}
