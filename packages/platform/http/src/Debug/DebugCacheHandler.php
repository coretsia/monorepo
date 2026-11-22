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

use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Psr\Http\Message\ResponseInterface;

/**
 * Diagnostic projection of the current runtime's boot-selected generation.
 *
 * No artifact discovery or filesystem inspection is performed.
 *
 * @internal
 */
final readonly class DebugCacheHandler
{
    public function __construct(
        private RuntimeDiagnosticsInput $runtime,
        private FallbackResponses $responses,
    ) {
    }

    public function handle(): ResponseInterface
    {
        $status = $this->runtime->artifactGenerationLoaded() ? 'loaded' : 'unavailable';

        return $this->responses->debugJson([
            'artifacts' => [
                'config' => $status,
                'container' => $status,
                'generation-manifest' => $status,
                'module-manifest' => $status,
            ],
        ]);
    }
}
