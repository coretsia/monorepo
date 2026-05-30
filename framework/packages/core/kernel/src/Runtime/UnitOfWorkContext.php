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

namespace Coretsia\Kernel\Runtime;

use Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException;
use Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer;

/**
 * Canonical Kernel UnitOfWork context shape.
 *
 * UnitOfWorkContext represents safe format-neutral metadata known at the
 * beginning of a UnitOfWork.
 *
 * It intentionally stores only plain scalar/json-like data:
 *
 * - no transport objects;
 * - no PSR-7/15 objects;
 * - no service instances;
 * - no closures/resources;
 * - no float values in attributes;
 * - no raw payloads, secrets, tokens, cookies, session ids, raw SQL, stack
 *   traces, or PII in attributes.
 *
 * Exported shapes are normalized arrays. No object instance may cross the
 * Kernel hook/export boundary.
 */
final readonly class UnitOfWorkContext
{
    private const int DEFAULT_ATTRIBUTES_MAX_DEPTH = 10;
    private const int DEFAULT_ATTRIBUTES_MAX_KEYS = 200;

    private string $uowId;
    private string $type;
    private int $startedAt;
    private string $correlationId;

    /**
     * @var array<string, mixed>
     */
    private array $attributes;

    /**
     * @param array<string, mixed> $attributes
     * @param int<1, max> $attributesMaxDepth
     * @param int<1, max> $attributesMaxKeys
     */
    public function __construct(
        string $uowId,
        string $type,
        int $startedAt,
        string $correlationId,
        array $attributes = [],
        int $attributesMaxDepth = self::DEFAULT_ATTRIBUTES_MAX_DEPTH,
        int $attributesMaxKeys = self::DEFAULT_ATTRIBUTES_MAX_KEYS,
    ) {
        self::assertNonEmptySafeId(
            $uowId,
            'uowId',
            UnitOfWorkContextInvalidException::REASON_UOW_ID_INVALID,
        );
        self::assertType($type);
        self::assertStartedAt($startedAt);
        self::assertNonEmptySafeId(
            $correlationId,
            'correlationId',
            UnitOfWorkContextInvalidException::REASON_CORRELATION_ID_INVALID,
        );
        self::assertPositiveLimit(
            $attributesMaxDepth,
            'attributes',
            UnitOfWorkContextInvalidException::REASON_ATTRIBUTES_MAX_DEPTH_INVALID,
        );
        self::assertPositiveLimit(
            $attributesMaxKeys,
            'attributes',
            UnitOfWorkContextInvalidException::REASON_ATTRIBUTES_MAX_KEYS_INVALID,
        );

        $this->uowId = $uowId;
        $this->type = $type;
        $this->startedAt = $startedAt;
        $this->correlationId = $correlationId;
        $this->attributes = JsonLikeShapeNormalizer::normalizeContextAttributes(
            attributes: $attributes,
            maxDepth: $attributesMaxDepth,
            maxKeys: $attributesMaxKeys,
        );
    }

    public function uowId(): string
    {
        return $this->uowId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function startedAt(): int
    {
        return $this->startedAt;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Returns normalized safe json-like UnitOfWork attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Exports the context as the canonical normalized hook/export shape.
     *
     * Top-level key order follows `docs/ssot/uow-shapes.md`:
     *
     * - attributes
     * - correlationId
     * - startedAt
     * - type
     * - uowId
     *
     * @return array{
     *     attributes: array<string, mixed>,
     *     correlationId: string,
     *     startedAt: int,
     *     type: string,
     *     uowId: string
     * }
     */
    public function toArray(): array
    {
        return [
            'attributes' => $this->attributes,
            'correlationId' => $this->correlationId,
            'startedAt' => $this->startedAt,
            'type' => $this->type,
            'uowId' => $this->uowId,
        ];
    }

    private static function assertNonEmptySafeId(
        string $value,
        string $path,
        string $reason,
    ): void {
        if ($value === '') {
            throw UnitOfWorkContextInvalidException::atPath($path, $reason);
        }

        if (\trim($value) !== $value || \preg_match('/\s/u', $value) === 1) {
            throw UnitOfWorkContextInvalidException::atPath($path, $reason);
        }

        if (!self::isSafeSingleLineString($value)) {
            throw UnitOfWorkContextInvalidException::atPath($path, $reason);
        }
    }

    private static function assertType(string $type): void
    {
        if (!UnitOfWorkType::isValid($type)) {
            throw UnitOfWorkContextInvalidException::atPath(
                'type',
                UnitOfWorkContextInvalidException::REASON_TYPE_INVALID,
            );
        }
    }

    private static function assertStartedAt(int $startedAt): void
    {
        if ($startedAt < 0) {
            throw UnitOfWorkContextInvalidException::atPath(
                'startedAt',
                UnitOfWorkContextInvalidException::REASON_STARTED_AT_INVALID,
            );
        }
    }

    private static function assertPositiveLimit(
        int $value,
        string $path,
        string $reason,
    ): void {
        if ($value < 1) {
            throw UnitOfWorkContextInvalidException::atPath($path, $reason);
        }
    }

    private static function isSafeSingleLineString(string $value): bool
    {
        return self::isSafeString($value)
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n");
    }

    private static function isSafeString(string $value): bool
    {
        return \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
