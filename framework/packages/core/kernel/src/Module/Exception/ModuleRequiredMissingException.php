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

use Coretsia\Contracts\Module\ModuleId;

/**
 * Deterministic required module missing failure.
 *
 * Used when a preset-required module or a transitive required dependency is
 * missing from the installed runtime module manifest.
 *
 * Diagnostics intentionally expose only deterministic module ids, preset names,
 * and stable reason tokens. They must not expose raw Composer metadata, raw
 * preset payloads, filesystem paths, or previous throwable messages.
 *
 * @internal Catch ModuleResolutionException and inspect errorCode()/reason()
 * instead of depending on concrete Kernel module-resolution exception classes.
 *
 * @internal
 */
final class ModuleRequiredMissingException extends ModuleResolutionException
{
    public const string REASON_PRESET_REQUIRED_MODULE_MISSING = 'preset-required-module-missing';
    public const string REASON_DEPENDENCY_REQUIRED_MODULE_MISSING = 'dependency-required-module-missing';

    private function __construct(
        string $reason,
        array $context,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            ModuleErrorCodes::CORETSIA_MODULE_REQUIRED_MISSING,
            $reason,
            $context,
            $previous,
        );
    }

    public static function presetRequiredModuleMissing(
        string $presetName,
        ModuleId $missingModuleId,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_PRESET_REQUIRED_MODULE_MISSING,
            [
                'preset' => $presetName,
                'missingModuleId' => $missingModuleId->value(),
            ],
            $previous,
        );
    }

    public static function dependencyRequiredModuleMissing(
        ModuleId $requiredByModuleId,
        ModuleId $missingModuleId,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_DEPENDENCY_REQUIRED_MODULE_MISSING,
            [
                'requiredByModuleId' => $requiredByModuleId->value(),
                'missingModuleId' => $missingModuleId->value(),
            ],
            $previous,
        );
    }
}
