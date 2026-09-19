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
 * Deterministic artifact-generation publication failure.
 *
 * The public message contains only the package error code and one stable reason
 * token. It never contains filesystem paths, generation ids, temporary names,
 * artifact bytes, PHP warning text, or previous throwable messages.
 *
 * @internal Kernel atomic artifact generation publication boundary.
 */
final class ArtifactGenerationPublishException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ARTIFACT_GENERATION_PUBLISH_FAILED';

    public const string REASON_LOCK_FAILED = 'lock-failed';
    public const string REASON_STAGING_CREATE_FAILED = 'staging-create-failed';
    public const string REASON_WRITE_FAILED = 'write-failed';
    public const string REASON_SYNC_FAILED = 'sync-failed';
    public const string REASON_GENERATION_INVALID = 'generation-invalid';
    public const string REASON_GENERATION_CONFLICT = 'generation-conflict';
    public const string REASON_POINTER_WRITE_FAILED = 'pointer-write-failed';
    public const string REASON_POINTER_SWITCH_FAILED = 'pointer-switch-failed';
    public const string REASON_CLEANUP_FAILED = 'cleanup-failed';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_LOCK_FAILED => true,
        self::REASON_STAGING_CREATE_FAILED => true,
        self::REASON_WRITE_FAILED => true,
        self::REASON_SYNC_FAILED => true,
        self::REASON_GENERATION_INVALID => true,
        self::REASON_GENERATION_CONFLICT => true,
        self::REASON_POINTER_WRITE_FAILED => true,
        self::REASON_POINTER_SWITCH_FAILED => true,
        self::REASON_CLEANUP_FAILED => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('artifact-generation-publish-reason-invalid');
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
