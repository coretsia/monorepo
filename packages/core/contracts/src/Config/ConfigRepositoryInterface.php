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
 * Read-only merged config access port.
 *
 * This interface does not prescribe storage format and does not require
 * filesystem paths. Source tracking, when exposed, must use safe contracts
 * shapes and must not contain raw source values.
 */
interface ConfigRepositoryInterface
{
    /**
     * Returns whether a merged config key exists.
     *
     * Key format is implementation-owned and must remain logical, not a
     * filesystem path contract. Empty-key/root-key semantics, if supported, are
     * implementation-owned.
     */
    public function has(string $keyPath): bool;

    /**
     * Returns a merged config value for the given logical key.
     *
     * The default is returned when the key is missing. The default value is
     * caller-owned and is not source-tracked by this contract.
     */
    public function get(string $keyPath, mixed $default = null): mixed;

    /**
     * Returns the full merged config tree.
     *
     * The returned tree is config data only. It MUST NOT contain executable
     * validators, closures, objects, resources, service instances, or runtime
     * wiring objects.
     *
     * @return array<string,mixed>
     */
    public function all(): array;

    /**
     * Returns safe source metadata for the given logical key, when available.
     *
     * The returned source must not contain raw config/env values.
     */
    public function sourceOf(string $keyPath): ?ConfigValueSource;

    /**
     * Returns a deterministic safe explain trace.
     *
     * Trace entries must not contain raw config values, raw env values, secrets,
     * absolute local paths, timestamps, random values, or host-specific bytes.
     *
     * @return list<ConfigValueSource>
     */
    public function explain(): array;
}
