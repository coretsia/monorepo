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

namespace Coretsia\Kernel\Config\Source;

/**
 * Immutable canonical config-source input for one Kernel compile operation.
 *
 * @internal
 */
final readonly class ConfigSourceSet
{
    /**
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $packageDefaultSources
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $packageRuleSources
     * @param list<non-empty-string> $splitRoots
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId?: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $explicitRuleSources
     * @param list<array{
     *     path: string,
     *     env: string,
     *     type: string,
     *     sourceId?: string|null,
     *     precedence?: int|null,
     *     allowedValues?: list<null|bool|int|string>
     * }> $explicitEnvOverlayMappings
     * @param list<array{
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int|null
     * }> $modePresetSourceCandidates
     */
    public function __construct(
        private array $packageDefaultSources,
        private array $packageRuleSources,
        private array $splitRoots,
        private array $explicitRuleSources,
        private array $explicitEnvOverlayMappings,
        private array $modePresetSourceCandidates,
    ) {
        self::assertList(
            $this->packageDefaultSources,
            'config-source-set-package-default-sources-must-be-list',
        );
        self::assertList(
            $this->packageRuleSources,
            'config-source-set-package-rule-sources-must-be-list',
        );
        self::assertList(
            $this->splitRoots,
            'config-source-set-split-roots-must-be-list',
        );
        self::assertList(
            $this->explicitRuleSources,
            'config-source-set-explicit-rule-sources-must-be-list',
        );
        self::assertList(
            $this->explicitEnvOverlayMappings,
            'config-source-set-explicit-env-overlay-mappings-must-be-list',
        );
        self::assertList(
            $this->modePresetSourceCandidates,
            'config-source-set-mode-preset-source-candidates-must-be-list',
        );
    }

    public static function empty(): self
    {
        return new self(
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: [],
        );
    }

    /**
     * @return list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }>
     */
    public function packageDefaultSources(): array
    {
        return $this->packageDefaultSources;
    }

    /**
     * @return list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }>
     */
    public function packageRuleSources(): array
    {
        return $this->packageRuleSources;
    }

    /**
     * @return list<non-empty-string>
     */
    public function splitRoots(): array
    {
        return $this->splitRoots;
    }

    /**
     * @return list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId?: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }>
     */
    public function explicitRuleSources(): array
    {
        return $this->explicitRuleSources;
    }

    /**
     * @return list<array{
     *     path: string,
     *     env: string,
     *     type: string,
     *     sourceId?: string|null,
     *     precedence?: int|null,
     *     allowedValues?: list<null|bool|int|string>
     * }>
     */
    public function explicitEnvOverlayMappings(): array
    {
        return $this->explicitEnvOverlayMappings;
    }

    /**
     * @return list<array{
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int|null
     * }>
     */
    public function modePresetSourceCandidates(): array
    {
        return $this->modePresetSourceCandidates;
    }

    private static function assertList(array $value, string $reason): void
    {
        if (!\array_is_list($value)) {
            throw new \InvalidArgumentException($reason);
        }
    }
}
