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

use Coretsia\Kernel\DependencySync\DependencySyncExecutionPolicy;
use PHPUnit\Framework\TestCase;

final class DependencySyncExecutionPolicyTest extends TestCase
{
    public function testDefaultsAreFailClosedAndFlagsRemainExplicit(): void
    {
        $defaults = new DependencySyncExecutionPolicy(false);

        self::assertFalse($defaults->apply());
        self::assertFalse($defaults->allowComposerScripts());
        self::assertFalse($defaults->allowComposerPlugins());
        self::assertFalse($defaults->allowBroadUpdate());
        self::assertFalse($defaults->allowRepair());

        $authorized = new DependencySyncExecutionPolicy(
            apply: true,
            allowComposerScripts: true,
            allowComposerPlugins: true,
            allowBroadUpdate: true,
            allowRepair: true,
        );

        self::assertTrue($authorized->apply());
        self::assertTrue($authorized->allowComposerScripts());
        self::assertTrue($authorized->allowComposerPlugins());
        self::assertTrue($authorized->allowBroadUpdate());
        self::assertTrue($authorized->allowRepair());
    }
}
