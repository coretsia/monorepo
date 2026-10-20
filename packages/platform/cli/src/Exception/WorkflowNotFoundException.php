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
 * Deterministic rejection of an invalid or unknown workflow lookup.
 *
 * The public and internal exception messages contain only the fixed error
 * code and reason token. Requested workflow-name tokens, including valid
 * unknown names, configuration definitions, and step data MUST NOT be
 * retained or exposed. No previous throwable is accepted.
 *
 * @internal
 */
final class WorkflowNotFoundException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_CLI_WORKFLOW_NOT_FOUND';
    public const string REASON_WORKFLOW_NOT_FOUND = 'workflow-not-found';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_WORKFLOW_NOT_FOUND);
    }

    public static function notFound(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_WORKFLOW_NOT_FOUND;
    }
}
