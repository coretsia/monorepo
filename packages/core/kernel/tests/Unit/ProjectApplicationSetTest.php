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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectApplicationSet;
use PHPUnit\Framework\TestCase;

final class ProjectApplicationSetTest extends TestCase
{
    public function testCanonicalizesExplicitTargetsByByteOrder(): void
    {
        $set = new ProjectApplicationSet([
            AppTarget::Worker,
            AppTarget::Web,
            AppTarget::Api,
        ]);

        self::assertSame(
            [AppTarget::Api, AppTarget::Web, AppTarget::Worker],
            $set->targets(),
        );
    }

    public function testRejectsEmptyNonListInvalidAndDuplicateTargets(): void
    {
        foreach (
            [
                [],
                ['web' => AppTarget::Web],
                [AppTarget::Web, 'web'],
                [AppTarget::Web, AppTarget::Web],
            ] as $invalid
        ) {
            try {
                new ProjectApplicationSet($invalid);
                self::fail('Expected invalid application set.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::APPLICATION_SET_INVALID,
                    $exception->errorCode(),
                );
            }
        }
    }
}
