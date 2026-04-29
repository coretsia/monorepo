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
    public const string MICRO = 'micro';

    public const string EXPRESS = 'express';

    public const string HYBRID = 'hybrid';

    public const string ENTERPRISE = 'enterprise';

    public function name(): string;

    public function description(): ?string;

    /**
     * Module ids included by the preset.
     *
     * Implementations must return this list in deterministic order unless a
     * future SSoT explicitly defines semantic ordering.
     *
     * @return list<ModuleId>
     */
    public function moduleIds(): array;

    /**
     * Deterministic JSON-like preset metadata.
     *
     * @return array<string,mixed>
     */
    public function metadata(): array;
}
