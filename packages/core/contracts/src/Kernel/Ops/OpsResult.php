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

namespace Coretsia\Contracts\Kernel\Ops;

/**
 * Immutable transport-neutral result of one Kernel operation.
 *
 * The result contains only safe operation metadata and producer-normalized
 * json-like data. It MUST NOT contain raw config or env values, Composer
 * metadata, provider instances, artifact payloads, Throwable objects or
 * messages, stack traces, credentials, tokens, arbitrary transport payloads,
 * or absolute filesystem paths.
 *
 * The producing Kernel boundary remains responsible for operation-specific
 * result shape and semantic safety. This contracts model validates the common
 * outcome invariants and verifies that data is already deterministic json-like
 * input; it does not perform a second recursive normalization pass.
 */
final readonly class OpsResult
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @var array<string, true>
     */
    private const array OUTCOMES = [
        'handled_error' => true,
        'success' => true,
    ];

    private const string SAFE_TOKEN_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9_.:-]*\z/';

    private string $operation;

    /**
     * @var non-empty-string|null
     */
    private ?string $appTarget;

    /**
     * @var non-empty-string|null
     */
    private ?string $preset;

    /**
     * @var 'success'|'handled_error'
     */
    private string $outcome;

    /**
     * @var non-empty-string|null
     */
    private ?string $reason;

    /**
     * @var array<int|string,mixed>
     */
    private array $data;

    /**
     * @param non-empty-string $operation Canonical safe operation id.
     * @param non-empty-string|null $appTarget Canonical accepted app target.
     * @param non-empty-string|null $preset Validated effective preset when safely available.
     * @param 'success'|'handled_error' $outcome
     * @param non-empty-string|null $reason Stable safe handled-error reason token.
     * @param array<int|string,mixed> $data Producer-normalized deterministic json-like data.
     */
    public function __construct(
        string $operation,
        ?string $appTarget,
        ?string $preset,
        string $outcome,
        ?string $reason,
        array $data = [],
    ) {
        $this->operation = self::assertRequiredSafeToken($operation, 'operation');
        $this->appTarget = self::assertOptionalSafeToken($appTarget, 'appTarget');
        $this->preset = self::assertOptionalSafeToken($preset, 'preset');
        $this->outcome = self::assertOutcome($outcome);
        $this->reason = self::assertOptionalSafeToken($reason, 'reason');

        self::assertOutcomeInvariants(
            outcome: $this->outcome,
            appTarget: $this->appTarget,
            preset: $this->preset,
            reason: $this->reason,
        );

        self::assertJsonLikeValue($data, 'data');

        $this->data = $data;
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * @return non-empty-string
     */
    public function operation(): string
    {
        return $this->operation;
    }

    /**
     * Canonical accepted application target.
     *
     * Null is allowed only for a handled error produced before canonical
     * app-target validation completes.
     *
     * @return non-empty-string|null
     */
    public function appTarget(): ?string
    {
        return $this->appTarget;
    }

    /**
     * Validated effective preset when safely available at the operation boundary.
     *
     * Successful results always contain a preset. A handled error may contain a
     * preset only when the producing Kernel operation has already established
     * safe preset eligibility.
     *
     * @return non-empty-string|null
     */
    public function preset(): ?string
    {
        return $this->preset;
    }

    /**
     * @return 'success'|'handled_error'
     */
    public function outcome(): string
    {
        return $this->outcome;
    }

    /**
     * Stable safe reason token for handled errors.
     *
     * Successful results always return null.
     *
     * @return non-empty-string|null
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * Returns operation-specific deterministic json-like result data.
     *
     * Maps are already recursively sorted by byte-order string comparison and
     * lists preserve producer order. Floats, objects, resources, and other
     * non-json-like runtime values are forbidden.
     *
     * @return array<int|string,mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return non-empty-string
     */
    private static function assertRequiredSafeToken(string $value, string $field): string
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Kernel Ops result ' . $field . ' must be non-empty.');
        }

        if (!self::isSafeToken($value)) {
            throw new \InvalidArgumentException('Invalid Kernel Ops result ' . $field . '.');
        }

        return $value;
    }

    /**
     * @return non-empty-string|null
     */
    private static function assertOptionalSafeToken(?string $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::assertRequiredSafeToken($value, $field);
    }

    /**
     * @return 'success'|'handled_error'
     */
    private static function assertOutcome(string $outcome): string
    {
        if (!isset(self::OUTCOMES[$outcome])) {
            throw new \InvalidArgumentException('Invalid Kernel Ops result outcome.');
        }

        /** @var 'success'|'handled_error' $outcome */
        return $outcome;
    }

    private static function assertOutcomeInvariants(
        string $outcome,
        ?string $appTarget,
        ?string $preset,
        ?string $reason,
    ): void {
        if ($outcome === 'success') {
            if ($appTarget === null) {
                throw new \InvalidArgumentException('Successful Kernel Ops result requires appTarget.');
            }

            if ($preset === null) {
                throw new \InvalidArgumentException('Successful Kernel Ops result requires preset.');
            }

            if ($reason !== null) {
                throw new \InvalidArgumentException('Successful Kernel Ops result must not contain reason.');
            }

            return;
        }

        if ($reason === null) {
            throw new \InvalidArgumentException('Handled Kernel Ops result requires reason.');
        }

        if ($appTarget === null && $preset !== null) {
            throw new \InvalidArgumentException('Kernel Ops result preset requires appTarget.');
        }
    }

    private static function isSafeToken(string $value): bool
    {
        return preg_match(self::SAFE_TOKEN_PATTERN, $value) === 1;
    }

    private static function assertJsonLikeValue(mixed $value, string $path): void
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return;
        }

        if (\is_string($value)) {
            if (!self::isSafeString($value)) {
                throw new \InvalidArgumentException('Invalid Kernel Ops result string at ' . $path . '.');
            }

            if (self::looksLikeAbsolutePath($value)) {
                throw new \InvalidArgumentException('Kernel Ops result must not contain absolute filesystem paths.');
            }

            return;
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException('Invalid Kernel Ops result value at ' . $path . '.');
        }

        if (\array_is_list($value)) {
            foreach ($value as $index => $item) {
                self::assertJsonLikeValue($item, $path . '[' . $index . ']');
            }

            return;
        }

        $previousKey = null;

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException('Kernel Ops result map keys must be strings.');
            }

            if (!self::isSafeString($key)) {
                throw new \InvalidArgumentException('Invalid Kernel Ops result map key at ' . $path . '.');
            }

            if ($previousKey !== null && \strcmp($previousKey, $key) >= 0) {
                throw new \InvalidArgumentException('Kernel Ops result maps must be strcmp-sorted.');
            }

            self::assertJsonLikeValue($item, $path . '[<key>]');

            $previousKey = $key;
        }
    }

    private static function isSafeString(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return str_starts_with($value, '/')
            || str_starts_with($value, '\\')
            || preg_match('/\A[A-Za-z]:[\\\\\/]/', $value) === 1;
    }
}
