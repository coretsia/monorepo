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

namespace Coretsia\Platform\Routing\Compile;

use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;

/**
 * Validates complete route-template domains against all reserved HTTP ownership.
 *
 * Ownership is independent of activation, environment, status and HTTP method.
 * The same guard is called for source compilation and artifact hydration.
 *
 * @internal
 */
final class ReservedRoutesGuard
{
    public function assertAllowed(RouteTemplate $template): void
    {
        foreach (SystemRouteTable::entries() as $entry) {
            $pattern = $entry['pattern'];
            $prefix = $entry['type'] === 'prefix';
            $segments = $pattern === '/' ? [] : \explode('/', \trim($pattern, '/'));
            $required = \count($segments);

            if ($prefix ? $template->segmentCount() <= $required : $template->segmentCount() !== $required) {
                continue;
            }

            $witness = [];
            $intersects = true;
            foreach ($segments as $index => $segment) {
                if (!$template->segmentMatches($index, $segment)) {
                    $intersects = false;
                    break;
                }
                $witness[] = $segment;
            }

            if (!$intersects) {
                continue;
            }

            for ($index = $required; $index < $template->segmentCount(); $index++) {
                $witness[] = $template->representativeSegment($index);
            }

            $path = $witness === [] ? '/' : '/' . \implode('/', $witness);
            if (ReservedPathMatcher::match($path) !== null) {
                throw ReservedRouteViolationException::forbidden();
            }
        }
    }
}
