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
 * Deterministic workflow definition and command-reference validation failure.
 *
 * WorkflowSchema uses only workflow-definition-invalid for deep definition
 * validation. WorkflowCatalog uses workflow-command-unknown or
 * workflow-command-composite for invalid command references.
 *
 * The public message contains only the package error code and one stable
 * allowlisted reason token. Workflow names, command tokens, definition values,
 * arguments, options, service ids, filesystem paths, and previous throwable
 * messages MUST NOT be retained or exposed.
 *
 * @internal
 */
final class WorkflowDefinitionInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CLI_WORKFLOW_DEFINITION_INVALID';

    public const string REASON_DEFINITION_INVALID = 'workflow-definition-invalid';
    public const string REASON_COMMAND_UNKNOWN = 'workflow-command-unknown';
    public const string REASON_COMMAND_COMPOSITE = 'workflow-command-composite';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_DEFINITION_INVALID => true,
        self::REASON_COMMAND_UNKNOWN => true,
        self::REASON_COMMAND_COMPOSITE => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('cli-workflow-definition-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE . ': ' . $this->reason);
    }

    public static function withReason(
        string $reason = self::REASON_DEFINITION_INVALID,
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
