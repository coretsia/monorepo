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

final class SecretLeakageGateTest extends TestCase
{
    public function testCleanFixturePassesWithoutOutput(): void
    {
        $result = $this->runGateWithFixture('gitleaks_clean.json', 0);

        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testFindingFixtureFailsWithSanitizedDiagnostics(): void
    {
        $result = $this->runGateWithFixture('gitleaks_with_findings.json', 1);

        self::assertSame(1, $result['exit']);

        $output = $this->normalizeOutput($result['stderr'] . $result['stdout']);

        self::assertSame(
            [
                'CORETSIA_SECRET_LEAK_DETECTED',
                'config/app.php:12: generic-api-key',
                'docs/example.txt:3: private-key',
            ],
            $output,
        );

        self::assertStringNotContainsString('REDACTED', \implode("\n", $output));
        self::assertStringNotContainsString('Secret', \implode("\n", $output));
        self::assertStringNotContainsString('Match', \implode("\n", $output));
    }

    public function testInvalidJsonFixtureFailsAsScanFailed(): void
    {
        $result = $this->runGateWithFixture('gitleaks_scan_failed.json', 1);

        self::assertSame(1, $result['exit']);

        $output = $this->normalizeOutput($result['stderr'] . $result['stdout']);

        self::assertSame(
            ['CORETSIA_SECRET_GATE_SCAN_FAILED'],
            $output,
        );
    }

    /**
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runGateWithFixture(string $fixtureName, int $gitleaksExitCode): array
    {
        $frameworkRoot = $this->frameworkRoot();
        $fixturePath = $frameworkRoot . '/tools/tests/Fixtures/Gitleaks/' . $fixtureName;

        self::assertFileExists($fixturePath);

        $scanRoot = $this->makeFixtureScanRoot();

        try {
            $gate = $frameworkRoot . '/tools/gates/secret_leakage_gate.php';

            self::assertFileExists($gate);

            return $this->runPhp(
                [
                    $gate,
                    '--path=' . $scanRoot,
                    '--config=' . $scanRoot . '/.gitleaks.toml',
                    '--gitleaks-json=' . $fixturePath,
                    '--gitleaks-exit-code=' . (string)$gitleaksExitCode,
                ],
                $frameworkRoot,
            );
        } finally {
            $this->removeTree($scanRoot);
        }
    }

    private function makeFixtureScanRoot(): string
    {
        $base = \rtrim(\str_replace('\\', '/', \sys_get_temp_dir()), '/');
        $root = $base . '/coretsia-secret-leakage-gate-' . \bin2hex(\random_bytes(8));

        self::assertTrue(\mkdir($root, 0777, true));

        $config = <<<'TOML'
title = "Coretsia Secret Leakage Gate Test Fixture"

[extend]
useDefault = true
TOML;

        self::assertNotFalse(\file_put_contents($root . '/.gitleaks.toml', $config . "\n"));

        return $root;
    }

    /**
     * @param list<string> $args
     * @return array{exit:int,stdout:string,stderr:string}
     */
    private function runPhp(array $args, string $cwd): array
    {
        $cmd = \array_merge([\PHP_BINARY], $args);

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
            $cwd,
            null,
            \PHP_OS_FAMILY === 'Windows' ? ['bypass_shell' => true] : [],
        );

        self::assertIsResource($process);

        \fclose($pipes[0]);

        $stdout = (string)\stream_get_contents($pipes[1]);
        $stderr = (string)\stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exit = \proc_close($process);

        return [
            'exit' => $exit >= 0 ? $exit : 1,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeOutput(string $output): array
    {
        $output = \trim(\str_replace(["\r\n", "\r"], "\n", $output));

        if ($output === '') {
            return [];
        }

        return \explode("\n", $output);
    }

    private function frameworkRoot(): string
    {
        $root = \realpath(__DIR__ . '/../../..');

        self::assertIsString($root);

        return \rtrim(\str_replace('\\', '/', $root), '/');
    }

    private function removeTree(string $path): void
    {
        $path = \rtrim(\str_replace('\\', '/', $path), '/');

        if ($path === '' || !\is_dir($path)) {
            return;
        }

        $items = \scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;

            if (\is_dir($child) && !\is_link($child)) {
                $this->removeTree($child);
                continue;
            }

            @\unlink($child);
        }

        @\rmdir($path);
    }
}
