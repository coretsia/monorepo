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

namespace Coretsia\Foundation\Container\Definition;

/**
 * Foundation declarative container-definition provider SPI.
 *
 * This interface is intentionally owned by `core/foundation`, not
 * `core/contracts`. It is coupled to the Foundation DI runtime, its definition
 * model, provider ordering, binding collision policy, tag dedupe policy, and
 * shared/non-shared lifecycle semantics. It is therefore not a framework-wide
 * technology-neutral port.
 *
 * Implementations MUST be deterministic for the same already-compiled config
 * snapshot and provider state. They MUST NOT return closures or runtime
 * objects, read filesystem or environment sources, resolve services, start
 * runtime lifecycle, emit stdout/stderr, or read generated artifacts.
 */
interface ContainerDefinitionProviderInterface
{
    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void;
}
