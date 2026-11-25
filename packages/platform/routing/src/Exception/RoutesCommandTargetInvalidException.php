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

namespace Coretsia\Platform\Routing\Exception;

/**
 * Deterministic failure of explicit Routing command target resolution.
 *
 * The message contains only the fixed Routing error code and a safe reason
 * token. Option values, application paths, source-host internals, service
 * metadata, and previous throwable messages are never retained.
 *
 * @internal
 */
final class RoutesCommandTargetInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ROUTING_COMMAND_TARGET_INVALID';

    public const string REASON_TARGET_REQUIRED = 'routing-command-target-required';
    public const string REASON_TARGET_INVALID = 'routing-command-target-invalid';
    public const string REASON_HOST_INVALID = 'routing-command-host-invalid';
    public const string REASON_MODULE_NOT_ENABLED = 'routing-module-not-enabled';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_TARGET_REQUIRED => true,
        self::REASON_TARGET_INVALID => true,
        self::REASON_HOST_INVALID => true,
        self::REASON_MODULE_NOT_ENABLED => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('routing-command-target-reason-invalid');
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
