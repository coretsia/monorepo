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
 */
enum ConfigSourceType: string
{
    case PackageDefaults = 'package_defaults';
    case ApplicationConfig = 'application_config';
    case Environment = 'environment';
    case RuntimeOverride = 'runtime_override';
    case GeneratedArtifact = 'generated_artifact';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PackageDefaults->value,
            self::ApplicationConfig->value,
            self::Environment->value,
            self::RuntimeOverride->value,
            self::GeneratedArtifact->value,
        ];
    }

    public static function isKnown(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
