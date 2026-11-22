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

namespace Coretsia\Kernel\Runtime;

/**
 * Immutable Kernel-owned runtime diagnostics input.
 *
 * Boot orchestration supplies the already resolved application environment
 * and whether a validated immutable artifact generation was actually loaded.
 * The input never resolves environments or discovers artifacts itself.
 */
final readonly class RuntimeDiagnosticsInput
{
    /**
     * @param non-empty-string $appEnv
     */
    public function __construct(
        private string $appEnv,
        private bool $artifactGenerationLoaded,
    ) {
        if (
            $appEnv === ''
            || \trim($appEnv) !== $appEnv
            || \str_contains($appEnv, "\r")
            || \str_contains($appEnv, "\n")
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $appEnv) === 1
        ) {
            throw new \InvalidArgumentException('runtime-diagnostics-input-app-env-invalid');
        }
    }

    /**
     * @return non-empty-string
     */
    public function appEnv(): string
    {
        return $this->appEnv;
    }

    public function artifactGenerationLoaded(): bool
    {
        return $this->artifactGenerationLoaded;
    }
}
