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

namespace Coretsia\Tools\Tests\Contract;

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class SpikeDeptracAllowlistPolicyContractTest extends ToolContractTestCase
{
    public function testSrcAllowlistFromSpikeFixtureIsRejectedDeterministically(): void
    {
        $sandbox = $this->createDeptracSandboxFromPackageIndexFixture('deptrac_min/package_index_ok.php');

        $out = $sandbox . '/framework/tools/testing/deptrac.yaml';
        $allowlist = $sandbox . '/framework/tools/testing/deptrac.allowlist.yaml';
        $artifactsDir = $sandbox . '/framework/var/arch';

        $this->writeDeptracAllowlistYamlFromSpikeFixture(
            $allowlist,
            'deptrac_min/allowlist_invalid_src.php',
            normalizeSrcWildcardToRegex: true,
        );

        [$code, $output] = $this->runDeptracGenerate($sandbox, [
            '--out',
            $out,
            '--allowlist',
            $allowlist,
            '--artifacts-dir',
            $artifactsDir,
            '--apply',
        ]);

        self::assertNotSame(0, $code, 'Expected invalid src allowlist to fail.');
        self::assertStringContainsString('CORETSIA_DEPTRAC_ALLOWLIST_INVALID', $output);
        self::assertStringNotContainsString($sandbox, $output, 'Failure output must not leak sandbox absolute paths.');
    }

    public function testTestsOnlyAllowlistFromSpikeFixtureIsAccepted(): void
    {
        $sandbox = $this->createDeptracSandboxFromPackageIndexFixture('deptrac_min/package_index_ok.php');

        $out = $sandbox . '/framework/tools/testing/deptrac.yaml';
        $allowlist = $sandbox . '/framework/tools/testing/deptrac.allowlist.yaml';
        $artifactsDir = $sandbox . '/framework/var/arch';

        $this->writeDeptracAllowlistYamlFromSpikeFixture(
            $allowlist,
            'deptrac_min/allowlist_tests_only.php',
        );

        [$code, $output] = $this->runDeptracGenerate($sandbox, [
            '--out',
            $out,
            '--allowlist',
            $allowlist,
            '--artifacts-dir',
            $artifactsDir,
            '--apply',
        ]);

        self::assertSame(0, $code, "Expected tests-only allowlist to pass.\nOutput:\n" . $output);
        self::assertFileExists($out);
    }
}
