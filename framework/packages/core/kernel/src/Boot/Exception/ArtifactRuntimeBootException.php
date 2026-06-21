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

namespace Coretsia\Kernel\Boot\Exception;

/**
 * Public deterministic artifact-runtime boot failure.
 *
 * This exception is the public boundary for production runtime container boot
 * from already generated Kernel-owned artifacts.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_ARTIFACT_RUNTIME_BOOT_FAILED: reason-token
 *
 * The message MUST NOT include artifact paths, absolute paths, raw config
 * values, raw artifact payloads, env values, secrets, tokens, headers, command
 * lines, PHP warning text, previous throwable messages, stack traces, or
 * filesystem details.
 */
final class ArtifactRuntimeBootException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ARTIFACT_RUNTIME_BOOT_FAILED';

    public const string REASON_CONFIG_ARTIFACT_INVALID = 'artifact-runtime-boot-config-artifact-invalid';
    public const string REASON_CONTAINER_ARTIFACT_INVALID = 'artifact-runtime-boot-container-artifact-invalid';
    public const string REASON_RUNTIME_CONTAINER_INVALID = 'artifact-runtime-boot-runtime-container-invalid';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_CONFIG_ARTIFACT_INVALID => true,
        self::REASON_CONTAINER_ARTIFACT_INVALID => true,
        self::REASON_RUNTIME_CONTAINER_INVALID => true,
    ];

    private function __construct(
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('artifact-runtime-boot-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('artifact-runtime-boot-reason-invalid');
        }

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function configArtifactInvalid(?\Throwable $previous = null): self
    {
        return new self(
            reason: self::REASON_CONFIG_ARTIFACT_INVALID,
            previous: $previous,
        );
    }

    public static function containerArtifactInvalid(?\Throwable $previous = null): self
    {
        return new self(
            reason: self::REASON_CONTAINER_ARTIFACT_INVALID,
            previous: $previous,
        );
    }

    public static function runtimeContainerInvalid(?\Throwable $previous = null): self
    {
        return new self(
            reason: self::REASON_RUNTIME_CONTAINER_INVALID,
            previous: $previous,
        );
    }

    public static function withReason(
        string $reason = self::REASON_RUNTIME_CONTAINER_INVALID,
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
