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

        $runtimeRoot = $repoRoot . '/packages/platform/sample-runtime';
        $composer = \json_decode(
            $this->readBytes($runtimeRoot . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);
        self::assertSame('platform.sample-runtime', $composer['extra']['coretsia']['moduleId']);
        self::assertSame('config/sample_runtime.php', $composer['extra']['coretsia']['defaultsConfigPath']);
        self::assertFileExists($runtimeRoot . '/config/sample_runtime.php');
        self::assertFileDoesNotExist($runtimeRoot . '/config/sample-runtime.php');

        [$code, $output] = $this->runPackageComplianceGate($repoRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testRegisteredConfigRootRejectsSlugBasedDefaultsPath(): void
    {
        $repoRoot = $this->createPackageComplianceSandbox('package_good');
        $composerPath = $repoRoot . '/packages/platform/sample-runtime/composer.json';

        $composer = \json_decode(
            $this->readBytes($composerPath),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);

        $composer['extra']['coretsia']['defaultsConfigPath'] = 'config/sample-runtime.php';

        $this->writeBytesExact(
            $composerPath,
            \json_encode(
                $composer,
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
            ) . "\n",
        );

        [$code, $output] = $this->runPackageComplianceGate($repoRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_PACKAGE_COMPLIANCE_VIOLATION\n"
            . "packages/platform/sample-runtime/composer.json: invalid-runtime-metadata-defaultsConfigPath\n",
            $output,
        );
    }

    public function testRulesConfigRootMentionedOnlyInCommentIsRejected(): void
    {
        $repoRoot = $this->createPackageComplianceSandbox('package_good');
        $rulesPath = $repoRoot . '/packages/platform/sample-runtime/config/rules.php';
        $original = $this->readBytes($rulesPath);
        $malformed = \str_replace(
            "'configRoot' => 'sample_runtime',",
            "'configRoot' => 'unowned',\n    // 'configRoot' => 'sample_runtime',",
            $original,
        );

        self::assertNotSame($original, $malformed);
        $this->writeBytesExact($rulesPath, $malformed);

        [$code, $output] = $this->runPackageComplianceGate($repoRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_PACKAGE_COMPLIANCE_VIOLATION\n"
            . "packages/platform/sample-runtime/config/rules.php: config-rules-root-mismatch\n",
            $output,
        );
    }

    public function testRulesRejectComputedAndUnpackedConfigRootOverrides(): void
    {
        $replacements = [
            "'configRoot' => 'sample_runtime',\n    ...['configRoot' => 'unowned'],",
            "'configRoot' => 'sample_runtime',\n    'config' . 'Root' => 'unowned',",
            "'configRoot' => 'sample_runtime' . '_unowned',",
        ];

        foreach ($replacements as $replacement) {
            $repoRoot = $this->createPackageComplianceSandbox('package_good');
            $rulesPath = $repoRoot . '/packages/platform/sample-runtime/config/rules.php';
            $original = $this->readBytes($rulesPath);

            $malformed = \str_replace(
                "'configRoot' => 'sample_runtime',",
                $replacement,
                $original,
            );

            self::assertNotSame($original, $malformed);
            $this->writeBytesExact($rulesPath, $malformed);

            [$code, $output] = $this->runPackageComplianceGate($repoRoot);

            self::assertSame(1, $code);
            self::assertSame(
                "CORETSIA_PACKAGE_COMPLIANCE_VIOLATION\n"
                . "packages/platform/sample-runtime/config/rules.php: config-rules-root-mismatch\n",
                $output,
            );
        }
    }

    public function testRulesRejectConditionalReturnBeforeActualRuleset(): void
    {
        $repoRoot = $this->createPackageComplianceSandbox('package_good');
        $rulesPath = $repoRoot . '/packages/platform/sample-runtime/config/rules.php';
        $original = $this->readBytes($rulesPath);

        $malformed = \str_replace(
            "return [\n    'schemaVersion' => 1,\n    'configRoot' => 'sample_runtime',",
            "if (false) {\n"
            . "    return ['configRoot' => 'sample_runtime'];\n"
            . "}\n\n"
            . "return [\n"
            . "    'schemaVersion' => 1,\n"
            . "    'configRoot' => 'unowned',",
            $original,
        );

        self::assertNotSame($original, $malformed);
        $this->writeBytesExact($rulesPath, $malformed);

        [$code, $output] = $this->runPackageComplianceGate($repoRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_PACKAGE_COMPLIANCE_VIOLATION\n"
            . "packages/platform/sample-runtime/config/rules.php: config-rules-not-array\n",
            $output,
        );
    }

    public function testRulesRejectExecutableValueDespiteMatchingConfigRoot(): void
    {
        $repoRoot = $this->createPackageComplianceSandbox('package_good');
        $rulesPath = $repoRoot . '/packages/platform/sample-runtime/config/rules.php';
        $original = $this->readBytes($rulesPath);
        $malformed = \str_replace(
            "'keys' => [],",
            "'keys' => (static function (): array { return []; })(),",
            $original,
        );

        self::assertNotSame($original, $malformed);
        $this->writeBytesExact($rulesPath, $malformed);

        [$code, $output] = $this->runPackageComplianceGate($repoRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_PACKAGE_COMPLIANCE_VIOLATION\n"
            . "packages/platform/sample-runtime/config/rules.php: config-rules-non-declarative\n",
            $output,
        );
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
        $this->ensureDir($repoRoot . '/docs/ssot');

        $this->writeBytesExact(
            $repoRoot . '/docs/ssot/config-roots.md',
            "## Reserved Config Roots (MUST)\n\n"
            . "| root | owner package_id | defaults file | rules file | notes |\n"
            . "|---|---|---|---|---|\n"
            . "| `sample_runtime` | `platform/sample-runtime` | `packages/platform/sample-runtime/config/sample_runtime.php` | `packages/platform/sample-runtime/config/rules.php` | Fixture |\n",
        );

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
