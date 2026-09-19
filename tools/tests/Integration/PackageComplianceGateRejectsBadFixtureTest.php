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

final class PackageComplianceGateRejectsBadFixtureTest extends ToolContractTestCase
{
    public function testBadFixtureFailsWithDeterministicDiagnostics(): void
    {
        $repoRoot = $this->createPackageComplianceSandbox('package_bad');

        [$code, $output] = $this->runPackageComplianceGate($repoRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_PACKAGE_COMPLIANCE_VIOLATION\n"
            . "packages/core/broken-library/LICENSE: legal-file-drift\n"
            . "packages/core/broken-library/README.md: missing-required-file\n"
            . "packages/platform/broken-runtime/composer.json: missing-runtime-metadata-defaultsConfigPath\n"
            . "packages/platform/broken-runtime/composer.json: missing-runtime-metadata-moduleClass\n"
            . "packages/platform/broken-runtime/composer.json: missing-runtime-metadata-moduleId\n"
            . "packages/platform/broken-runtime/composer.json: missing-runtime-metadata-providers\n"
            . "packages/platform/broken-runtime/config/rules.php: missing-runtime-file\n",
            $output,
        );

        $this->assertDiagnosticsAreRelativeAndSorted($output);
    }

    public function testAllowlistIsLoadedDeterministicallyAndSuppressesAllowlistedPackage(): void
    {
        $repoRoot = $this->createPackageComplianceSandbox('package_bad');

        $allowlistPath = $this->writeTemporaryAllowlist(
            $repoRoot,
            [
                'core/broken-library',
            ],
        );

        [$code, $output] = $this->runPackageComplianceGate(
            $repoRoot,
            $allowlistPath,
        );

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_PACKAGE_COMPLIANCE_VIOLATION\n"
            . "packages/platform/broken-runtime/composer.json: missing-runtime-metadata-defaultsConfigPath\n"
            . "packages/platform/broken-runtime/composer.json: missing-runtime-metadata-moduleClass\n"
            . "packages/platform/broken-runtime/composer.json: missing-runtime-metadata-moduleId\n"
            . "packages/platform/broken-runtime/composer.json: missing-runtime-metadata-providers\n"
            . "packages/platform/broken-runtime/config/rules.php: missing-runtime-file\n",
            $output,
        );

        self::assertStringNotContainsString('packages/core/broken-library/', $output);
        $this->assertDiagnosticsAreRelativeAndSorted($output);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runPackageComplianceGate(
        string $repoRoot,
        ?string $allowlistPath = null,
    ): array {
        $args = [
            '--repo-root=' . $repoRoot,
            '--path=' . $repoRoot . '/packages',
        ];

        if ($allowlistPath !== null) {
            $args[] = '--allowlist=' . $allowlistPath;
        }

        return $this->runPhp(
            $this->repoRoot()
            . '/tools/gates/package_compliance_gate.php',
            $args,
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

    private function assertDiagnosticsAreRelativeAndSorted(string $output): void
    {
        $lines = \explode("\n", \trim($output));

        self::assertNotSame([], $lines);
        self::assertSame('CORETSIA_PACKAGE_COMPLIANCE_VIOLATION', $lines[0]);

        $diagnostics = \array_slice($lines, 1);

        self::assertNotSame([], $diagnostics);

        foreach ($diagnostics as $diagnostic) {
            self::assertStringStartsWith('packages/', $diagnostic);
            self::assertStringNotContainsString('\\', $diagnostic);
            self::assertDoesNotMatchRegularExpression('/\A\//', $diagnostic);
            self::assertDoesNotMatchRegularExpression('/\A[A-Za-z]:[\/\\\\]/', $diagnostic);
        }

        $sorted = $diagnostics;
        \sort($sorted, \SORT_STRING);

        self::assertSame($sorted, $diagnostics);
    }

    /**
     * @param list<string> $packageIds
     */
    private function writeTemporaryAllowlist(
        string $repoRoot,
        array $packageIds,
    ): string {
        \sort($packageIds, \SORT_STRING);

        $path = $repoRoot . '/var/phpunit/package-compliance-allowlist.php';

        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0777, true);
        }

        if (\is_file($path) || \is_link($path)) {
            \unlink($path);
        }

        $this->writeBytesExact(
            $path,
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . $this->renderAllowlistEntries($packageIds)
            . "];\n",
        );

        return $path;
    }

    /**
     * @param list<string> $packageIds
     */
    private function renderAllowlistEntries(array $packageIds): string
    {
        $lines = [];

        foreach ($packageIds as $packageId) {
            $lines[] = '    ' . \var_export($packageId, true) . ',';
        }

        return $lines === [] ? '' : \implode("\n", $lines) . "\n";
    }
}
