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

namespace Coretsia\Contracts\Validation;

/**
 * Format-neutral validation boundary port.
 *
 * Implementations validate implementation-owned input against
 * implementation-owned rule data and optional safe context metadata.
 *
 * The input value is runtime input and may be any value required by the
 * concrete owner implementation. It MUST remain ephemeral at the contracts
 * boundary and MUST NOT be copied into ValidationResult, Violation,
 * ValidationException, normalized errors, logs, spans, metrics, health output,
 * CLI output, worker failure output, or unsafe diagnostics.
 *
 * The port MUST NOT require PSR-7 request/response objects, framework HTTP
 * request/response objects, CLI concrete input/output objects, queue vendor
 * message objects, worker vendor context objects, scheduler vendor context
 * objects, concrete service container objects, platform classes, or integration
 * classes.
 */
interface ValidatorInterface
{
    /**
     * Validates runtime input against implementation-owned rules.
     *
     * Rules and context are data inputs for the implementation. They SHOULD stay
     * json-like and safe whenever represented as array data. They MUST NOT
     * require executable validators, closures, service instances, container
     * references, request objects, response objects, PSR-7 objects, vendor SDK
     * objects, or runtime wiring objects at the contracts boundary.
     *
     * @param array<string,mixed> $rules
     * @param array<string,mixed> $context
     */
    public function validate(
        mixed $input,
        array $rules = [],
        array $context = [],
    ): ValidationResult;
}
