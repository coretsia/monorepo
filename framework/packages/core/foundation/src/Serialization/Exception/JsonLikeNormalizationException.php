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

namespace Coretsia\Foundation\Serialization\Exception;

/**
 * Deterministic json-like runtime value normalization failure.
 *
 * This exception is used when Foundation rejects a value that cannot be
 * represented as a Coretsia json-like runtime value.
 *
 * The message is intentionally stable and safe. It may include only the
 * package error code, path-to-value, and a stable reason token. It must never
 * include rejected raw values, object class names, resource ids, payloads,
 * secrets, raw SQL, authorization data, cookies, tokens, session ids,
 * absolute local paths, or environment-specific data.
 */
final class JsonLikeNormalizationException extends \InvalidArgumentException
{
    public const string ERROR_CODE = 'CORETSIA_JSON_LIKE_INVALID';

    public const string REASON_FLOAT_FORBIDDEN = 'json-like-float-forbidden';
    public const string REASON_RESOURCE_FORBIDDEN = 'json-like-resource-forbidden';
    public const string REASON_CLOSURE_FORBIDDEN = 'json-like-closure-forbidden';
    public const string REASON_OBJECT_FORBIDDEN = 'json-like-object-forbidden';
    public const string REASON_MAP_KEY_MUST_BE_STRING = 'json-like-map-key-must-be-string';
    public const string REASON_TYPE_FORBIDDEN = 'json-like-type-forbidden';

    private readonly string $path;

    private readonly string $reason;

    public function __construct(
        string $path = '',
        string $reason = 'json-like-invalid',
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('json-like-normalization-reason-empty');
        }

        $this->path = $path;
        $this->reason = $reason;

        parent::__construct(self::message($path, $reason), 0, $previous);
    }

    public static function atPath(
        string $path,
        string $reason,
        ?\Throwable $previous = null,
    ): self {
        return new self($path, $reason, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    private static function message(string $path, string $reason): string
    {
        if ($path === '') {
            return self::ERROR_CODE . ': ' . $reason;
        }

        return self::ERROR_CODE . ': ' . $reason . ': value at ' . $path;
    }
}
