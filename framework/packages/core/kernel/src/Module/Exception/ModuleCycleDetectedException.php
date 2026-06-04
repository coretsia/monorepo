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
 * Deterministic module graph cycle failure.
 *
 * Used when enabled module dependency edges contain a cycle.
 *
 * Diagnostics intentionally expose only deterministic module ids and a stable
 * reason token. They must not expose graph dumps, raw metadata payloads,
 * filesystem paths, Composer payloads, or service internals.
 *
 * @internal
 */
final class ModuleCycleDetectedException extends ModuleResolutionException
{
    public const string REASON_CYCLE_DETECTED = 'module-cycle-detected';

    /**
     * @param list<ModuleId> $moduleIds
     */
    private function __construct(
        array $moduleIds,
        ?\Throwable $previous = null,
    ) {
        $normalizedModuleIds = self::normalizeModuleIds($moduleIds);

        parent::__construct(
            ModuleErrorCodes::CORETSIA_MODULE_CYCLE_DETECTED,
            self::REASON_CYCLE_DETECTED,
            [
                'moduleIds' => $normalizedModuleIds,
            ],
            $previous,
        );
    }

    /**
     * @param list<ModuleId> $moduleIds
     */
    public static function forModules(
        array $moduleIds,
        ?\Throwable $previous = null,
    ): self {
        return new self($moduleIds, $previous);
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function normalizeModuleIds(array $moduleIds): array
    {
        if ($moduleIds === []) {
            throw new \InvalidArgumentException('module-cycle-module-ids-empty');
        }

        $normalized = [];

        foreach ($moduleIds as $moduleId) {
            if (!$moduleId instanceof ModuleId) {
                throw new \InvalidArgumentException('module-cycle-module-id-invalid');
            }

            $normalized[] = $moduleId->value();
        }

        $normalized = \array_values(\array_unique($normalized));

        \usort(
            $normalized,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $normalized;
    }
}
