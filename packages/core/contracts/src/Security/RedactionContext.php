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

namespace Coretsia\Contracts\Security;

/**
 * Immutable contracts-level context for one redaction operation.
 *
 * Scope is a stable operation or output-boundary identifier such as
 * "cli.output", "logging.record", or "http.problem-detail". Callers MUST NOT
 * derive scopes from paths, endpoints, user or tenant ids, tokens, field
 * values, request ids, correlation ids, or other high-cardinality runtime
 * data. Semantic high-cardinality policy remains caller-owned.
 *
 * This value object stores no raw redacted value and has no runtime ContextStore
 * dependency.
 */
final readonly class RedactionContext
{
    public const int SCHEMA_VERSION = 1;

    private string $scope;
    private RedactionMode $mode;

    public function __construct(
        string $scope,
        RedactionMode $mode = RedactionMode::Placeholder,
    ) {
        if (
            strlen($scope) > 128
            || preg_match('/[\x00-\x1F\x7F]/', $scope) === 1
            || preg_match('/\A[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*\z/', $scope) !== 1
        ) {
            throw new \InvalidArgumentException('redaction-context-scope-invalid');
        }

        $this->scope = $scope;
        $this->mode = $mode;
    }

    public function schemaVersion(): int
    {
        return self::SCHEMA_VERSION;
    }

    /**
     * @return non-empty-string
     */
    public function scope(): string
    {
        return $this->scope;
    }

    public function mode(): RedactionMode
    {
        return $this->mode;
    }
}
