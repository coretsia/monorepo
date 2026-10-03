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
 * Deterministic module conflict failure.
 *
 * Used when module graph policy detects incompatible enabled modules or an
 * enabled module requires an excluded module.
 *
 * Diagnostics intentionally expose only deterministic module ids and stable
 * reason tokens. They must not expose raw Composer metadata, raw preset
 * payloads, filesystem paths, service internals, or previous throwable
 * messages.
 *
 * @internal
 */
final class ModuleConflictException extends ModuleResolutionException
{
    public const string REASON_MODULE_CONFLICT = 'module-conflict';
    public const string REASON_DEPENDENCY_EXCLUDED = 'dependency-excluded';

    private function __construct(
        string $reason,
        array $context,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            ModuleErrorCodes::CORETSIA_MODULE_CONFLICT,
            $reason,
            $context,
            $previous,
        );
    }

    public static function between(
        ModuleId $firstModuleId,
        ModuleId $secondModuleId,
        ?\Throwable $previous = null,
    ): self {
        [$lowerModuleId, $higherModuleId] = self::sortedPair(
            $firstModuleId->value(),
            $secondModuleId->value(),
        );

        return new self(
            self::REASON_MODULE_CONFLICT,
            [
                'lowerModuleId' => $lowerModuleId,
                'higherModuleId' => $higherModuleId,
            ],
            $previous,
        );
    }

    public static function dependencyExcluded(
        ModuleId $requiredByModuleId,
        ModuleId $excludedModuleId,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_DEPENDENCY_EXCLUDED,
            [
                'requiredByModuleId' => $requiredByModuleId->value(),
                'excludedModuleId' => $excludedModuleId->value(),
            ],
            $previous,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function sortedPair(string $first, string $second): array
    {
        if (\strcmp($first, $second) <= 0) {
            return [$first, $second];
        }

        return [$second, $first];
    }
}
