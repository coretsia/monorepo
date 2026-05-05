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

namespace Coretsia\Foundation\Container;

/**
 * Foundation service provider contract.
 *
 * This contract is intentionally owned by `core/foundation`, not
 * `core/contracts`, because it is coupled to the Foundation DI runtime and its
 * deterministic registration semantics.
 *
 * Provider order is significant:
 *
 * - `ContainerBuilder` must preserve the caller-supplied provider order;
 * - later container definitions override earlier definitions deterministically;
 * - tag registration remains independent and `TagRegistry` keeps first
 *   occurrence per `(tag, serviceId)`.
 *
 * Implementations must not emit stdout/stderr and must not read tooling-only
 * packages or generated architecture artifacts.
 */
interface ServiceProviderInterface
{
    /**
     * Registers container definitions and tag registrations into the builder.
     *
     * Registration must be deterministic for the same provider state and must
     * not depend on filesystem traversal order, locale collation, environment
     * dumps, or reflection side effects outside explicit builder behavior.
     */
    public function register(ContainerBuilder $builder): void;
}
