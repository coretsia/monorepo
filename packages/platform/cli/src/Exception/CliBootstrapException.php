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
 * Deterministic post-autoload CLI entrypoint layout failure.
 *
 * Used by CliEntrypointPathsResolver after Composer autoload succeeds.
 * Pre-autoload failures are handled by the fixed entrypoint fallback,
 * without instantiating this class.
 *
 * The public message contains only the package error code and a stable
 * allowlisted reason token. Raw input, configuration, service metadata,
 * filesystem paths, rendered payloads, secrets, and previous throwable
 * messages MUST NOT be included.
 *
 * @internal
 */
final class CliBootstrapException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CLI_BOOTSTRAP_FAILED';

    public const string REASON_INVALID = 'cli-bootstrap-invalid';
    public const string REASON_AUTOLOAD_INVALID = 'cli-autoload-invalid';
    public const string REASON_LAUNCHER_INVALID = 'cli-launcher-invalid';
    public const string REASON_APPLICATION_ROOT_UNRESOLVED = 'cli-application-root-unresolved';
    public const string REASON_APPLICATION_ROOT_CONFLICT = 'cli-application-root-conflict';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_AUTOLOAD_INVALID => true,
        self::REASON_LAUNCHER_INVALID => true,
        self::REASON_APPLICATION_ROOT_UNRESOLVED => true,
        self::REASON_APPLICATION_ROOT_CONFLICT => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('cli-bootstrap-reason-invalid');
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
