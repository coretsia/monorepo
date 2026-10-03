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
use Coretsia\Kernel\DependencySync\ProjectInstallationIntent;
use PHPUnit\Framework\TestCase;

final class ProjectInstallationIntentTest extends TestCase
{
    public function testKeepsOnlyExplicitPresetOverridesAndSortsThem(): void
    {
        $applications = new ProjectApplicationSet([
            AppTarget::Worker,
            AppTarget::Web,
        ]);

        $intent = new ProjectInstallationIntent(
            $applications,
            [
                'worker' => 'enterprise',
                'web' => 'micro',
            ],
        );

        self::assertSame(
            [
                'web' => 'micro',
                'worker' => 'enterprise',
            ],
            $intent->fixedPresetByTarget(),
        );
        self::assertSame($applications, $intent->applications());
    }

    public function testRejectsMalformedTargetMapAndUnsafePresetTokens(): void
    {
        $applications = new ProjectApplicationSet([AppTarget::Web]);

        foreach (
            [
                [0 => 'micro'],
                ['worker' => 'enterprise'],
                ['web' => 1],
                ['web' => ''],
                ['web' => ' micro'],
                ['web' => "micro\n"],
            ] as $invalid
        ) {
            try {
                new ProjectInstallationIntent($applications, $invalid);
                self::fail('Expected invalid installation intent.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID,
                    $exception->errorCode(),
                );
            }
        }
    }
}
