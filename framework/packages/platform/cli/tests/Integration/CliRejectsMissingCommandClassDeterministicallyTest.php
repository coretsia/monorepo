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

final class CliRejectsMissingCommandClassDeterministicallyTest extends TestCase
{
    public function testMissingCommandClassIsDeterministicError(): void
    {
        $repoRoot = $this->repoRoot();

        self::assertFileExists($repoRoot . '/coretsia', 'Repo entrypoint MUST exist.');

        $skeletonRoot = $repoRoot . '/skeleton';
        $appCli = $skeletonRoot . '/config/cli.php';
        $appConfigDir = \dirname($appCli);

        $hadSkeletonDir = \is_dir($skeletonRoot);
        $hadDir = \is_dir($appConfigDir);
        $hadFile = \is_file($appCli);

        $original = $hadFile ? (string)\file_get_contents($appCli) : '';

        $override = $this->configCliPhp([
            'commands' => [
                'Vendor\\Package\\MissingCommand',
            ],
            'output' => [
                'format' => 'text',
                'redaction' => ['enabled' => true],
            ],
        ]);

        try {
            if (!\is_dir($appConfigDir)) {
                $ok = \mkdir($appConfigDir, 0777, true);
                self::assertTrue($ok, 'Failed to create skeleton config dir: ' . $appConfigDir);
            }

            $this->writeFile($appCli, $override);

            $result = $this->runProcess([
                \PHP_BINARY,
                '-d', 'opcache.enable_cli=0',
                '-d', 'opcache.enable=0',
                '-d', 'opcache.jit=0',
                $repoRoot . '/coretsia',
                'list',
            ], $repoRoot);

            self::assertSame(1, $result['exitCode'], 'CLI MUST exit 1 for missing command class.');
            self::assertSame('', $result['stdout'], 'This failure MUST NOT emit stdout.');

            $stderr = $this->normalizeNewlines($result['stderr']);
            $lines = \array_values(\array_filter(\explode("\n", $stderr), static fn(string $s): bool => $s !== ''));

            self::assertCount(2, $lines, 'CLI deterministic failure MUST emit exactly 2 stderr lines.');
            self::assertSame('CORETSIA_CLI_COMMAND_CLASS_MISSING', $lines[0]);
            self::assertSame('class-not-found', $lines[1]);

            self::assertStringNotContainsString('C:\\', $stderr);
            self::assertStringNotContainsString('\\\\', $stderr);
            self::assertStringNotContainsString('/home/', $stderr);
            self::assertStringNotContainsString('/Users/', $stderr);
        } finally {
            if ($hadFile) {
                $this->writeFile($appCli, $original);
            } else {
                if (\is_file($appCli)) {
                    @\unlink($appCli);
                }
                if (!$hadDir && \is_dir($appConfigDir)) {
                    // best-effort cleanup
                    @\rmdir($appConfigDir);
                }
                if (!$hadSkeletonDir && \is_dir($skeletonRoot)) {
                    // best-effort cleanup
                    @\rmdir($skeletonRoot);
                }
            }
        }
    }

    private function repoRoot(): string
    {
        $packageRoot = \dirname(__DIR__, 2);
        return \dirname($packageRoot, 4);
    }

    /**
     * @param array{commands?: list<string>, output?: array<string,mixed>} $subtree
     */
    private function configCliPhp(array $subtree): string
    {
        $export = \var_export($subtree, true);

        return <<<PHP
<?php

declare(strict_types=1);

return {$export};

PHP;
    }

    private function writeFile(string $file, string $content): void
    {
        $bytes = \file_put_contents($file, $content, \LOCK_EX);
        self::assertNotFalse($bytes, 'Failed to write file: ' . $file);
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
