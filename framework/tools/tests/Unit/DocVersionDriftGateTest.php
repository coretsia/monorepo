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

namespace Coretsia\Tools\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DocVersionDriftGateTest extends TestCase
{
    public function testPassFixtureExitsZeroAndEmitsNoOutput(): void
    {
        $result = $this->runGate('Pass');

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testSsotIndexDocumentMismatchEmitsDocVersionDrift(): void
    {
        $result = $this->runGate('SsotVersionDrift');

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            "CORETSIA_DOC_VERSION_DRIFT\n"
            . "docs/ssot/example.md: ssotVersion-drift index-version-2 file-version-1\n",
            $result['stderr'],
        );
    }

    public function testAdrIndexDocumentMismatchEmitsDocVersionDrift(): void
    {
        $result = $this->runGate('AdrVersionDrift');

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            "CORETSIA_DOC_VERSION_DRIFT\n"
            . "docs/adr/ADR-0001-example.md: adrVersion-drift index-version-2 file-version-1\n",
            $result['stderr'],
        );
    }

    public function testMissingFencedYamlMetadataBlockEmitsDeterministicDiagnostic(): void
    {
        $result = $this->runGate('MissingMetadataBlock');

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            "CORETSIA_DOC_VERSION_DRIFT\n"
            . "docs/ssot/example.md: metadata-block-missing\n",
            $result['stderr'],
        );
    }

    public function testMissingLinkedDocumentEmitsDeterministicDiagnostic(): void
    {
        $result = $this->runGate('MissingDocument');

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            "CORETSIA_DOC_VERSION_DRIFT\n"
            . "docs/ssot/missing.md: document-missing\n",
            $result['stderr'],
        );
    }

    public function testInvalidIndexEntryEmitsDeterministicLineDiagnostic(): void
    {
        $result = $this->runGate('InvalidIndexEntry');

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            "CORETSIA_DOC_VERSION_DRIFT\n"
            . "docs/ssot/INDEX.md: index-entry-format-invalid:line-21\n",
            $result['stderr'],
        );
    }

    public function testDiagnosticsAreSortedByByteOrder(): void
    {
        $result = $this->runGate('SortedDiagnostics');

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame(
            "CORETSIA_DOC_VERSION_DRIFT\n"
            . "docs/adr/ADR-0001-a.md: adrVersion-drift index-version-2 file-version-1\n"
            . "docs/ssot/a.md: ssotVersion-drift index-version-2 file-version-1\n"
            . "docs/ssot/b.md: ssotVersion-drift index-version-3 file-version-1\n",
            $result['stderr'],
        );
    }

    public function testDiagnosticsContainRepoRelativePathsOnly(): void
    {
        $result = $this->runGate('MissingDocument');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('docs/ssot/missing.md: document-missing', $result['stderr']);
        self::assertStringNotContainsString($this->repoRoot(), $result['stderr']);
        self::assertStringNotContainsString('\\', $result['stderr']);
        self::assertStringNotContainsString('://', $result['stderr']);
        self::assertStringNotContainsString('unsafe-output', $result['stderr']);
    }

    public function testInvalidPathEmitsGateFailed(): void
    {
        $result = $this->runGatePath($this->fixtureRoot('DoesNotExist'));

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame("CORETSIA_DOC_VERSION_GATE_FAILED\n", $result['stderr']);
    }

    /**
     * @return array{exit:int, stdout:string, stderr:string}
     */
    private function runGate(string $fixture): array
    {
        return $this->runGatePath($this->fixtureRoot($fixture));
    }

    /**
     * @return array{exit:int, stdout:string, stderr:string}
     */
    private function runGatePath(string $path): array
    {
        $gate = $this->repoRoot() . '/framework/tools/gates/doc_version_drift_gate.php';

        self::assertFileExists($gate);

        $cmd = [
            PHP_BINARY,
            $gate,
            '--path=' . $path,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];

        $process = \proc_open(
            $cmd,
            $descriptors,
            $pipes,
            $this->repoRoot(),
            null,
            $this->procOptions($cmd),
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
            'stdout' => \str_replace(["\r\n", "\r"], "\n", $stdout),
            'stderr' => \str_replace(["\r\n", "\r"], "\n", $stderr),
        ];
    }

    /**
     * @param list<string> $cmd
     * @return array<string, mixed>
     */
    private function procOptions(array $cmd): array
    {
        if (\DIRECTORY_SEPARATOR !== '\\') {
            return [
                'bypass_shell' => true,
            ];
        }

        /*
         * Windows proc_open() cannot always use array argv consistently across
         * environments. Let PHP handle command-line quoting through the shell.
         */
        return [];
    }

    private function fixtureRoot(string $fixture): string
    {
        return $this->repoRoot()
            . '/framework/tools/tests/Fixtures/DocVersion/'
            . $fixture;
    }

    private function repoRoot(): string
    {
        $root = \realpath(__DIR__ . '/../../../..');

        self::assertIsString($root);

        return \rtrim(\str_replace('\\', '/', $root), '/');
    }
}
