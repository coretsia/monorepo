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

namespace Coretsia\Kernel\Boot;

use Coretsia\Kernel\Boot\Exception\BootstrapException;

/**
 * Immutable Bootstrap Phase A entrypoint input.
 *
 * This value object carries only explicit entrypoint-owned bootstrap inputs.
 *
 * It MUST NOT:
 *
 * - read the filesystem;
 * - inspect skeleton/apps/<app>;
 * - infer the application target;
 * - read process env;
 * - parse dotenv files;
 * - contain raw env values.
 *
 * Filesystem existence and readability checks are owned by later bootstrap
 * services. This class only validates that `skeletonRoot` is a non-empty safe
 * single-line string and stores the explicit `AppTarget`.
 */
final readonly class BootstrapInput
{
    public function __construct(
        private string $skeletonRoot,
        private AppTarget $appTarget,
        private ?string $appEnv = null,
        private ?string $preset = null,
        private ?bool $debug = null,
        private ?BootstrapEnvSourcePolicy $envSourcePolicy = null,
    ) {
        if (!self::isNonEmptySafeSingleLineString($this->skeletonRoot)) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_INVALID_SKELETON_ROOT,
            );
        }
    }

    /**
     * Entrypoint-provided skeleton root path.
     *
     * This may be an absolute or relative path. BootstrapInput does not check
     * whether it exists and does not normalize it.
     */
    public function skeletonRoot(): string
    {
        return $this->skeletonRoot;
    }

    /**
     * Explicit application target.
     *
     * The target is never inferred by scanning skeleton/apps/<app>.
     */
    public function appTarget(): AppTarget
    {
        return $this->appTarget;
    }

    /**
     * Optional entrypoint-provided app environment token.
     */
    public function appEnv(): ?string
    {
        return $this->appEnv;
    }

    /**
     * Optional entrypoint-provided preset token.
     */
    public function preset(): ?string
    {
        return $this->preset;
    }

    /**
     * Optional entrypoint-provided debug flag.
     */
    public function debug(): ?bool
    {
        return $this->debug;
    }

    /**
     * Optional entrypoint-provided env source precedence policy.
     */
    public function envSourcePolicy(): ?BootstrapEnvSourcePolicy
    {
        return $this->envSourcePolicy;
    }

    private static function isNonEmptySafeSingleLineString(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n")
            && \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
