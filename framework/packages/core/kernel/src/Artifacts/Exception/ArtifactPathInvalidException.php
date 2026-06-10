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
 * Deterministic Kernel artifact path resolution failure.
 *
 * This exception is used by ArtifactPathResolver when artifact output path
 * configuration or artifact basenames violate Kernel artifact path policy.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_ARTIFACT_PATH_INVALID: reason-token
 *
 * The message MUST NOT include absolute paths, configured path values, input
 * path strings, filesystem layout, config payloads, env values, PHP warnings,
 * stack traces, host-specific data, or previous throwable messages.
 *
 * @internal
 */
final class ArtifactPathInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ARTIFACT_PATH_INVALID';

    public const string REASON_PATH_INVALID = 'artifact-path-invalid';
    public const string REASON_CACHE_DIR_INVALID = 'artifact-cache-dir-invalid';
    public const string REASON_CACHE_DIR_ABSOLUTE = 'artifact-cache-dir-absolute';
    public const string REASON_CACHE_DIR_TRAVERSAL = 'artifact-cache-dir-traversal';
    public const string REASON_CACHE_DIR_SKELETON_PREFIXED = 'artifact-cache-dir-skeleton-prefixed';
    public const string REASON_BASENAME_INVALID = 'artifact-basename-invalid';
    public const string REASON_TARGET_OUTSIDE_CACHE_DIR = 'artifact-target-outside-cache-dir';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_PATH_INVALID => true,
        self::REASON_CACHE_DIR_INVALID => true,
        self::REASON_CACHE_DIR_ABSOLUTE => true,
        self::REASON_CACHE_DIR_TRAVERSAL => true,
        self::REASON_CACHE_DIR_SKELETON_PREFIXED => true,
        self::REASON_BASENAME_INVALID => true,
        self::REASON_TARGET_OUTSIDE_CACHE_DIR => true,
    ];

    private function __construct(
        private readonly string $reason,
        ?\Throwable $previous = null,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('artifact-path-invalid-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('artifact-path-invalid-reason-invalid');
        }

        parent::__construct(self::message($this->reason), 0, $previous);
    }

    public static function withReason(
        string $reason = self::REASON_PATH_INVALID,
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
