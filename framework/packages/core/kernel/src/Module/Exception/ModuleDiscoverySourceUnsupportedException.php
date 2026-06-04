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
 * Deterministic unsupported module discovery source failure.
 *
 * Used when `kernel.modules.discovery.source` selects a source that is not
 * present in `kernel.modules.discovery.allowed_sources`.
 *
 * Diagnostics intentionally expose only the safe selected source and safe
 * allowlisted source tokens. They must not expose raw config payloads,
 * filesystem paths, Composer payloads, service internals, or previous
 * throwable messages.
 *
 * @internal
 */
final class ModuleDiscoverySourceUnsupportedException extends ModuleResolutionException
{
    public const string REASON_DISCOVERY_SOURCE_UNSUPPORTED = 'module-discovery-source-unsupported';

    /**
     * @param list<string> $allowedSources
     */
    private function __construct(
        string $source,
        array $allowedSources,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            ModuleErrorCodes::CORETSIA_MODULE_DISCOVERY_SOURCE_UNSUPPORTED,
            self::REASON_DISCOVERY_SOURCE_UNSUPPORTED,
            [
                'source' => $source,
                'allowedSources' => self::normalizeAllowedSources($allowedSources),
            ],
            $previous,
        );
    }

    /**
     * @param list<string> $allowedSources
     */
    public static function forSource(
        string $source,
        array $allowedSources,
        ?\Throwable $previous = null,
    ): self {
        return new self($source, $allowedSources, $previous);
    }

    /**
     * @param list<string> $allowedSources
     *
     * @return list<string>
     */
    private static function normalizeAllowedSources(array $allowedSources): array
    {
        $normalized = \array_values(\array_unique($allowedSources));

        \usort(
            $normalized,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $normalized;
    }
}
