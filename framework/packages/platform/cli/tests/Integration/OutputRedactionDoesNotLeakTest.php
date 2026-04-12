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

final class OutputRedactionDoesNotLeakTest extends TestCase
{
    public function testRedactionDoesNotLeakSecretsOrAbsolutePaths(): void
    {
        $repoRoot = $this->repoRoot();

        self::assertFileExists($repoRoot . '/coretsia', 'Repo entrypoint MUST exist.');

        $skeletonRoot = $repoRoot . '/skeleton';
        $appCli = $skeletonRoot . '/config/cli.php';
        $appConfigDir = \dirname($appCli);

        $packageRoot = \dirname(__DIR__, 2);

        $fixture = $packageRoot . '/tests/Fixtures/LeakCommand.php';
        self::assertFileExists($fixture, 'Fixture MUST exist.');

        $prependFile = $packageRoot . '/tests/Fixtures/LeakCommand.prepend.php';
        self::assertFileExists($prependFile, 'Prepend bootstrap MUST exist.');

        $hadSkeletonDir = \is_dir($skeletonRoot);
        $hadDir = \is_dir($appConfigDir);
        $hadFile = \is_file($appCli);

        $original = $hadFile ? (string)\file_get_contents($appCli) : '';

        $override = $this->configCliPhp([
            'commands' => [
                'Coretsia\\Platform\\Cli\\Tests\\Fixtures\\LeakCommand',
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

            $prepend = \realpath($prependFile);
            self::assertNotFalse($prepend);

            // ini value is safer with forward slashes on Windows.
            $prependIni = \str_replace('\\', '/', (string)$prepend);

            $result = $this->runProcess([
                \PHP_BINARY,
                '-d', 'opcache.enable_cli=0',
                '-d', 'opcache.enable=0',
                '-d', 'opcache.jit=0',
                '-d', 'auto_prepend_file=' . $prependIni,
                $repoRoot . '/coretsia',
                'leak',
            ], $repoRoot);

            self::assertSame(0, $result['exitCode'], 'Leak command MUST exit 0.');
            self::assertSame('', $result['stderr'], 'Leak command MUST NOT emit stderr.');

            $out = $this->normalizeNewlines($result['stdout']);

            // Must contain redaction placeholders.
            self::assertStringContainsString('<redacted>', $out);
            self::assertStringContainsString('<redacted-path>', $out);

            // Must NOT contain raw secrets.
            self::assertStringNotContainsString('superSecret', $out);
            self::assertStringNotContainsString('Bearer superSecret', $out);

            // Must NOT contain absolute path fragments.
            self::assertStringNotContainsString('C:\\OSPanel\\home\\coretsia-framework.local', $out);
            self::assertStringNotContainsString('/home/', $out);
            self::assertStringNotContainsString('/Users/', $out);

            // Text-mode patterns.
            self::assertStringContainsString("API_TOKEN=<redacted>\n", $out);
            self::assertStringContainsString("Authorization: <redacted>\n", $out);

            // JSON must redact by secret-like keys deterministically.
            self::assertStringContainsString('"apiToken":"<redacted>"', $out);
            self::assertStringContainsString('"password":"<redacted>"', $out);
            self::assertStringContainsString('"session":"<redacted>"', $out);

            // JSON must scrub absolute path string values.
            self::assertStringContainsString('"path":"<redacted-path>"', $out);
        } finally {
            if ($hadFile) {
                $this->writeFile($appCli, $original);
            } else {
                if (\is_file($appCli)) {
                    @\unlink($appCli);
                }
                if (!$hadDir && \is_dir($appConfigDir)) {
                    @\rmdir($appConfigDir);
                }
                if (!$hadSkeletonDir && \is_dir($skeletonRoot)) {
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
