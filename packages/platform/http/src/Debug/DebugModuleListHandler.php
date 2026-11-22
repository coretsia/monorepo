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

namespace Coretsia\Platform\Http\Debug;

use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Psr\Http\Message\ResponseInterface;

/**
 * Diagnostic projection of the already resolved enabled runtime module IDs.
 *
 * The canonical ModulePlan owns uniqueness and byte-order sorting.
 *
 * @internal
 */
final readonly class DebugModuleListHandler
{
    public function __construct(
        private ModulePlan $modulePlan,
        private FallbackResponses $responses,
    ) {
    }

    public function handle(): ResponseInterface
    {
        $modules = [];
        foreach ($this->modulePlan->enabled() as $moduleId) {
            $modules[] = $moduleId->value();
        }

        return $this->responses->debugJson(['modules' => $modules]);
    }
}
