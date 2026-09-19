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

namespace Coretsia\Contracts\Module;

/**
 * Accessor for a loaded mode preset.
 *
 * Presets are format-neutral. This interface must not expose whether the source
 * was PHP, JSON, YAML, Composer metadata, or a generated artifact.
 */
interface ModePresetInterface
{
    public const int SCHEMA_VERSION = 1;

    public const string MICRO = 'micro';
    public const string EXPRESS = 'express';
    public const string HYBRID = 'hybrid';
    public const string ENTERPRISE = 'enterprise';

    public function schemaVersion(): int;

    /**
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * @return non-empty-string|null
     */
    public function description(): ?string;

    /**
     * Required module ids.
     *
     * @return list<ModuleId>
     */
    public function required(): array;

    /**
     * Optional module ids.
     *
     * @return list<ModuleId>
     */
    public function optional(): array;

    /**
     * Explicitly disabled module ids.
     *
     * @return list<ModuleId>
     */
    public function disabled(): array;

    /**
     * Compatibility projection of enabled preset module ids.
     *
     * SHOULD be derived from required + optional, excluding disabled,
     * sorted by module id value using byte-order strcmp.
     *
     * @return list<ModuleId>
     */
    public function moduleIds(): array;

    /**
     * Schema-owned policy knobs.
     *
     * @return array<string,mixed>
     */
    public function featureBundles(): array;

    /**
     * Deterministic JSON-like preset metadata.
     *
     * @return array<string,mixed>
     */
    public function metadata(): array;

    /**
     * Stable exported scalar/json-like shape.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array;
}
