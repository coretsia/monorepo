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

namespace Coretsia\Platform\Cli\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class CliBootHelpWorksWithEmptyCommandsTest extends TestCase
{
    public function testBootHelpWorksWithEmptyCommands(): void
    {
        $repoRoot = $this->repoRoot();

        self::assertFileExists($repoRoot . '/coretsia', 'Repo entrypoint MUST exist.');

        $result = $this->runProcess([
            \PHP_BINARY,
            '-d', 'opcache.enable_cli=0',
            '-d', 'opcache.enable=0',
            '-d', 'opcache.jit=0',
            $repoRoot . '/coretsia',
        ], $repoRoot);

        self::assertSame(0, $result['exitCode'], 'CLI MUST exit 0 for help.');
        self::assertSame('', $result['stderr'], 'Help path MUST NOT emit to stderr.');

        $out = $this->normalizeNewlines($result['stdout']);

        self::assertNotSame('', $out, 'Help output MUST NOT be empty.');
        self::assertStringContainsString("Usage:\n", $out);
        self::assertStringContainsString("php coretsia <command> [args...]\n", $out);

        // Built-ins must be listed.
        self::assertStringContainsString("Commands:\n", $out);
        self::assertStringContainsString("  help\n", $out);
        self::assertStringContainsString("  list\n", $out);
    }

    private function repoRoot(): string
    {
        $packageRoot = \dirname(__DIR__, 2);
        return \dirname($packageRoot, 4);
    }

    private function normalizeNewlines(string $s): string
    {
        return \str_replace("\r\n", "\n", $s);
    }

    /**
     * @param list<string> $cmd
     * @return array{exitCode:int, stdout:string, stderr:string}
     */
    private function runProcess(array $cmd, ?string $cwd = null): array
    {
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = \proc_open($cmd, $spec, $pipes, $cwd, null, ['bypass_shell' => true]);

        self::assertIsResource($proc, 'proc_open() failed.');

        \fclose($pipes[0]);

        $stdout = (string)\stream_get_contents($pipes[1]);
        $stderr = (string)\stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exitCode = (int)\proc_close($proc);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
