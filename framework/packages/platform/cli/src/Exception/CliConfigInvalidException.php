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

use Coretsia\Platform\Cli\Error\ErrorCodes;

/**
 * Domain error: CLI configuration is invalid.
 */
final class CliConfigInvalidException extends CliException
{
    public const string REASON_CLI_SPIKES_PRESET_MISSING = 'cli-spikes-preset-missing';
    public const string REASON_CLI_SPIKES_PRESET_INVALID = 'cli-spikes-preset-invalid';
    public const string REASON_CLI_SUBTREE_INVALID = 'cli-subtree-invalid';

    /**
     * Layout/boot assumptions (Phase 0):
     * - CLI is scoped to monorepo layout (framework/ + skeleton/ siblings).
     * - Root resolution is derived from launcher path and MUST NOT probe.
     */
    public const string REASON_LAYOUT_INVALID = 'layout-invalid';

    /** @var list<string> */
    private const array ALLOWED_REASONS = [
        self::REASON_CLI_SPIKES_PRESET_MISSING,
        self::REASON_CLI_SPIKES_PRESET_INVALID,
        self::REASON_CLI_SUBTREE_INVALID,
        self::REASON_LAYOUT_INVALID,
    ];

    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(
            ErrorCodes::CORETSIA_CLI_CONFIG_INVALID,
            self::normalizeReason($reason, self::ALLOWED_REASONS),
            $previous,
        );
    }

    public static function cliSpikesPresetMissing(?\Throwable $previous = null): self
    {
        return new self(self::REASON_CLI_SPIKES_PRESET_MISSING, $previous);
    }

    public static function cliSpikesPresetInvalid(?\Throwable $previous = null): self
    {
        return new self(self::REASON_CLI_SPIKES_PRESET_INVALID, $previous);
    }

    public static function cliSubtreeInvalid(?\Throwable $previous = null): self
    {
        return new self(self::REASON_CLI_SUBTREE_INVALID, $previous);
    }

    public static function layoutInvalid(?\Throwable $previous = null): self
    {
        return new self(self::REASON_LAYOUT_INVALID, $previous);
    }
}
