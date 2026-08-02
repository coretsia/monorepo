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

namespace Coretsia\Platform\Worker\Exception;

/**
 * Deterministic worker lifecycle failure.
 *
 * This exception covers startup, readiness, child-process, shutdown, runtime
 * cleanup, lifecycle-lock, state-schema, and task-factory failures owned by the
 * worker package after runtime-driver compatibility has already passed.
 *
 * The public message contains only:
 *
 *     CORETSIA_WORKER_START_FAILED: worker-reason-token
 *
 * It MUST NOT expose raw config values, absolute paths, raw socket paths, raw
 * TCP endpoints, request payloads, headers, tokens, process command lines,
 * previous throwable messages, container exception messages, service ids,
 * stack traces, or environment-specific data.
 */
final class WorkerStartFailedException extends WorkerException
{
    public const string ERROR_CODE = 'CORETSIA_WORKER_START_FAILED';

    public const string REASON_START_FAILED = 'worker-start-failed';
    public const string REASON_INVALID_STATE = 'worker-invalid-state';
    public const string REASON_REQUEST_HANDLER_MISSING = 'worker-request-handler-missing';
    public const string REASON_REQUEST_HANDLER_UNRESOLVABLE = 'worker-request-handler-unresolvable';
    public const string REASON_REQUEST_HANDLER_INVALID = 'worker-request-handler-invalid';
    public const string REASON_READINESS_TIMEOUT = 'worker-readiness-timeout';
    public const string REASON_READINESS_INVALID = 'worker-readiness-invalid';
    public const string REASON_CHILD_START_FAILED = 'worker-child-start-failed';
    public const string REASON_CHILD_EXITED = 'worker-child-exited';
    public const string REASON_SHUTDOWN_FAILED = 'worker-shutdown-failed';
    public const string REASON_RUNTIME_CLEANUP_FAILED = 'worker-runtime-cleanup-failed';
    public const string REASON_LIFECYCLE_LOCK_FAILED = 'worker-lifecycle-lock-failed';
    public const string REASON_SIGNAL_HANDLING_UNAVAILABLE = 'worker-signal-handling-unavailable';
    public const string REASON_PROCESS_HOST_FAILED = 'worker-process-host-failed';

    private const array REASONS = [
        self::REASON_START_FAILED => true,
        self::REASON_INVALID_STATE => true,
        self::REASON_REQUEST_HANDLER_MISSING => true,
        self::REASON_REQUEST_HANDLER_UNRESOLVABLE => true,
        self::REASON_REQUEST_HANDLER_INVALID => true,
        self::REASON_READINESS_TIMEOUT => true,
        self::REASON_READINESS_INVALID => true,
        self::REASON_CHILD_START_FAILED => true,
        self::REASON_CHILD_EXITED => true,
        self::REASON_SHUTDOWN_FAILED => true,
        self::REASON_RUNTIME_CLEANUP_FAILED => true,
        self::REASON_LIFECYCLE_LOCK_FAILED => true,
        self::REASON_SIGNAL_HANDLING_UNAVAILABLE => true,
        self::REASON_PROCESS_HOST_FAILED => true,
    ];

    private function __construct(string $reason)
    {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('worker-start-failed-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE, $reason);
    }

    public static function startFailed(): self
    {
        return new self(self::REASON_START_FAILED);
    }

    public static function invalidState(): self
    {
        return new self(self::REASON_INVALID_STATE);
    }

    public static function requestHandlerMissing(): self
    {
        return new self(self::REASON_REQUEST_HANDLER_MISSING);
    }

    public static function requestHandlerUnresolvable(): self
    {
        return new self(self::REASON_REQUEST_HANDLER_UNRESOLVABLE);
    }

    public static function requestHandlerInvalid(): self
    {
        return new self(self::REASON_REQUEST_HANDLER_INVALID);
    }

    public static function readinessTimeout(): self
    {
        return new self(self::REASON_READINESS_TIMEOUT);
    }

    public static function readinessInvalid(): self
    {
        return new self(self::REASON_READINESS_INVALID);
    }

    public static function childStartFailed(): self
    {
        return new self(self::REASON_CHILD_START_FAILED);
    }

    public static function childExited(): self
    {
        return new self(self::REASON_CHILD_EXITED);
    }

    public static function shutdownFailed(): self
    {
        return new self(self::REASON_SHUTDOWN_FAILED);
    }

    public static function runtimeCleanupFailed(): self
    {
        return new self(self::REASON_RUNTIME_CLEANUP_FAILED);
    }

    public static function lifecycleLockFailed(): self
    {
        return new self(self::REASON_LIFECYCLE_LOCK_FAILED);
    }

    public static function signalHandlingUnavailable(): self
    {
        return new self(self::REASON_SIGNAL_HANDLING_UNAVAILABLE);
    }

    public static function processHostFailed(): self
    {
        return new self(self::REASON_PROCESS_HOST_FAILED);
    }
}
