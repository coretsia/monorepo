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
 * Immutable resolved Bootstrap Phase A configuration.
 *
 * BootstrapConfig represents the resolved, minimal, format-neutral Kernel boot
 * configuration needed before full config compilation and before runtime
 * lifecycle starts.
 *
 * It MUST NOT:
 *
 * - scan skeleton/apps/*;
 * - infer the application target;
 * - read the filesystem;
 * - require the derived app root to exist;
 * - read process env;
 * - parse dotenv files;
 * - contain raw env values.
 *
 * The application root is always derived deterministically as:
 *
 * skeletonRoot/apps/<appTarget>
 */
final readonly class BootstrapConfig
{
    private string $appRoot;

    /**
     * @param non-empty-string $appEnv
     * @param non-empty-string $preset
     */
    public function __construct(
        private string $appEnv,
        private string $preset,
        private bool $debug,
        private BootstrapEnvSourcePolicy $envSourcePolicy,
        private AppTarget $appTarget,
        private string $skeletonRoot,
    ) {
        if (!self::isNonEmptySafeSingleLineString($this->appEnv)) {
            throw new \InvalidArgumentException('bootstrap-config-app-env-invalid');
        }

        if (!self::isNonEmptySafeSingleLineString($this->preset)) {
            throw new \InvalidArgumentException('bootstrap-config-preset-invalid');
        }

        if (!self::isNonEmptySafeSingleLineString($this->skeletonRoot)) {
            throw BootstrapException::withReason(
                BootstrapException::REASON_INVALID_SKELETON_ROOT,
            );
        }

        $this->appRoot = self::deriveAppRoot($this->skeletonRoot, $this->appTarget);
    }

    /**
     * Resolved non-empty application environment token.
     *
     * @return non-empty-string
     */
    public function appEnv(): string
    {
        return $this->appEnv;
    }

    /**
     * Resolved non-empty bootstrap preset token.
     *
     * @return non-empty-string
     */
    public function preset(): string
    {
        return $this->preset;
    }

    public function debug(): bool
    {
        return $this->debug;
    }

    public function envSourcePolicy(): BootstrapEnvSourcePolicy
    {
        return $this->envSourcePolicy;
    }

    public function appTarget(): AppTarget
    {
        return $this->appTarget;
    }

    /**
     * Resolved skeleton root string.
     *
     * This class does not check whether the path exists and does not normalize
     * it through the filesystem.
     */
    public function skeletonRoot(): string
    {
        return $this->skeletonRoot;
    }

    /**
     * Deterministically derived application root.
     *
     * The path is derived as:
     *
     * skeletonRoot/apps/<appTarget>
     *
     * This class does not check whether the derived path exists.
     */
    public function appRoot(): string
    {
        return $this->appRoot;
    }

    private static function deriveAppRoot(string $skeletonRoot, AppTarget $appTarget): string
    {
        $root = \rtrim($skeletonRoot, '/\\');

        if ($root === '') {
            return $skeletonRoot . 'apps/' . $appTarget->value;
        }

        return $root . '/apps/' . $appTarget->value;
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
