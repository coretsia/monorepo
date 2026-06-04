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

namespace Coretsia\Kernel\Module\Exception;

/**
 * Deterministic mode preset not-found failure.
 *
 * Used when the selected preset cannot be resolved from the skeleton override
 * path or the framework default path.
 *
 * Diagnostics intentionally expose only the safe preset name and stable reason
 * token. They must not expose resolved filesystem paths, skeleton root,
 * defaults path, overrides path, raw config payloads, or PHP warnings.
 *
 * @internal
 */
final class ModePresetNotFoundException extends ModuleResolutionException
{
    public const string REASON_PRESET_NAME_INVALID = 'mode-preset-name-invalid';
    public const string REASON_PRESET_NOT_FOUND = 'mode-preset-not-found';

    private function __construct(
        string $presetName,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            ModuleErrorCodes::CORETSIA_MODE_PRESET_NOT_FOUND,
            $reason,
            [
                'preset' => $presetName,
            ],
            $previous,
        );
    }

    public static function forPreset(
        string $presetName,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            $presetName,
            self::REASON_PRESET_NOT_FOUND,
            $previous,
        );
    }

    public static function invalidPresetName(?\Throwable $previous = null): self
    {
        return new self(
            'invalid',
            self::REASON_PRESET_NAME_INVALID,
            $previous,
        );
    }
}
