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

namespace Coretsia\Contracts\Observability\Errors;

/**
 * Format-neutral normalized error descriptor.
 *
 * This class is intentionally not a DTO-marker class. Its invariants are
 * governed by contracts shape policy.
 *
 * The descriptor MUST NOT expose raw Throwable objects, transport objects,
 * raw payloads, raw SQL, credentials, tokens, cookies, or profile payloads.
 */
final readonly class ErrorDescriptor
{
    public const int SCHEMA_VERSION = 1;
    private string $code;
    private string $message;
    private ErrorSeverity $severity;
    private ?int $httpStatus;

    /**
     * Safe deterministic extension map.
     *
     * Values are normalized recursively and may contain only:
     * null, bool, int, string, list values, or string-keyed maps.
     *
     * Floats, objects, closures, resources, and throwables are rejected.
     *
     * @var array<string, mixed>
     */
    private array $extensions;

    /**
     * @param array<string,mixed> $extensions
     */
    public function __construct(
        string $code,
        string $message,
        ErrorSeverity $severity = ErrorSeverity::Error,
        ?int $httpStatus = null,
        array $extensions = [],
    ) {
        $this->code = self::normalizeCode($code);
        $this->message = self::normalizeMessage($message);
        $this->severity = $severity;
        $this->httpStatus = self::normalizeHttpStatus($httpStatus);
        $this->extensions = self::normalizeExtensions($extensions);
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function severity(): ErrorSeverity
    {
        return $this->severity;
    }

    /**
     * Optional HTTP status hint only.
     *
     * Non-HTTP runtimes may ignore this value.
     */
    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Returns deterministic safe extension metadata.
     *
     * @return array<string, mixed>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return array{
     *     code: string,
     *     extensions: array<string, mixed>,
     *     httpStatus: int|null,
     *     message: string,
     *     schemaVersion: int,
     *     severity: string
     * }
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'extensions' => $this->extensions,
            'httpStatus' => $this->httpStatus,
            'message' => $this->message,
            'schemaVersion' => self::SCHEMA_VERSION,
            'severity' => $this->severity->value,
        ];
    }

    private static function normalizeCode(string $code): string
    {
        $code = trim($code);

        if ($code === '') {
            throw new \InvalidArgumentException('Invalid error descriptor code.');
        }

        if (!self::isSafeSingleLineString($code)) {
            throw new \InvalidArgumentException('Invalid error descriptor code.');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]*$/', $code) !== 1) {
            throw new \InvalidArgumentException('Invalid error descriptor code.');
        }

        return $code;
    }

    private static function normalizeMessage(string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            throw new \InvalidArgumentException('Invalid error descriptor message.');
        }

        if (!self::isSafeSingleLineString($message)) {
            throw new \InvalidArgumentException('Invalid error descriptor message.');
        }

        return $message;
    }

    private static function normalizeHttpStatus(?int $httpStatus): ?int
    {
        if ($httpStatus === null) {
            return null;
        }

        if ($httpStatus < 100 || $httpStatus > 599) {
            throw new \InvalidArgumentException('Invalid error descriptor http status.');
        }

        return $httpStatus;
    }

    /**
     * @param array<string,mixed> $extensions
     *
     * @return array<string,mixed>
     */
    private static function normalizeExtensions(array $extensions): array
    {
        if (array_is_list($extensions) && $extensions !== []) {
            throw new \InvalidArgumentException('Error descriptor extensions must be a map.');
        }

        /** @var array<string,mixed> $normalized */
        $normalized = self::normalizeJsonLikeMap($extensions, 'extensions');

        return $normalized;
    }

    /**
     * @param array<mixed> $map
     *
     * @return array<string,mixed>
     */
    private static function normalizeJsonLikeMap(array $map, string $path): array
    {
        $out = [];

        foreach ($map as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Invalid error descriptor extension key at ' . $path);
            }

            if ($key === '') {
                throw new \InvalidArgumentException('Invalid error descriptor extension key at ' . $path);
            }

            if (!self::isSafeSingleLineString($key)) {
                throw new \InvalidArgumentException('Invalid error descriptor extension key at ' . $path);
            }

            $out[$key] = self::normalizeJsonLikeValue($value, $path . \chr(46) . $key);
        }

        ksort($out, \SORT_STRING);

        /** @var array<string,mixed> $out */
        return $out;
    }

    private static function normalizeJsonLikeValue(mixed $value, string $path): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (!self::isSafeString($value)) {
                throw new \InvalidArgumentException('Invalid error descriptor extension string at ' . $path);
            }

            return $value;
        }

        if (is_float($value)) {
            throw new \InvalidArgumentException('Invalid float error descriptor extension at ' . $path);
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

        throw new \InvalidArgumentException('Invalid error descriptor extension at ' . $path);
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
