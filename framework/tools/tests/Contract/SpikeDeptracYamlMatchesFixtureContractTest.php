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

final class SpikeDeptracYamlMatchesFixtureContractTest extends ToolContractTestCase
{
    public function testGeneratedDeptracYamlIsLockedToPromotedSpikeFixture(): void
    {
        $fixtureRel = 'deptrac_min/package_index_ok.php';
        $sandbox = $this->createDeptracSandboxFromPackageIndexFixture($fixtureRel);

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

        self::assertSame(0, $code, "Expected deptrac generation to pass.\nOutput:\n" . $output);
        self::assertFileExists($out);

        $yaml = $this->readBytes($out);
        $fixture = $this->requireArrayFixture($fixtureRel);

        $this->assertYamlIsDeterministic($yaml);
        $this->assertYamlContainsFixturePackagesAndRules($yaml, $fixture);

        self::assertFileExists($artifactsDir . '/deptrac_graph.dot');
        self::assertFileExists($artifactsDir . '/deptrac_graph.svg');
        self::assertFileExists($artifactsDir . '/deptrac_graph.html');

        [$checkCode, $checkOutput] = $this->runDeptracGenerate($sandbox, [
            '--out',
            $out,
            '--allowlist',
            $allowlist,
            '--artifacts-dir',
            $artifactsDir,
            '--check',
        ]);

        self::assertSame(0, $checkCode, "Expected deptrac rerun-no-diff check to pass.\nOutput:\n" . $checkOutput);
    }

    private function assertYamlIsDeterministic(string $yaml): void
    {
        self::assertNotSame('', $yaml);
        self::assertFalse(str_contains($yaml, "\r"), 'Generated deptrac YAML must be LF-only.');
        self::assertSame("\n", substr($yaml, -1), 'Generated deptrac YAML must end with a final newline.');

        self::assertStringNotContainsString($this->repoRoot(), $yaml);
        self::assertStringNotContainsString(sys_get_temp_dir(), $yaml);
        self::assertStringNotContainsString('\\', str_replace('\\\\', '', $yaml));
        self::assertStringContainsString("deptrac:\n", $yaml);
    }

    /**
     * @param array<mixed> $fixture
     */
    private function assertYamlContainsFixturePackagesAndRules(string $yaml, array $fixture): void
    {
        $packages = $fixture['packages'] ?? null;
        self::assertIsArray($packages);

        foreach ($packages as $packageId => $package) {
            self::assertIsArray($package);

            $resolvedPackageId = $package['package_id'] ?? $packageId;
            $deps = $package['deps'] ?? [];

            self::assertIsString($resolvedPackageId);
            self::assertIsArray($deps);

            $layerName = $this->packageIdToLayerName($resolvedPackageId);

            self::assertStringContainsString(
                "- '../../packages/" . $resolvedPackageId . "/src'",
                $yaml,
            );

            self::assertStringContainsString(
                "    - name: '" . $layerName . "'\n",
                $yaml,
            );

            if ($deps === []) {
                self::assertStringContainsString(
                    "    '" . $layerName . "': [ ]\n",
                    $yaml,
                );

                continue;
            }

            self::assertStringContainsString(
                "    '" . $layerName . "':\n",
                $yaml,
            );

            foreach ($deps as $dep) {
                self::assertIsString($dep);
                self::assertStringContainsString(
                    "      - '" . $this->packageIdToLayerName($dep) . "'\n",
                    $yaml,
                );
            }
        }
    }

    private function packageIdToLayerName(string $packageId): string
    {
        return str_replace(['/', '-'], ['.', '_'], $packageId);
    }
}
