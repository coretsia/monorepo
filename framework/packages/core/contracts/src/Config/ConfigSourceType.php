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

namespace Coretsia\Contracts\Config;

/**
 * Stable source type vocabulary for config source tracking.
 *
 * Source type values are vocabulary only. They do not define merge precedence
 * by themselves.
 */
enum ConfigSourceType: string
{
    case PackageDefault = 'package_default';
    case SkeletonConfig = 'skeleton_config';
    case AppConfig = 'app_config';
    case Dotenv = 'dotenv';
    case Env = 'env';
    case Cli = 'cli';
    case Runtime = 'runtime';
    case GeneratedArtifact = 'generated_artifact';

    /**
     * @return list<non-empty-string>
     */
    public static function values(): array
    {
        return [
            self::PackageDefault->value,
            self::SkeletonConfig->value,
            self::AppConfig->value,
            self::Dotenv->value,
            self::Env->value,
            self::Cli->value,
            self::Runtime->value,
            self::GeneratedArtifact->value,
        ];
    }

    public static function isKnown(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
