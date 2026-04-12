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

namespace Coretsia\Devtools\CliSpikes\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class SpikeFingerprintCommandIsThinAdapterTest extends TestCase
{
    public function testSpikeFingerprintCommandDelegatesToWorkflowAndDoesNotEmbedFingerprintLogic(): void
    {
        $packageRoot = \dirname(__DIR__, 2);
        $file = $packageRoot . '/src/Command/SpikeFingerprintCommand.php';

        self::assertFileExists($file, 'SpikeFingerprintCommand.php MUST exist.');

        $code = \file_get_contents($file);
        self::assertIsString($code, 'Read failed for SpikeFingerprintCommand.php');

        self::assertStringContainsString(
            'FingerprintWorkflow',
            $code,
            'SpikeFingerprintCommand MUST delegate to tools-side FingerprintWorkflow.'
        );

        $forbidden = [
            'FingerprintCalculator',
            'tryForwardDeterministicFailure',
            'isSafeOneLineToken',
            'calculate(false)',
            'loadTrackedEnvAllowlist',
            'computeTrackedEnvSnapshot',
            'digestTrackedEnvBucket',
            'digestFilesBucket',
            'hashDotenvFilesToSnapshot',
            'hashFilesToSnapshot',
        ];

        foreach ($forbidden as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $code,
                'SpikeFingerprintCommand MUST NOT embed fingerprint spike logic: forbidden token "' . $needle . '" found.'
            );
        }
    }
}
