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

final class NoRuntimeToolingArtifactsGateTest extends ToolContractTestCase
{
    public function testRuntimeSourceImportingToolsSpikesFails(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/ImportsToolsSpikes.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "use Coretsia\\Tools\\Spikes\\SomeSpike;\n\n"
            . "final class ImportsToolsSpikes\n"
            . "{\n"
            . "}\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION', $output);
        self::assertStringContainsString('runtime-imports-tools-spikes', $output);
        self::assertStringContainsString('framework/packages/core/foundation/src/Fixture/ImportsToolsSpikes.php', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testRuntimeSourceImportingDevtoolsFails(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'platform/cli/src/Fixture/ImportsDevtools.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Platform\\Cli\\Fixture;\n\n"
            . "use Coretsia\\Devtools\\CliSpikes\\SomeTool;\n\n"
            . "final class ImportsDevtools\n"
            . "{\n"
            . "}\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION', $output);
        self::assertStringContainsString('runtime-imports-devtools', $output);
        self::assertStringContainsString('framework/packages/platform/cli/src/Fixture/ImportsDevtools.php', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testRuntimeSourceReferencingDevtoolsPackageFails(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/ReferencesDevtoolsPackage.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "final class ReferencesDevtoolsPackage\n"
            . "{\n"
            . "    private const string PACKAGE = 'coretsia/devtools-internal-toolkit';\n"
            . "}\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION', $output);
        self::assertStringContainsString('runtime-references-devtools-package', $output);
        self::assertStringContainsString('framework/packages/core/foundation/src/Fixture/ReferencesDevtoolsPackage.php', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testRuntimeConfigReferencingFrameworkToolsFails(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/config/foundation.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'path' => 'framework/tools/gates/package_compliance_gate.php',\n"
            . "];\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION', $output);
        self::assertStringContainsString('runtime-reads-framework-tools', $output);
        self::assertStringContainsString('framework/packages/core/foundation/config/foundation.php', $output);
        self::assertStringNotContainsString('framework/tools/gates/package_compliance_gate.php', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testRuntimeSourceRequiringToolsBuildFails(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/RequiresToolsBuild.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "require __DIR__ . '/../../../../tools/build/deptrac_generate.php';\n"
            . "final class RequiresToolsBuild\n"
            . "{\n"
            . "}\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION', $output);
        self::assertStringContainsString('runtime-reads-framework-tools', $output);
        self::assertStringContainsString('framework/packages/core/foundation/src/Fixture/RequiresToolsBuild.php', $output);
        self::assertStringNotContainsString('tools/build/deptrac_generate.php', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testRuntimeSourceShellingOutToToolsGatesFails(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/ExecutesToolsGate.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "final class ExecutesToolsGate\n"
            . "{\n"
            . "    public function run(): void\n"
            . "    {\n"
            . "        shell_exec('php tools/gates/no_skeleton_http_default_gate.php');\n"
            . "    }\n"
            . "}\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION', $output);
        self::assertStringContainsString('runtime-executes-tooling-path', $output);
        self::assertStringContainsString('framework/packages/core/foundation/src/Fixture/ExecutesToolsGate.php', $output);
        self::assertStringNotContainsString('tools/gates/no_skeleton_http_default_gate.php', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testRuntimeSourceReadingArchitectureArtifactFails(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/ReadsArchitectureArtifact.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "final class ReadsArchitectureArtifact\n"
            . "{\n"
            . "    public function read(): string\n"
            . "    {\n"
            . "        return (string) file_get_contents('framework/var/arch/deptrac_graph.svg');\n"
            . "    }\n"
            . "}\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION', $output);
        self::assertStringContainsString('runtime-reads-architecture-artifact', $output);
        self::assertStringContainsString('framework/packages/core/foundation/src/Fixture/ReadsArchitectureArtifact.php', $output);
        self::assertStringNotContainsString('framework/var/arch/deptrac_graph.svg', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testDocsTestsFixturesAndDevtoolsMentionsAreIgnored(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/CleanRuntimeSource.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "final class CleanRuntimeSource\n"
            . "{\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $sandbox . '/docs/example.php',
            "<?php\n\n"
            . "use Coretsia\\Tools\\Spikes\\IgnoredDocMention;\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/tests/Fixture/IgnoredTestMention.php',
            "<?php\n\n"
            . "use Coretsia\\Devtools\\IgnoredTestMention;\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/fixtures/IgnoredFixtureMention.php',
            "<?php\n\n"
            . "use Coretsia\\Tools\\Spikes\\IgnoredFixtureMention;\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/packages/devtools/cli-spikes/src/IgnoredDevtoolsPackageMention.php',
            "<?php\n\n"
            . "use Coretsia\\Tools\\Spikes\\IgnoredDevtoolsPackageMention;\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertSame(0, $code, $output);
        self::assertSame('', trim($output));
    }

    public function testGateIsDeterministicNoopWhenNoRuntimePackageScanRootsExist(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox(includeRuntimePackageRoot: false);

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertSame(0, $code, $output);
        self::assertSame('', trim($output));
    }

    public function testDiagnosticsAreSortedAndRepoRelative(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/ZViolation.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "final class ZViolation\n"
            . "{\n"
            . "    private const string PATH = 'framework/tools/gates/no_skeleton_http_default_gate.php';\n"
            . "}\n",
        );

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/AViolation.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "use Coretsia\\Devtools\\SomeTool;\n\n"
            . "final class AViolation\n"
            . "{\n"
            . "}\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);

        $aPos = strpos($output, 'framework/packages/core/foundation/src/Fixture/AViolation.php');
        $zPos = strpos($output, 'framework/packages/core/foundation/src/Fixture/ZViolation.php');

        self::assertIsInt($aPos, $output);
        self::assertIsInt($zPos, $output);
        self::assertLessThan($zPos, $aPos, $output);

        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testDiagnosticsDoNotContainSourceSnippetsAbsolutePathsEnvValuesOrSecrets(): void
    {
        $sandbox = $this->createNoRuntimeToolingArtifactsGateSandbox();

        $this->writeRuntimePhp(
            $sandbox,
            'core/foundation/src/Fixture/LeakyViolation.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . "final class LeakyViolation\n"
            . "{\n"
            . "    private const string SECRET = 'super_secret_runtime_tooling_token';\n"
            . "    private const string ENV_VALUE = 'CORETSIA_SECRET_ENV_VALUE';\n"
            . "    private const string PATH = 'framework/tools/gates/package_compliance_gate.php';\n"
            . "}\n",
        );

        [$code, $output] = $this->runNoRuntimeToolingArtifactsGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_RUNTIME_TOOLING_ARTIFACTS_VIOLATION', $output);
        self::assertStringContainsString('runtime-reads-framework-tools', $output);
        self::assertStringNotContainsString('super_secret_runtime_tooling_token', $output);
        self::assertStringNotContainsString('CORETSIA_SECRET_ENV_VALUE', $output);
        self::assertStringNotContainsString('framework/tools/gates/package_compliance_gate.php', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    private function createNoRuntimeToolingArtifactsGateSandbox(bool $includeRuntimePackageRoot = true): string
    {
        $sandbox = $this->tempDir('coretsia-no-runtime-tooling-artifacts-gate');

        $this->ensureDir($sandbox . '/framework/tools/gates');
        $this->ensureDir($sandbox . '/framework/tools/spikes');
        $this->ensureDir($sandbox . '/framework/vendor');

        $this->writeBytesExact(
            $sandbox . '/framework/vendor/autoload.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "return true;\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/tools/gates/no_runtime_tooling_artifacts_gate.php',
            $this->readBytes($this->frameworkRoot() . '/tools/gates/no_runtime_tooling_artifacts_gate.php'),
        );

        $this->copyDir(
            $this->frameworkRoot() . '/tools/spikes/_support',
            $sandbox . '/framework/tools/spikes/_support',
        );

        if ($includeRuntimePackageRoot) {
            $this->ensureDir($sandbox . '/framework/packages/core/foundation/src');
            $this->ensureDir($sandbox . '/framework/packages/core/foundation/config');
        }

        return $sandbox;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runNoRuntimeToolingArtifactsGate(string $sandbox): array
    {
        return $this->runPhp(
            $sandbox . '/framework/tools/gates/no_runtime_tooling_artifacts_gate.php',
            [],
            $sandbox,
        );
    }

    private function writeRuntimePhp(string $sandbox, string $runtimeRelativePath, string $contents): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/' . ltrim($runtimeRelativePath, '/'),
            $contents,
        );
    }

    private function assertSafeGateOutput(string $sandbox, string $output): void
    {
        $normalizedSandbox = rtrim(str_replace('\\', '/', $sandbox), '/');

        self::assertStringNotContainsString($normalizedSandbox, str_replace('\\', '/', $output));
        self::assertStringNotContainsString('\\', $output);
        self::assertStringNotContainsString('Coretsia\\Tools\\Spikes', $output);
        self::assertStringNotContainsString('Coretsia\\Devtools', $output);
        self::assertStringNotContainsString('return [', $output);
        self::assertStringNotContainsString('shell_exec', $output);
        self::assertStringNotContainsString('file_get_contents', $output);
    }
}
