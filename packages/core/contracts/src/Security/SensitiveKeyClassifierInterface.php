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

namespace Coretsia\Contracts\Security;

/**
 * Read-only contracts-level port for canonical sensitive-key classification.
 *
 * Implementations use the canonical platform.redaction classification policy,
 * including ASCII-only case folding and removal of ASCII hyphen, underscore,
 * dot, and space separators for alias lookup. The alias vocabulary and
 * classification implementation remain owned by platform.redaction.
 *
 * Classification examines only the supplied structural key. Consumers of
 * composite key paths are responsible for their own path validation and for
 * classifying relevant path segments separately.
 *
 * A null result means that the key is unclassified, NOT that it is safe for
 * emission. Consumers retain destination-specific schema, semantic-key, path,
 * cardinality, resource-limit, and mandatory redaction requirements.
 *
 * This port does not expose mutable policy registries, configuration, runtime
 * context, service-locator operations, or redaction behavior.
 */
interface SensitiveKeyClassifierInterface
{
    /**
     * Classifies a structural key using the canonical sensitive-key policy.
     *
     * Returns null when the key has no matching canonical classification.
     * A non-match does not authorize exposing the key or its associated value.
     */
    public function classify(string $key): ?RedactionKind;
}
