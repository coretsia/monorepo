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
 * @internal
 */
final class InvalidModuleSelectionException extends ModuleResolutionException
{
    public const string REASON_REQUIRED_EXCLUDED = 'module-selection-required-excluded';
    public const string REASON_OVERLAP = 'module-selection-overlap';
    public const string REASON_ROOTS_INVALID = 'module-selection-roots-invalid';
    public const string REASON_INCLUDE_INVALID = 'module-selection-include-invalid';
    public const string REASON_EXCLUDED_INVALID = 'module-selection-excluded-invalid';

    public static function withReason(string $reason, array $context = []): self
    {
        if (!\in_array(
            $reason,
            [
                self::REASON_REQUIRED_EXCLUDED,
                self::REASON_OVERLAP,
                self::REASON_ROOTS_INVALID,
                self::REASON_INCLUDE_INVALID,
                self::REASON_EXCLUDED_INVALID,
            ],
            true,
        )) {
            throw new \InvalidArgumentException('module-selection-reason-invalid');
        }
        return new self(ModuleErrorCodes::CORETSIA_MODULE_SELECTION_INVALID, $reason, $context);
    }
}
