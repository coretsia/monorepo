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
 * Deterministic CLI presentation configuration failure.
 *
 * Used by CliOutputPolicy, CliServiceFactory, and CommandOverrides for
 * invalid CLI-owned policy and catalog-view override configuration.
 *
 * The public message contains only the package error code and a stable
 * allowlisted reason token. Raw input, configuration, service metadata,
 * filesystem paths, rendered payloads, secrets, and previous throwable
 * messages MUST NOT be included.
 *
 * @internal
 */
final class CliConfigInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CLI_CONFIG_INVALID';

    public const string REASON_INVALID = 'cli-config-invalid';
    public const string REASON_OUTPUT_POLICY_INVALID = 'cli-output-policy-invalid';
    public const string REASON_SERVICE_CONFIG_INVALID = 'cli-service-config-invalid';
    public const string REASON_COMMAND_OVERRIDES_INVALID = 'cli-command-overrides-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_OUTPUT_POLICY_INVALID => true,
        self::REASON_SERVICE_CONFIG_INVALID => true,
        self::REASON_COMMAND_OVERRIDES_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('cli-config-reason-invalid');
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
