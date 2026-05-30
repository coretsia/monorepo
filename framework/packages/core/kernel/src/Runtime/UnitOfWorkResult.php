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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException;
use Coretsia\Kernel\Runtime\Internal\JsonLikeShapeNormalizer;

/**
 * Canonical Kernel UnitOfWork result shape.
 *
 * UnitOfWorkResult represents safe format-neutral completion metadata known at
 * the end of a UnitOfWork.
 *
 * It intentionally stores only plain scalar/json-like exported data:
 *
 * - no transport objects;
 * - no PSR-7/15 objects;
 * - no service instances;
 * - no closures/resources;
 * - no float values in extensions;
 * - no raw payloads, secrets, tokens, cookies, session ids, raw SQL, stack
 *   traces, or PII in extensions.
 *
 * `error` may be held internally as ErrorDescriptor, but exported shapes always
 * carry a normalized json-like error map. No object instance may cross the
 * Kernel hook/export boundary.
 */
final readonly class UnitOfWorkResult
{
    private string $uowId;
    private string $type;
    private string $correlationId;
    private int $startedAt;
    private int $finishedAt;
    private int $durationMs;
    private string $outcome;
    private ?ErrorDescriptor $error;

    /**
     * @var array<string, mixed>
     */
    private array $extensions;

    /**
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        string $uowId,
        string $type,
        string $correlationId,
        int $startedAt,
        int $finishedAt,
        int $durationMs,
        string $outcome,
        ?ErrorDescriptor $error = null,
        array $extensions = [],
    ) {
        self::assertNonEmptySafeId(
            $uowId,
            'uowId',
            UnitOfWorkResultInvalidException::REASON_UOW_ID_INVALID,
        );
        self::assertType($type);
        self::assertNonEmptySafeId(
            $correlationId,
            'correlationId',
            UnitOfWorkResultInvalidException::REASON_CORRELATION_ID_INVALID,
        );
        self::assertTimestamp(
            $startedAt,
            'startedAt',
            UnitOfWorkResultInvalidException::REASON_STARTED_AT_INVALID,
        );
        self::assertTimestamp(
            $finishedAt,
            'finishedAt',
            UnitOfWorkResultInvalidException::REASON_FINISHED_AT_INVALID,
        );
        self::assertDuration($durationMs);
        self::assertOutcome($outcome);

        $this->uowId = $uowId;
        $this->type = $type;
        $this->correlationId = $correlationId;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
        $this->durationMs = $durationMs;
        $this->outcome = $outcome;
        $this->error = $error;
        $this->extensions = JsonLikeShapeNormalizer::normalizeResultExtensions($extensions);
    }

    /**
     * Creates a result from the originating UnitOfWork context.
     *
     * This helper preserves the context-owned identity fields exactly:
     *
     * - uowId
     * - type
     * - correlationId
     * - startedAt
     *
     * @param array<string, mixed> $extensions
     */
    public static function fromContext(
        UnitOfWorkContext $context,
        int $finishedAt,
        int $durationMs,
        string $outcome,
        ?ErrorDescriptor $error = null,
        array $extensions = [],
    ): self {
        return new self(
            uowId: $context->uowId(),
            type: $context->type(),
            correlationId: $context->correlationId(),
            startedAt: $context->startedAt(),
            finishedAt: $finishedAt,
            durationMs: $durationMs,
            outcome: $outcome,
            error: $error,
            extensions: $extensions,
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

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function startedAt(): int
    {
        return $this->startedAt;
    }

    public function finishedAt(): int
    {
        return $this->finishedAt;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }

    public function outcome(): string
    {
        return $this->outcome;
    }

    /**
     * Returns the internal error descriptor.
     *
     * This object MUST NOT be passed to hooks, adapters, or artifacts.
     * Use toArray() for boundary/export representation.
     */
    public function error(): ?ErrorDescriptor
    {
        return $this->error;
    }

    /**
     * Returns normalized safe json-like UnitOfWork result extensions.
     *
     * @return array<string, mixed>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /**
     * Exports the result as the canonical normalized hook/export shape.
     *
     * Top-level key order follows `docs/ssot/uow-shapes.md`.
     *
     * When `error` is present:
     *
     * - correlationId
     * - durationMs
     * - error
     * - extensions
     * - finishedAt
     * - outcome
     * - startedAt
     * - type
     * - uowId
     *
     * When `error` is absent, the `error` key is omitted and the remaining
     * keys keep the same deterministic byte-order position.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'correlationId' => $this->correlationId,
            'durationMs' => $this->durationMs,
        ];

        if ($this->error !== null) {
            $result['error'] = JsonLikeShapeNormalizer::normalizeExportedErrorMap(
                $this->error->toArray(),
            );
        }

        $result['extensions'] = $this->extensions;
        $result['finishedAt'] = $this->finishedAt;
        $result['outcome'] = $this->outcome;
        $result['startedAt'] = $this->startedAt;
        $result['type'] = $this->type;
        $result['uowId'] = $this->uowId;

        return $result;
    }

    private static function assertNonEmptySafeId(
        string $value,
        string $path,
        string $reason,
    ): void {
        if ($value === '') {
            throw self::invalid($path, $reason);
        }

        if (\trim($value) !== $value || \preg_match('/\s/u', $value) === 1) {
            throw self::invalid($path, $reason);
        }

        if (!self::isSafeSingleLineString($value)) {
            throw self::invalid($path, $reason);
        }
    }

    private static function assertType(string $type): void
    {
        if (!UnitOfWorkType::isValid($type)) {
            throw self::invalid(
                'type',
                UnitOfWorkResultInvalidException::REASON_TYPE_INVALID,
            );
        }
    }

    private static function assertTimestamp(
        int $timestamp,
        string $path,
        string $reason,
    ): void {
        if ($timestamp < 0) {
            throw self::invalid($path, $reason);
        }
    }

    private static function assertDuration(int $durationMs): void
    {
        if ($durationMs < 0) {
            throw self::invalid(
                'durationMs',
                UnitOfWorkResultInvalidException::REASON_DURATION_MS_INVALID,
            );
        }
    }

    private static function assertOutcome(string $outcome): void
    {
        if (!Outcome::isValid($outcome)) {
            throw self::invalid(
                'outcome',
                UnitOfWorkResultInvalidException::REASON_OUTCOME_INVALID,
            );
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

    private static function invalid(string $path, string $reason): UnitOfWorkResultInvalidException
    {
        return UnitOfWorkResultInvalidException::atPath($path, $reason);
    }
}
