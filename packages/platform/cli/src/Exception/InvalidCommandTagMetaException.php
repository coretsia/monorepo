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

namespace Coretsia\Platform\Cli\Exception;

/**
 * Deterministic command tag schema, catalog, and dispatch identity failure.
 *
 * The public diagnostic exposes a reason token only. Even though validated
 * command names or service ids may be safe diagnostics, this exception never
 * retains caller-supplied names, service ids, tag values, or runtime metadata.
 * This also covers duplicate names, reserved names, non-zero priority, and
 * command service identity mismatches.
 *
 * The public message contains only the package error code and a stable
 * allowlisted reason token. Raw input, configuration, service metadata,
 * filesystem paths, rendered payloads, secrets, and previous throwable
 * messages MUST NOT be included.
 *
 * @internal
 */
final class InvalidCommandTagMetaException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CLI_INVALID_COMMAND_META';

    public const string REASON_INVALID = 'command-meta-invalid';
    public const string REASON_SCHEMA_INVALID = 'command-meta-schema-invalid';
    public const string REASON_SERVICE_INVALID = 'command-service-invalid';
    public const string REASON_PRIORITY_INVALID = 'command-priority-invalid';
    public const string REASON_NAME_COLLISION = 'command-name-collision';
    public const string REASON_RESERVED_NAME = 'command-name-reserved';
    public const string REASON_IDENTITY_MISMATCH = 'command-identity-mismatch';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_SCHEMA_INVALID => true,
        self::REASON_SERVICE_INVALID => true,
        self::REASON_PRIORITY_INVALID => true,
        self::REASON_NAME_COLLISION => true,
        self::REASON_RESERVED_NAME => true,
        self::REASON_IDENTITY_MISMATCH => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('cli-command-meta-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE . ': ' . $this->reason);
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

    public function reason(): string
    {
        return $this->reason;
    }
}
