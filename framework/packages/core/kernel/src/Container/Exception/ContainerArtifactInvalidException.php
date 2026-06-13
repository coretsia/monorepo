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

namespace Coretsia\Kernel\Container\Exception;

/**
 * Deterministic compiled-container artifact invalid failure.
 *
 * This exception is used by artifact-only runtime boot when container.php is
 * present but cannot be accepted as a production `container@1`
 * compiled-container artifact.
 *
 * It covers invalid, unreadable, schema-invalid, legacy-stub, and non-compiled
 * `container@1` artifacts.
 *
 * The public message is intentionally fixed and safe:
 *
 *     CORETSIA_CONTAINER_ARTIFACT_INVALID: container-artifact-invalid
 *
 * The message MUST NOT include absolute paths, raw artifact payloads, raw config
 * values, raw env values, PHP warning text, closure dumps, source snippets,
 * OS error messages, stack traces, object dumps, filesystem details, or previous
 * throwable messages.
 *
 * The reason is intentionally bounded to stable safe tokens only.
 *
 * @internal
 */
final class ContainerArtifactInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONTAINER_ARTIFACT_INVALID';

    public const string MESSAGE_TOKEN = 'container-artifact-invalid';

    public const string REASON_INVALID = 'container-artifact-invalid';
    public const string REASON_UNREADABLE = 'container-artifact-unreadable';
    public const string REASON_READ_FAILED = 'container-artifact-read-failed';
    public const string REASON_RETURN_TYPE_INVALID = 'container-artifact-return-type-invalid';
    public const string REASON_ENVELOPE_INVALID = 'container-artifact-envelope-invalid';
    public const string REASON_HEADER_INVALID = 'container-artifact-header-invalid';
    public const string REASON_PAYLOAD_INVALID = 'container-artifact-payload-invalid';
    public const string REASON_SCHEMA_INVALID = 'container-artifact-schema-invalid';
    public const string REASON_SCHEMA_VERSION_INVALID = 'container-artifact-schema-version-invalid';
    public const string REASON_LEGACY_STUB = 'container-artifact-legacy-stub';
    public const string REASON_NON_COMPILED = 'container-artifact-non-compiled';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_UNREADABLE => true,
        self::REASON_READ_FAILED => true,
        self::REASON_RETURN_TYPE_INVALID => true,
        self::REASON_ENVELOPE_INVALID => true,
        self::REASON_HEADER_INVALID => true,
        self::REASON_PAYLOAD_INVALID => true,
        self::REASON_SCHEMA_INVALID => true,
        self::REASON_SCHEMA_VERSION_INVALID => true,
        self::REASON_LEGACY_STUB => true,
        self::REASON_NON_COMPILED => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('container-artifact-invalid-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('container-artifact-invalid-reason-invalid');
        }

        parent::__construct(self::message(), 0);
    }

    public static function withReason(
        string $reason = self::REASON_INVALID,
    ): self {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function messageToken(): string
    {
        return self::MESSAGE_TOKEN;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    private static function message(): string
    {
        return self::ERROR_CODE . ': ' . self::MESSAGE_TOKEN;
    }
}
