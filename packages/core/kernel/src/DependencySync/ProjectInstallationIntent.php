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

namespace Coretsia\Kernel\DependencySync;

use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;

final readonly class ProjectInstallationIntent
{
    private ProjectApplicationSet $applications;

    /** @var array<string, non-empty-string> */
    private array $fixedPresetByTarget;

    /** @param array<string, string> $fixedPresetByTarget */
    public function __construct(
        ProjectApplicationSet $applications,
        array $fixedPresetByTarget = [],
    ) {
        $selectedTargets = [];

        foreach ($applications->targets() as $target) {
            $selectedTargets[$target->value] = true;
        }

        $canonical = [];

        foreach ($fixedPresetByTarget as $targetName => $preset) {
            if (
                !\is_string($targetName)
                || !isset($selectedTargets[$targetName])
                || !\is_string($preset)
                || !self::isSafePresetToken($preset)
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
            }

            $canonical[$targetName] = $preset;
        }

        \ksort($canonical, \SORT_STRING);

        $this->applications = $applications;
        $this->fixedPresetByTarget = $canonical;
    }

    public function applications(): ProjectApplicationSet
    {
        return $this->applications;
    }

    /** @return array<string, non-empty-string> */
    public function fixedPresetByTarget(): array
    {
        return $this->fixedPresetByTarget;
    }

    private static function isSafePresetToken(string $preset): bool
    {
        return $preset !== ''
            && \trim($preset) === $preset
            && \preg_match('/[\x00-\x1F\x7F]/', $preset) === 0;
    }
}
