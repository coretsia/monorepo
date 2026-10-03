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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModuleGraphResolverRejectsMissingSelectedRootTest extends TestCase
{
    public function testMultipleMissingRootsFailWithSmallestIdRegardlessOfInputOrder(): void
    {
        $manifest = ModeInfrastructureTestSupport::manifest(
            [ModeInfrastructureTestSupport::descriptor('core.foundation')],
        );
        foreach ([['platform.worker', 'platform.http'], ['platform.http', 'platform.worker']] as $order) {
            $ids = ModeInfrastructureTestSupport::ids($order);
            \usort($ids, static fn ($a, $b): int => \strcmp($a->value(), $b->value()));
            try {
                new ModuleGraphResolver(new TopologicalSorter())->resolve(
                    app: 'api',
                    installed: $manifest,
                    selection: new ModuleSelection($ids, []),
                );
                self::fail('Selected root absent from installed manifest must fail.');
            } catch (ModuleRequiredMissingException $exception) {
                self::assertSame(
                    ModuleRequiredMissingException::REASON_SELECTED_ROOT_MODULE_MISSING,
                    $exception->reason(),
                );
                self::assertSame(['missingModuleId' => 'platform.http'], $exception->context());
            }
        }
    }
}
