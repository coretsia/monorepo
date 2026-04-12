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

namespace Coretsia\Devtools\CliSpikes\Spikes;

/**
 * Deterministic bootstrap failure carrier (typed signal).
 *
 * Public reason() contract (cemented):
 * - launcher-path-unresolvable
 * - framework-root-unresolvable
 * - repo-root-unresolvable
 * - spikes-bootstrap-missing
 * - composer-autoload-missing
 *
 * Invariants:
 * - message MUST be exactly the reason token
 * - unknown reason token is a developer error and MUST fail fast
 * - MUST NOT include absolute paths or dynamic OS error text
 */
final class SpikesBootstrapFailedException extends \RuntimeException
{
    public const string REASON_COMPOSER_AUTOLOAD_MISSING = 'composer-autoload-missing';
    public const string REASON_LAUNCHER_PATH_UNRESOLVABLE = 'launcher-path-unresolvable';
    public const string REASON_FRAMEWORK_ROOT_UNRESOLVABLE = 'framework-root-unresolvable';
    public const string REASON_REPO_ROOT_UNRESOLVABLE = 'repo-root-unresolvable';
    public const string REASON_SPIKES_BOOTSTRAP_MISSING = 'spikes-bootstrap-missing';

    /** @var list<string> */
    private const array ALLOWED_REASONS = [
        self::REASON_COMPOSER_AUTOLOAD_MISSING,
        self::REASON_LAUNCHER_PATH_UNRESOLVABLE,
        self::REASON_FRAMEWORK_ROOT_UNRESOLVABLE,
        self::REASON_REPO_ROOT_UNRESOLVABLE,
        self::REASON_SPIKES_BOOTSTRAP_MISSING,
    ];

    private readonly string $reason;

    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        if (!\in_array($reason, self::ALLOWED_REASONS, true)) {
            throw new \InvalidArgumentException('spikes-bootstrap-invalid-reason-token');
        }

        $this->reason = $reason;

        parent::__construct($this->reason, 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
