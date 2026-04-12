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

namespace Coretsia\Devtools\CliSpikes\Spikes;

/**
 * Loads tools-only spikes bootstrap deterministically:
 * - MUST load exactly the path computed by SpikesPaths (no probing, no fallbacks)
 * - MUST use require_once
 * - MUST NOT emit stdout/stderr
 *
 * Bootstrap failure containment:
 * - launcher/paths resolution failures MUST surface as SpikesBootstrapFailedException
 * - Composer autoload MUST already be loaded by the CLI launcher; otherwise this class MUST fail
 *   with composer-autoload-missing before requiring tools bootstrap
 * - tools bootstrap path missing/unreadable MUST surface as spikes-bootstrap-missing
 */
final class SpikesBootstrap
{
    private function __construct()
    {
    }

    /**
     * @throws SpikesBootstrapFailedException
     */
    public static function requireOnceFromGlobals(): void
    {
        $paths = SpikesPaths::fromServerGlobals();
        self::requireOnce($paths);
    }

    /**
     * @throws SpikesBootstrapFailedException
     */
    public static function requireOnce(SpikesPaths $paths): void
    {
        self::assertComposerAutoloadAlreadyLoaded();

        $bootstrapPath = $paths->spikesBootstrapPath();

        if (!is_file($bootstrapPath) || !is_readable($bootstrapPath)) {
            throw new SpikesBootstrapFailedException(
                SpikesBootstrapFailedException::REASON_SPIKES_BOOTSTRAP_MISSING
            );
        }

        require_once $bootstrapPath;
    }

    /**
     * Launcher contract (cemented):
     * - CLI launcher MUST already require Composer autoload before tools bootstrap.
     * - This check MUST NOT trigger autoloading implicitly; it only validates current runtime state.
     *
     * @throws SpikesBootstrapFailedException
     */
    private static function assertComposerAutoloadAlreadyLoaded(): void
    {
        if (!\class_exists(\Composer\Autoload\ClassLoader::class, false)) {
            throw new SpikesBootstrapFailedException(
                SpikesBootstrapFailedException::REASON_COMPOSER_AUTOLOAD_MISSING
            );
        }
    }
}
