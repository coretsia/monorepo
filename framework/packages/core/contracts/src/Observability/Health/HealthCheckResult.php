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

namespace Coretsia\Contracts\Observability\Health;

/**
 * Format-neutral health check result.
 *
 * This model contains only safe endpoint-ready metadata. It MUST NOT expose
 * credentials, raw SQL, raw request/response payloads, profile payloads,
 * private customer data, or absolute local paths.
 */
final readonly class HealthCheckResult
{
    public const int SCHEMA_VERSION = 1;

    private HealthStatus $status;
    private ?string $message;

    /**
     * @var array<string,mixed>
     */
    private array $details;

    /**
     * @param array<string,mixed> $details
     */
    public function __construct(
        HealthStatus $status,
        ?string $message = null,
        array $details = [],
    ) {
        $this->status = $status;
        $this->message = self::normalizeOptionalMessage($message);
        $this->details = self::normalizeJsonLikeMap($details, 'details');
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function status(): HealthStatus
    {
        return $this->status;
    }

    /**
     * @return non-empty-string|null
     */
    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * @return array<string,mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * @return array{
     *     details: array<string,mixed>,
     *     message: non-empty-string|null,
     *     schemaVersion: int,
     *     status: non-empty-string
     * }
     */
    public function toArray(): array
    {
        return [
            'details' => $this->details,
            'message' => $this->message,
            'schemaVersion' => self::SCHEMA_VERSION,
            'status' => $this->status->value,
        ];
    }

    private static function normalizeOptionalMessage(?string $message): ?string
    {
        if ($message === null) {
            return null;
        }

        $message = trim($message);

        if ($message === '') {
            return null;
        }

        if (!self::isSafeSingleLineString($message)) {
            throw new \InvalidArgumentException('Invalid health check result message.');
        }

        return $message;
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string,mixed>
     */
    private static function normalizeJsonLikeMap(array $map, string $path): array
    {
        if (array_is_list($map) && $map !== []) {
            throw new \InvalidArgumentException('Health check result ' . $path . ' must be a map.');
        }

        $out = [];

        foreach ($map as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException('Invalid health check result detail key at ' . $path);
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid health check result detail key at ' . $path);
            }

            $out[$key] = self::normalizeJsonLikeValue($value, $path . \chr(46) . $key);
        }

        ksort($out, \SORT_STRING);

        return $out;
    }

    private static function normalizeJsonLikeValue(mixed $value, string $path): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (!self::isSafeString($value)) {
                throw new \InvalidArgumentException('Invalid health check result string at ' . $path);
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float health check result detail at ' . $path);
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $out = [];

                foreach ($value as $item) {
                    $out[] = self::normalizeJsonLikeValue($item, $path . '[]');
                }

                return $out;
            }

            return self::normalizeJsonLikeMap($value, $path);
        }

        throw new \InvalidArgumentException('Invalid health check result detail at ' . $path);
    }

    private static function isSafeSingleLineString(string $value): bool
    {
        return self::isSafeString($value)
            && !str_contains($value, "\r")
            && !str_contains($value, "\n");
    }

    private static function isSafeString(string $value): bool
    {
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
