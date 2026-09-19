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

namespace Coretsia\Tools\Tests\Integration;

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class PackageComplianceGateAcceptsGoodFixtureTest extends ToolContractTestCase
{
    public function testGoodFixturePasses(): void
    {
        $repoRoot = $this->createPackageComplianceSandbox('package_good');

        [$code, $output] = $this->runPackageComplianceGate($repoRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runPackageComplianceGate(
        string $repoRoot,
    ): array {
        return $this->runPhp(
            $this->repoRoot()
            . '/tools/gates/package_compliance_gate.php',
            [
                '--repo-root=' . $repoRoot,
                '--path=' . $repoRoot . '/packages',
            ],
            $repoRoot,
        );
    }

    private function createPackageComplianceSandbox(
        string $name,
    ): string {
        $repoRoot = $this->tempDir('package-compliance-' . $name);

        $this->copyDir(
            $this->fixturePath($name),
            $repoRoot,
        );
        $this->ensureDir($repoRoot . '/tools/config');

        $this->writeBytesExact(
            $repoRoot . '/composer.json',
            "{\n"
            . "  \"name\": \"coretsia/package-compliance-fixture\",\n"
            . "  \"type\": \"project\"\n"
            . "}\n",
        );

        foreach (['LICENSE', 'NOTICE', 'SECURITY.md'] as $file) {
            $this->writeBytesExact(
                $repoRoot . '/' . $file,
                $this->readBytes(
                    $this->repoRoot() . '/' . $file,
                ),
            );
        }

        $this->writeBytesExact(
            $repoRoot
            . '/tools/config/package_compliance_allowlist.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "return [];\n",
        );

        return $repoRoot;
    }
}
