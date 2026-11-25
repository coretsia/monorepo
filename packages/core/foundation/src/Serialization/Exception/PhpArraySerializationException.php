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
 * Deterministic PHP-array serialization failure.
 *
 * Messages contain only a fixed error code and an allowlisted reason token.
 */
final class PhpArraySerializationException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_PHP_ARRAY_SERIALIZATION_FAILED';

    public const string REASON_SERIALIZATION_INVALID = 'php-array-serialization-invalid';
    public const string REASON_FLOAT_FORBIDDEN = 'php-array-float-forbidden';
    public const string REASON_RESOURCE_FORBIDDEN = 'php-array-resource-forbidden';
    public const string REASON_CLOSURE_FORBIDDEN = 'php-array-closure-forbidden';
    public const string REASON_OBJECT_FORBIDDEN = 'php-array-object-forbidden';
    public const string REASON_MAP_KEY_MUST_BE_STRING = 'php-array-map-key-must-be-string';
    public const string REASON_TYPE_FORBIDDEN = 'php-array-type-forbidden';
    public const string REASON_MAX_DEPTH_EXCEEDED = 'php-array-max-depth-exceeded';
    public const string REASON_MAX_NODES_EXCEEDED = 'php-array-max-nodes-exceeded';
    public const string REASON_STRING_BYTES_EXCEEDED = 'php-array-string-bytes-exceeded';
    public const string REASON_TOTAL_STRING_BYTES_EXCEEDED = 'php-array-total-string-bytes-exceeded';

    /** @var array<string, true> */
    private const array REASONS = [
        self::REASON_SERIALIZATION_INVALID => true,
        self::REASON_FLOAT_FORBIDDEN => true,
        self::REASON_RESOURCE_FORBIDDEN => true,
        self::REASON_CLOSURE_FORBIDDEN => true,
        self::REASON_OBJECT_FORBIDDEN => true,
        self::REASON_MAP_KEY_MUST_BE_STRING => true,
        self::REASON_TYPE_FORBIDDEN => true,
        self::REASON_MAX_DEPTH_EXCEEDED => true,
        self::REASON_MAX_NODES_EXCEEDED => true,
        self::REASON_STRING_BYTES_EXCEEDED => true,
        self::REASON_TOTAL_STRING_BYTES_EXCEEDED => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('php-array-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE . ': ' . $reason);
    }

    public static function withReason(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
