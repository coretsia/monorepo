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
 * Deterministic compiled-container compilation failure.
 *
 * This exception is used by ContainerCompiler when container input cannot be
 * represented as a deterministic `container@1` compiled definition graph.
 *
 * The public message is intentionally fixed and safe:
 *
 *     CORETSIA_CONTAINER_COMPILE_FAILED: container-compile-failed
 *
 * The message MUST NOT include closure dumps, source snippets, absolute paths,
 * raw config values, raw env values, raw payloads, OS error messages, stack
 * traces, object dumps, filesystem details, or previous throwable messages.
 *
 * The reason is intentionally bounded to stable safe tokens only.
 *
 * @internal
 */
final class ContainerCompileFailedException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CONTAINER_COMPILE_FAILED';

    public const string MESSAGE_TOKEN = 'container-compile-failed';

    public const string REASON_COMPILE_FAILED = 'container-compile-failed';
    public const string REASON_DEFINITION_INVALID = 'container-definition-invalid';
    public const string REASON_FACTORY_INVALID = 'container-factory-invalid';
    public const string REASON_ARGUMENT_INVALID = 'container-argument-invalid';
    public const string REASON_PARAMETER_INVALID = 'container-parameter-invalid';
    public const string REASON_TAG_INVALID = 'container-tag-invalid';
    public const string REASON_GRAPH_INVALID = 'container-graph-invalid';
    public const string REASON_CLOSURE_DEFINITION = 'container-closure-definition';
    public const string REASON_CALLABLE_DEFINITION = 'container-callable-definition';
    public const string REASON_NON_DETERMINISTIC_VALUE = 'container-non-deterministic-value';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_COMPILE_FAILED => true,
        self::REASON_DEFINITION_INVALID => true,
        self::REASON_FACTORY_INVALID => true,
        self::REASON_ARGUMENT_INVALID => true,
        self::REASON_PARAMETER_INVALID => true,
        self::REASON_TAG_INVALID => true,
        self::REASON_GRAPH_INVALID => true,
        self::REASON_CLOSURE_DEFINITION => true,
        self::REASON_CALLABLE_DEFINITION => true,
        self::REASON_NON_DETERMINISTIC_VALUE => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('container-compile-failed-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('container-compile-failed-reason-invalid');
        }

        parent::__construct(self::message(), 0);
    }

    public static function withReason(
        string $reason = self::REASON_COMPILE_FAILED,
    ): self {
        return new self($reason);
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
