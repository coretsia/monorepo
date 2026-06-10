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

namespace Coretsia\Kernel\Artifacts\Exception;

/**
 * Deterministic Kernel artifact read/validation failure.
 *
 * This exception is used by PhpArtifactReader, ArtifactSchemaValidator, and
 * CacheVerifier when an existing artifact cannot be read, parsed, or validated.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_ARTIFACT_INVALID: reason-token
 *
 * The message MUST NOT include absolute paths, input path strings, raw artifact
 * bytes, raw PHP payloads, config values, env values, secrets, PHP warning text,
 * stack traces, object dumps, filesystem details, or previous throwable
 * messages.
 *
 * Missing expected artifacts should normally be represented by cache verification
 * result data as dirty=true with reason `missing`, not by this exception.
 *
 * @internal
 */
final class ArtifactInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ARTIFACT_INVALID';

    public const string REASON_INVALID = 'artifact-invalid';
    public const string REASON_UNREADABLE = 'artifact-unreadable';
    public const string REASON_READ_FAILED = 'artifact-read-failed';
    public const string REASON_PHP_RETURN_TYPE_INVALID = 'artifact-php-return-type-invalid';
    public const string REASON_ENVELOPE_INVALID = 'artifact-envelope-invalid';
    public const string REASON_HEADER_INVALID = 'artifact-header-invalid';
    public const string REASON_PAYLOAD_INVALID = 'artifact-payload-invalid';
    public const string REASON_SCHEMA_INVALID = 'artifact-schema-invalid';
    public const string REASON_NAME_MISMATCH = 'artifact-name-mismatch';
    public const string REASON_SCHEMA_VERSION_MISMATCH = 'artifact-schema-version-mismatch';
    public const string REASON_FINGERPRINT_INVALID = 'artifact-fingerprint-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_UNREADABLE => true,
        self::REASON_READ_FAILED => true,
        self::REASON_PHP_RETURN_TYPE_INVALID => true,
        self::REASON_ENVELOPE_INVALID => true,
        self::REASON_HEADER_INVALID => true,
        self::REASON_PAYLOAD_INVALID => true,
        self::REASON_SCHEMA_INVALID => true,
        self::REASON_NAME_MISMATCH => true,
        self::REASON_SCHEMA_VERSION_MISMATCH => true,
        self::REASON_FINGERPRINT_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('artifact-invalid-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('artifact-invalid-reason-invalid');
        }

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function withReason(
        string $reason = self::REASON_INVALID,
        ?\Throwable $previous = null,
    ): self {
        return new self($reason, $previous);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    private static function message(string $reason): string
    {
        return self::ERROR_CODE . ': ' . $reason;
    }
}
