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

use PHPUnit\Framework\TestCase;

final class LicenseHeaderGateTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $base = $this->frameworkRoot() . '/var/license-header-gate-tests';
        $this->fixtureRoot = $base . '/case-' . \bin2hex(\random_bytes(8));

        self::assertTrue(\mkdir($this->fixtureRoot, 0777, true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->fixtureRoot);

        parent::tearDown();
    }

    public function testCanonicalCBlockHeaderWithDifferentOwnerPasses(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/Example.php',
            "<?php\n\ndeclare(strict_types=1);\n\n"
            . $this->cBlockHeader(
                authors: 'Example Runtime Team and contributors',
                copyright: '2026 Example Runtime Team',
            )
            . "\nfinal class Example {}\n",
        );

        $this->assertGatePasses($this->fixtureRoot);
    }

    public function testCanonicalHtmlHeaderPasses(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/asset.svg',
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . $this->htmlHeader()
            . "\n<svg xmlns=\"http://www.w3.org/2000/svg\"></svg>\n",
        );

        $this->assertGatePasses($this->fixtureRoot);
    }

    public function testCanonicalHtmlAndHtmHeadersPass(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/report.html',
            $this->htmlHeader() . "\n<!doctype html>\n<html lang=\"en\"></html>\n",
        );
        $this->writeFile(
            $this->fixtureRoot . '/legacy.htm',
            $this->htmlHeader() . "\n<!doctype html>\n<html lang=\"en\"></html>\n",
        );

        $this->assertGatePasses($this->fixtureRoot);
    }

    public function testCanonicalDotHeaderPasses(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/graph.dot',
            $this->slashLineHeader() . "\ndigraph \"coretsia\" {\n}\n",
        );

        $this->assertGatePasses($this->fixtureRoot);
    }

    public function testCanonicalHashHeaderPasses(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/phpstan.neon',
            $this->hashHeader() . "\nparameters:\n    level: max\n",
        );

        $this->assertGatePasses($this->fixtureRoot);
    }

    public function testCanonicalRepositoryMetadataHashHeadersPass(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/.editorconfig',
            $this->hashHeader() . "\nroot = true\n",
        );
        $this->writeFile(
            $this->fixtureRoot . '/.gitattributes',
            $this->hashHeader() . "\n* text=auto\n",
        );
        $this->writeFile(
            $this->fixtureRoot . '/.gitignore',
            $this->hashHeader() . "\n/vendor/\n",
        );
        $this->writeFile(
            $this->fixtureRoot . '/.gitleaks.toml',
            $this->hashHeader() . "\ntitle = \"fixture\"\n",
        );

        $this->assertGatePasses($this->fixtureRoot);
    }

    public function testMissingHeaderFailsDeterministically(): void
    {
        $path = $this->fixtureRoot . '/Missing.php';
        $this->writeFile($path, "<?php\n\ndeclare(strict_types=1);\n");

        $this->assertGateViolation([
            $this->diagnostic($path, 'license-header-missing'),
        ]);
    }

    public function testMalformedCanonicalHeaderFailsDeterministically(): void
    {
        $path = $this->fixtureRoot . '/Malformed.php';
        $header = \str_replace(
            'Project: Coretsia Framework (Monorepo)',
            'Project: Coretsia Framework',
            $this->cBlockHeader(),
        );

        $this->writeFile(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\n" . $header . "\n",
        );

        $this->assertGateViolation([
            $this->diagnostic($path, 'license-header-invalid'),
        ]);
    }

    public function testWrongSpdxLicenseIsInvalidRatherThanMissing(): void
    {
        $path = $this->fixtureRoot . '/WrongLicense.php';
        $this->writeFile(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\n"
            . $this->cBlockHeader(license: 'MIT')
            . "\n",
        );

        $this->assertGateViolation([
            $this->diagnostic($path, 'license-header-invalid'),
        ]);
    }

    public function testCopyrightMismatchFailsDeterministically(): void
    {
        $path = $this->fixtureRoot . '/Mismatch.php';
        $this->writeFile(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\n"
            . $this->cBlockHeader(
                copyright: '2026 Example Runtime Team',
                spdxCopyright: '2026 Different Owner',
            )
            . "\n",
        );

        $this->assertGateViolation([
            $this->diagnostic($path, 'license-header-copyright-mismatch'),
        ]);
    }

    public function testNestedBuildDirectoryIsScanned(): void
    {
        $path = $this->fixtureRoot . '/build/MissingHeader.php';
        $this->writeFile($path, "<?php\n\ndeclare(strict_types=1);\n");

        $this->assertGateViolation([
            $this->diagnostic($path, 'license-header-missing'),
        ]);
    }

    public function testUnsupportedJsonIsIgnored(): void
    {
        $this->writeFile(
            $this->fixtureRoot . '/config.json',
            "{\n    \"enabled\": true\n}\n",
        );

        $this->assertGatePasses($this->fixtureRoot);
    }

    public function testLicenseAndNoticeAreExempt(): void
    {
        $this->writeFile($this->fixtureRoot . '/LICENSE', "fixture license\n");
        $this->writeFile($this->fixtureRoot . '/NOTICE', "fixture notice\n");

        $this->assertGatePasses($this->fixtureRoot);
    }

    public function testPathOutsideRepositoryFailsWithGateFailedCode(): void
    {
        $externalRoot = \rtrim(\str_replace('\\', '/', \sys_get_temp_dir()), '/')
            . '/coretsia-license-header-outside-'
            . \bin2hex(\random_bytes(8));

        self::assertTrue(\mkdir($externalRoot, 0777, true));

        try {
            $result = $this->runGate($externalRoot);

            self::assertSame(1, $result['exit']);
            self::assertSame('', $result['stdout']);
            self::assertSame(
                ['CORETSIA_LICENSE_HEADER_GATE_FAILED'],
                $this->stderrLines($result['stderr']),
            );
            $this->assertOutputIsRedacted($result['stderr'], $externalRoot);
        } finally {
            $this->removeTree($externalRoot);
        }
    }

    public function testNonAsciiDiagnosticPathIsPercentEncoded(): void
    {
        $path = $this->fixtureRoot . '/alpha—beta.php';
        $this->writeFile($path, "<?php\n");

        $expectedPath = \str_replace(
            '—',
            '%E2%80%94',
            $this->repoRelativePath($path),
        );

        $this->assertGateViolation([
            $expectedPath . ':license-header-missing',
        ]);
    }

    public function testDiagnosticsAreSortedDeterministically(): void
    {
        $zPath = $this->fixtureRoot . '/z.php';
        $aPath = $this->fixtureRoot . '/a.php';
        $mPath = $this->fixtureRoot . '/nested/m.php';

        $this->writeFile($zPath, "<?php\n");
        $this->writeFile($aPath, "<?php\n");
        $this->writeFile($mPath, "<?php\n");

        $expected = [
            $this->diagnostic($zPath, 'license-header-missing'),
            $this->diagnostic($aPath, 'license-header-missing'),
            $this->diagnostic($mPath, 'license-header-missing'),
        ];
        \sort($expected, \SORT_STRING);

        $this->assertGateViolation($expected);
    }

    /**
     * @param list<string> $expectedDiagnostics
     */
    private function assertGateViolation(array $expectedDiagnostics): void
    {
        $result = $this->runGate($this->fixtureRoot);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            \array_merge(
                ['CORETSIA_LICENSE_HEADER_VIOLATION'],
                $expectedDiagnostics,
            ),
            $this->stderrLines($result['stderr']),
        );
        $this->assertOutputIsRedacted($result['stderr'], $this->frameworkRoot());
    }

    private function assertGatePasses(string $scanRoot): void
    {
        $result = $this->runGate($scanRoot);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    /**
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runGate(string $scanRoot): array
    {
        $frameworkRoot = $this->frameworkRoot();
        $gate = $frameworkRoot . '/tools/gates/license_header_gate.php';

        self::assertFileExists($gate);

        $process = \proc_open(
            [\PHP_BINARY, $gate, '--path=' . $scanRoot],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $frameworkRoot,
        );

        self::assertIsResource($process);

        \fclose($pipes[0]);

        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exit = \proc_close($process);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return [
            'exit' => $exit,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * @return list<string>
     */
    private function stderrLines(string $stderr): array
    {
        $stderr = \trim(\str_replace(["\r\n", "\r"], "\n", $stderr));

        return $stderr === '' ? [] : \explode("\n", $stderr);
    }

    private function diagnostic(string $absolutePath, string $reason): string
    {
        return $this->repoRelativePath($absolutePath) . ':' . $reason;
    }

    private function repoRelativePath(string $absolutePath): string
    {
        $repoRoot = $this->repoRoot();
        $absolutePath = \str_replace('\\', '/', $absolutePath);

        self::assertStringStartsWith($repoRoot . '/', $absolutePath);

        return \substr($absolutePath, \strlen($repoRoot) + 1);
    }

    private function repoRoot(): string
    {
        return \rtrim(\str_replace('\\', '/', \dirname($this->frameworkRoot())), '/');
    }

    private function frameworkRoot(): string
    {
        return \rtrim(\str_replace('\\', '/', \dirname(__DIR__, 3)), '/');
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = \dirname($path);
        if (!\is_dir($directory)) {
            self::assertTrue(\mkdir($directory, 0777, true));
        }

        self::assertNotFalse(\file_put_contents($path, $contents));
    }

    private function removeTree(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $path,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                continue;
            }

            $entryPath = $entry->getPathname();
            if ($entry->isDir() && !$entry->isLink()) {
                @\rmdir($entryPath);
                continue;
            }

            @\unlink($entryPath);
        }

        @\rmdir($path);
    }

    private function assertOutputIsRedacted(string $output, string $sensitivePath): void
    {
        $normalized = \str_replace('\\', '/', $output);
        $sensitivePath = \str_replace('\\', '/', $sensitivePath);

        self::assertStringNotContainsString($sensitivePath, $normalized);
        self::assertStringNotContainsString('RuntimeException', $normalized);
        self::assertStringNotContainsString('Stack trace', $normalized);
    }

    private function cBlockHeader(
        string $authors = 'Vladyslav Mudrichenko and contributors',
        string $copyright = '2026 Vladyslav Mudrichenko',
        ?string $spdxCopyright = null,
        string $license = 'Apache-2.0',
    ): string {
        $spdxCopyright ??= $copyright;

        return \sprintf(
            "/*\n"
            . " * Coretsia Framework (Monorepo)\n"
            . " *\n"
            . " * Project: Coretsia Framework (Monorepo)\n"
            . " * Authors: %s\n"
            . " * Copyright (c) %s\n"
            . " *\n"
            . " * SPDX-FileCopyrightText: %s\n"
            . " * SPDX-License-Identifier: %s\n"
            . " *\n"
            . " * For contributors list, see git history.\n"
            . " * See LICENSE and NOTICE in the project root for full license information.\n"
            . " */",
            $authors,
            $copyright,
            $spdxCopyright,
            $license,
        );
    }

    private function htmlHeader(): string
    {
        return <<<'HTML'
<!--
  Coretsia Framework (Monorepo)

  Project: Coretsia Framework (Monorepo)
  Authors: Vladyslav Mudrichenko and contributors
  Copyright (c) 2026 Vladyslav Mudrichenko

  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
  SPDX-License-Identifier: Apache-2.0

  For contributors list, see git history.
  See LICENSE and NOTICE in the project root for full license information.
-->
HTML;
    }

    private function hashHeader(): string
    {
        return <<<'HASH'
# Coretsia Framework (Monorepo)
#
# Project: Coretsia Framework (Monorepo)
# Authors: Vladyslav Mudrichenko and contributors
# Copyright (c) 2026 Vladyslav Mudrichenko
#
# SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
# SPDX-License-Identifier: Apache-2.0
#
# For contributors list, see git history.
# See LICENSE and NOTICE in the project root for full license information.
HASH;
    }

    private function slashLineHeader(): string
    {
        return <<<'SLASH'
// Coretsia Framework (Monorepo)
//
// Project: Coretsia Framework (Monorepo)
// Authors: Vladyslav Mudrichenko and contributors
// Copyright (c) 2026 Vladyslav Mudrichenko
//
// SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
// SPDX-License-Identifier: Apache-2.0
//
// For contributors list, see git history.
// See LICENSE and NOTICE in the project root for full license information.
SLASH;
    }
}
