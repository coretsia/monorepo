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
 * Canonical Foundation container-definition operation kinds.
 *
 * Values intentionally match the descriptor kinds consumed by the Kernel
 * container compiler. The enum itself is an in-memory Foundation model and is
 * not an artifact schema contract.
 */
enum ContainerDefinitionKind: string
{
    case SERVICE_CLASS = 'service.class';
    case SERVICE_FACTORY_CLASS_METHOD = 'service.factory.class-method';
    case SERVICE_FACTORY_SERVICE_METHOD = 'service.factory.service-method';
    case ALIAS = 'alias';
    case PARAMETER = 'parameter';
    case TAG = 'tag';
}
