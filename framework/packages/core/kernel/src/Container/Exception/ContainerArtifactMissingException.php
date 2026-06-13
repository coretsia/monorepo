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
 * Deterministic compiled-container artifact missing failure.
 *
 * This exception is used by artifact-only runtime boot when the required
 * container.php artifact is missing.
 *
 * The public message is intentionally fixed and safe:
 *
 *     CORETSIA_CONTAINER_ARTIFACT_MISSING: container-artifact-missing
 *
 * The message MUST NOT include the missing filesystem path, absolute paths,
 * configured path strings, OS error messages, stack traces, filesystem details,
 * or previous throwable messages.
 *
 * @internal
 */
final class ContainerArtifactMissingException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONTAINER_ARTIFACT_MISSING';

    public const string MESSAGE_TOKEN = 'container-artifact-missing';

    public const string REASON_MISSING = 'container-artifact-missing';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_MISSING => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('container-artifact-missing-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('container-artifact-missing-reason-invalid');
        }

        parent::__construct(self::message(), 0);
    }

    public static function withReason(
        string $reason = self::REASON_MISSING,
    ): self {
        return new self($reason);
    }

    public static function missing(): self
    {
        return new self(self::REASON_MISSING);
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
