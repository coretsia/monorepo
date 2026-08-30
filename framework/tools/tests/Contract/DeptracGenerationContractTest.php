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

final class DeptracGenerationContractTest extends ToolContractTestCase
{
    public function testGeneratedDeptracYamlAndGraphArtifactsMatchCanonicalFixture(): void
    {
        $fixtureRel = 'Deptrac/dependency_graph_ok.php';
        $sandbox = $this->createDeptracSandboxFromDependencyGraphFixture($fixtureRel);

        $out = $sandbox . '/framework/tools/testing/deptrac.yaml';
        $allowlist = $sandbox . '/framework/tools/testing/deptrac.allowlist.yaml';
        $artifactsDir = $sandbox . '/framework/var/arch';

        $this->writeDeptracAllowlistYamlFromFixture(
            $allowlist,
            'Deptrac/allowlist_tests_only.php',
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
        $fixture = $this->requireCanonicalArrayFixture($fixtureRel);

        $this->assertYamlIsDeterministic($yaml);
        $this->assertYamlContainsFixturePackagesAndRules($yaml, $fixture);

        self::assertFileExists($artifactsDir . '/deptrac_graph.dot');
        self::assertFileExists($artifactsDir . '/deptrac_graph.svg');
        self::assertFileExists($artifactsDir . '/deptrac_graph.html');

        foreach (
            [
                'deptrac_graph.dot',
                'deptrac_graph.svg',
                'deptrac_graph.html',
            ] as $artifactName
        ) {
            $artifact = $this->readBytes(
                $artifactsDir . '/' . $artifactName,
            );

            self::assertNotSame(
                '',
                $artifact,
                $artifactName . ': must not be empty.',
            );

            self::assertFalse(
                \str_contains($artifact, "\r"),
                $artifactName . ': must be LF-only.',
            );

            self::assertSame(
                "\n",
                \substr($artifact, -1),
                $artifactName . ': must end with final newline.',
            );

            self::assertStringNotContainsString(
                \str_replace('\\', '/', $sandbox),
                \str_replace('\\', '/', $artifact),
                $artifactName . ': must not contain sandbox absolute root.',
            );

            self::assertDoesNotMatchRegularExpression(
                '~/(?:home|users)/~i',
                $artifact,
                $artifactName . ': must not contain POSIX home absolute path.',
            );

            self::assertDoesNotMatchRegularExpression(
                '~(?i)\b[A-Z]:[\\\\/]~',
                $artifact,
                $artifactName . ': must not contain Windows drive absolute path.',
            );

            self::assertDoesNotMatchRegularExpression(
                '~\\\\{2}[^\\\\/\r\n]+[\\\\/]+[^\\\\/\r\n]+~',
                $artifact,
                $artifactName . ': must not contain UNC absolute path.',
            );
        }

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

    public function testCanonicalGeneratorDoesNotInvokeExternalProcessFunctions(): void
    {
        $source = $this->readBytes(
            $this->frameworkRoot()
            . '/tools/build/deptrac_generate.php',
        );

        self::assertNotSame('', $source);

        foreach (
            [
                'exec',
                'shell_exec',
                'system',
                'passthru',
                'proc_open',
                'popen',
            ] as $function
        ) {
            self::assertDoesNotMatchRegularExpression(
                '~\b'
                . \preg_quote($function, '~')
                . '\s*\(~',
                $source,
                'Forbidden process execution call: ' . $function,
            );
        }
    }

    public function testCanonicalGeneratorRejectsUnsafeSsotDependencyIdentifiersDeterministicallyWithoutLeak(): void
    {
        foreach (
            [
                'backslash' => 'demo\\pkg-b',
                'posix-home-segment' => 'demo/home/user',
                'windows-drive' => 'C:/tmp/secret',
            ] as $case => $unsafeDependency
        ) {
            $sandbox = $this->createDeptracSandboxFromDependencyGraphFixture('Deptrac/dependency_graph_ok.php');

            $dependencyTable = $sandbox . '/docs/roadmap/phase0/00_2-dependency-table.md';
            $out = $sandbox . '/framework/tools/testing/deptrac.yaml';
            $allowlist = $sandbox . '/framework/tools/testing/deptrac.allowlist.yaml';
            $artifactsDir = $sandbox . '/framework/var/arch';

            $this->writeDeptracAllowlistYamlFromFixture(
                $allowlist,
                'Deptrac/allowlist_tests_only.php',
            );

            $this->writeBytesExact(
                $dependencyTable,
                "# Dependency table\n\n"
                . "| package_id | depends_on | notes |\n"
                . "|---|---|---|\n"
                . "| demo/pkg-a | demo/pkg-b, "
                . $unsafeDependency
                . " | |\n"
                . "| demo/pkg-b | — | |\n",
            );

            $args = [
                '--out',
                $out,
                '--allowlist',
                $allowlist,
                '--artifacts-dir',
                $artifactsDir,
                '--apply',
            ];

            [$firstCode, $firstOutput] = $this->runDeptracGenerate(
                $sandbox,
                $args,
            );

            [$secondCode, $secondOutput] = $this->runDeptracGenerate(
                $sandbox,
                $args,
            );

            self::assertNotSame(
                0,
                $firstCode,
                'Unsafe SSoT dependency identifier must be rejected: '
                . $case,
            );

            self::assertStringContainsString(
                'CORETSIA_DEPTRAC_GENERATE_FAILED',
                $firstOutput,
            );

            self::assertSame(
                $firstCode,
                $secondCode,
                'Unsafe SSoT dependency rejection exit code must be deterministic: '
                . $case,
            );

            self::assertSame(
                $firstOutput,
                $secondOutput,
                'Unsafe SSoT dependency rejection output must be deterministic: '
                . $case,
            );

            self::assertNotSame(
                '',
                $firstOutput,
                'Unsafe SSoT dependency rejection must emit a sanitized diagnostic: '
                . $case,
            );

            self::assertStringNotContainsString(
                $unsafeDependency,
                $firstOutput,
            );

            self::assertStringNotContainsString(
                \str_replace('\\', '/', $sandbox),
                \str_replace('\\', '/', $firstOutput),
            );

            self::assertStringNotContainsString(
                'RuntimeException',
                $firstOutput,
            );

            self::assertStringNotContainsString(
                'Stack trace',
                $firstOutput,
            );
        }
    }

    private function assertYamlIsDeterministic(string $yaml): void
    {
        self::assertNotSame('', $yaml);
        self::assertFalse(str_contains($yaml, "\r"), 'Generated deptrac YAML must be LF-only.');
        self::assertSame("\n", substr($yaml, -1), 'Generated deptrac YAML must end with a final newline.');

        self::assertStringNotContainsString($this->repoRoot(), $yaml);
        self::assertStringNotContainsString(sys_get_temp_dir(), $yaml);
        self::assertStringNotContainsString('\\', str_replace('\\\\', '', $yaml));

        self::assertStringNotContainsString(
            '://',
            $yaml,
        );

        self::assertDoesNotMatchRegularExpression(
            '~/(?:home|users)/~i',
            $yaml,
        );

        self::assertDoesNotMatchRegularExpression(
            '~(?i)\b[A-Z]:[\\\\/]~',
            $yaml,
        );

        self::assertDoesNotMatchRegularExpression(
            '~\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+~',
            $yaml,
        );

        self::assertDoesNotMatchRegularExpression(
            '~//[^/]+/[^/]+~',
            $yaml,
        );

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
