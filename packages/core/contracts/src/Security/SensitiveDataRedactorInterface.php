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
 * Contracts-level port for deterministic sensitive-data redaction.
 *
 * Direct string redaction requires an owner-selected RedactionKind. Recursive
 * json-like redaction MUST use the canonical platform key/value classification
 * policy; callers cannot supply callbacks, mutable policy registries,
 * configuration, observability, runtime context, or service-locator inputs.
 *
 * Neither operation writes to stdout or stderr. Once a string value or complete
 * json-like branch has been selected for redaction, the operation MUST NOT
 * return that original value or branch.
 *
 * Redaction is defense in depth. Consumers remain responsible for emitting
 * safe-by-construction shapes and for enforcing their destination-boundary
 * schema, semantic-key, path, cardinality, and resource-limit policies.
 */
interface SensitiveDataRedactorInterface
{
    /**
     * Redacts a string value already known by its owner to be sensitive.
     *
     * @throws \Coretsia\Contracts\Security\Exception\RedactionException
     */
    public function redactValue(
        string $value,
        RedactionKind $kind,
        RedactionContext $context,
    ): RedactedValue;

    /**
     * Redacts a recursively json-like runtime value.
     *
     * The returned value contains only null, bool, int, string, list values, or
     * string-keyed maps recursively. Floats, objects, closures, resources, and
     * non-string map keys are outside this contract.
     *
     * @return null|bool|int|string|array<int|string, mixed>
     *
     * @throws \Coretsia\Contracts\Security\Exception\RedactionException
     */
    public function redactJsonLike(mixed $value, RedactionContext $context): mixed;
}
