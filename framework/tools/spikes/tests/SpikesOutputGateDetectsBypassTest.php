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

namespace Coretsia\Tools\Spikes\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpikesOutputGateDetectsBypassTest extends TestCase
{
    public function test_default_mode_passes_on_real_repo_scan_root_framework_tools(): void
    {
        $gate = self::gateScriptPath();

        // Cement: CWD-independent (run from outside repo).
        $cwd = sys_get_temp_dir();

        $r = self::runPhpScript($gate, [], $cwd);

        self::assertSame(0, $r['exitCode'], self::debugProcessResult($r));

        // Pass => print nothing.
        self::assertSame('', trim($r['stdout']), 'stdout must be empty on pass');
        self::assertSame('', trim($r['stderr']), 'stderr must be empty on pass');
    }

    public function test_path_override_mode_scans_synthetic_tree_and_is_cwd_independent(): void
    {
        $gate = self::gateScriptPath();

        $tmpRoot = self::createTempScanRoot();
        try {
            // Synthetic scan-only tree (bootstrap/ConsoleOutput MUST be loaded from real runtime tools root).
            self::writePhp($tmpRoot . '/spikes/fingerprint/Ok.php', <<<'PHP'
                <?php

                declare(strict_types=1);

                use Coretsia\Tools\Spikes\_support\ConsoleOutput;

                // This is scan-only; the gate must NOT treat ConsoleOutput usage as a bypass.
                ConsoleOutput::line('ok');
                PHP
            );

            // Cement the NOTE: If gate incorrectly loads bootstrap from scan-root, this would terminate.
            self::writePhp($tmpRoot . '/spikes/_support/bootstrap.php', <<<'PHP'
                <?php
                exit(123);
                PHP
            );

            $r = self::runPhpScript(
                $gate,
                ['--path=' . $tmpRoot],
                // Cement: run from synthetic root to prove runtime-root-based bootstrap, not scan-root-based.
                $tmpRoot,
            );

            self::assertSame(0, $r['exitCode'], self::debugProcessResult($r));
            self::assertSame('', trim($r['stdout']), 'stdout must be empty on pass');
            self::assertSame('', trim($r['stderr']), 'stderr must be empty on pass');
        } finally {
            self::removeDirRecursive($tmpRoot);
        }
    }

    public function test_path_override_mode_applies_same_excludes_as_default_mode(): void
    {
        $gate = self::gateScriptPath();

        $tmpRoot = self::createTempScanRoot();
        try {
            // Cement: spikes/tests/** is excluded; bypasses here MUST NOT fail the gate.
            self::writePhp($tmpRoot . '/spikes/tests/bad.php', <<<'PHP'
                <?php

                declare(strict_types=1);

                echo "x";
                PHP
            );

            // Cement: spikes/fixtures/** is excluded.
            self::writePhp($tmpRoot . '/spikes/fixtures/bad.php', <<<'PHP'
                <?php

                declare(strict_types=1);

                echo "x";
                PHP
            );

            // Cement: gates/**/tests/** is excluded.
            self::writePhp($tmpRoot . '/gates/tests/bad.php', <<<'PHP'
                <?php

                declare(strict_types=1);

                echo "x";
                PHP
            );

            // Cement: gates/**/fixtures/** is excluded.
            self::writePhp($tmpRoot . '/gates/fixtures/bad.php', <<<'PHP'
                <?php

                declare(strict_types=1);

                echo "x";
                PHP
            );

            $r = self::runPhpScript($gate, ['--path=' . $tmpRoot], $tmpRoot);

            self::assertSame(0, $r['exitCode'], self::debugProcessResult($r));
            self::assertSame('', trim($r['stdout']), 'stdout must be empty on pass');
            self::assertSame('', trim($r['stderr']), 'stderr must be empty on pass');
        } finally {
            self::removeDirRecursive($tmpRoot);
        }
    }

    public function test_allowlisted_console_output_file_is_ignored_even_if_it_contains_echo(): void
    {
        $gate = self::gateScriptPath();

        $tmpRoot = self::createTempScanRoot();
        try {
            // Cement: allowlisted file spikes/_support/ConsoleOutput.php is ignored by the gate.
            self::writePhp($tmpRoot . '/spikes/_support/ConsoleOutput.php', <<<'PHP'
                <?php

                declare(strict_types=1);

                echo "x";
                PHP
            );

            $r = self::runPhpScript($gate, ['--path=' . $tmpRoot], $tmpRoot);

            self::assertSame(0, $r['exitCode'], self::debugProcessResult($r));
            self::assertSame('', trim($r['stdout']), 'stdout must be empty on pass');
            self::assertSame('', trim($r['stderr']), 'stderr must be empty on pass');
        } finally {
            self::removeDirRecursive($tmpRoot);
        }
    }

    public function test_path_override_mode_missing_scan_root_fails_with_scan_failed_code_only(): void
    {
        $gate = self::gateScriptPath();

        $missing = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/')
            . '/coretsia_missing_scan_root_' . bin2hex(random_bytes(8));

        // Intentionally do not create it.
        $r = self::runPhpScript($gate, ['--path=' . $missing], sys_get_temp_dir());

        self::assertSame(1, $r['exitCode'], self::debugProcessResult($r));

        $payload = self::pickPayload($r);
        $lines = self::lines($payload);

        self::assertNotSame([], $lines, 'expected non-empty output on failure');
        self::assertSame('CORETSIA_SPIKES_OUTPUT_GATE_SCAN_FAILED', $lines[0], self::debugProcessResult($r));

        self::assertPayloadIsSafe($payload);
    }

    #[DataProvider('bypassCases')]
    public function test_detects_output_bypass_constructs_in_path_override_mode(
        string $relativePath,
        string $phpContents,
        string $expectedDiagnosticPath
    ): void
    {
        $gate = self::gateScriptPath();

        $tmpRoot = self::createTempScanRoot();
        try {
            self::writePhp($tmpRoot . '/' . $relativePath, $phpContents);

            $r = self::runPhpScript($gate, ['--path=' . $tmpRoot], $tmpRoot);

            self::assertSame(1, $r['exitCode'], self::debugProcessResult($r));

            $payload = self::pickPayload($r);
            $lines = self::lines($payload);

            self::assertNotSame([], $lines, 'expected non-empty output on failure');
            self::assertSame('CORETSIA_SPIKES_OUTPUT_BYPASS_DETECTED', $lines[0], self::debugProcessResult($r));

            $expectedLine = $expectedDiagnosticPath . ': output-bypass';

            // Single violation => exactly 2 lines (CODE + 1 diagnostic).
            self::assertCount(2, $lines, self::debugProcessResult($r));
            self::assertSame($expectedLine, $lines[1], self::debugProcessResult($r));

            self::assertPayloadIsSafe($payload);
        } finally {
            self::removeDirRecursive($tmpRoot);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function bypassCases(): array
    {
        return [
            'echo in spikes/**' => [
                'spikes/fingerprint/echo.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                echo "x";
                PHP,
                'spikes/fingerprint/echo.php',
            ],
            'print in spikes/**' => [
                'spikes/fingerprint/print.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                print "x";
                PHP,
                'spikes/fingerprint/print.php',
            ],
            'var_dump in spikes/**' => [
                'spikes/fingerprint/var_dump.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                var_dump(123);
                PHP,
                'spikes/fingerprint/var_dump.php',
            ],
            'print_r in spikes/**' => [
                'spikes/fingerprint/print_r.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                print_r([1, 2, 3]);
                PHP,
                'spikes/fingerprint/print_r.php',
            ],
            'printf in spikes/**' => [
                'spikes/fingerprint/printf.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                printf("x");
                PHP,
                'spikes/fingerprint/printf.php',
            ],
            'vprintf in spikes/**' => [
                'spikes/fingerprint/vprintf.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                vprintf("%s", ["x"]);
                PHP,
                'spikes/fingerprint/vprintf.php',
            ],
            'echo in gates/**' => [
                'gates/bad_gate.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                echo "x";
                PHP,
                'gates/bad_gate.php',
            ],
            'fwrite(STDOUT, ...)' => [
                'spikes/fingerprint/fwrite_stdout.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                fwrite(STDOUT, "x");
                PHP,
                'spikes/fingerprint/fwrite_stdout.php',
            ],
            'fputs(STDERR, ...)' => [
                'spikes/fingerprint/fputs_stderr.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                fputs(STDERR, "x");
                PHP,
                'spikes/fingerprint/fputs_stderr.php',
            ],
            'fprintf(STDOUT, ...)' => [
                'spikes/fingerprint/fprintf_stdout.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                fprintf(STDOUT, "%s", "x");
                PHP,
                'spikes/fingerprint/fprintf_stdout.php',
            ],
            "string sink 'php://stdout' literal" => [
                'spikes/fingerprint/php_stdout_literal.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                $x = 'php://stdout';
                PHP,
                'spikes/fingerprint/php_stdout_literal.php',
            ],
            "file_put_contents('php://stderr', ...)" => [
                'spikes/fingerprint/php_stderr.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                file_put_contents('php://stderr', "x");
                PHP,
                'spikes/fingerprint/php_stderr.php',
            ],
            "string sink 'php://output' literal" => [
                'spikes/fingerprint/php_output_literal.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                $x = 'php://output';
                PHP,
                'spikes/fingerprint/php_output_literal.php',
            ],
            'error_log(...)' => [
                'spikes/fingerprint/error_log.php',
                <<<'PHP'
                <?php

                declare(strict_types=1);

                error_log("x");
                PHP,
                'spikes/fingerprint/error_log.php',
            ],
        ];
    }

    private static function gateScriptPath(): string
    {
        // __DIR__ = framework/tools/spikes/tests; dirname(..., 2) => framework/tools
        $toolsRoot = realpath(\dirname(__DIR__, 2));
        if (!\is_string($toolsRoot) || $toolsRoot === '') {
            throw new \RuntimeException('tools-root-missing');
        }

        $gate = $toolsRoot . '/gates/spikes_output_gate.php';
        if (!is_file($gate)) {
            throw new \RuntimeException('spikes-output-gate-missing');
        }

        return $gate;
    }

    private static function createTempScanRoot(): string
    {
        $base = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/');
        $dir = $base . '/coretsia_spikes_gate_scan_' . bin2hex(random_bytes(10));

        self::mkdirp($dir . '/spikes');
        self::mkdirp($dir . '/gates');

        // Keep expected structure ready (includes + excludes + allowlist paths).
        self::mkdirp($dir . '/spikes/_support');
        self::mkdirp($dir . '/spikes/tests');
        self::mkdirp($dir . '/spikes/fixtures');
        self::mkdirp($dir . '/gates/tests');
        self::mkdirp($dir . '/gates/fixtures');

        return $dir;
    }

    private static function mkdirp(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (@mkdir($dir, 0777, true) !== true && !is_dir($dir)) {
            throw new \RuntimeException('mkdir-failed');
        }
    }

    private static function writePhp(string $absPath, string $contents): void
    {
        self::mkdirp(\dirname($absPath));

        $bytes = @file_put_contents($absPath, $contents);
        if ($bytes === false) {
            throw new \RuntimeException('write-failed');
        }
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private static function runPhpScript(string $script, array $args, ?string $cwd): array
    {
        $cmd = array_merge([PHP_BINARY, $script], $args);

        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $pipes = [];
        $proc = @proc_open($cmd, $spec, $pipes, $cwd ?: null);

        if (!\is_resource($proc)) {
            return ['exitCode' => 1, 'stdout' => '', 'stderr' => 'proc-open-failed'];
        }

        if (isset($pipes[0]) && \is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $stdout = '';
        $stderr = '';

        if (isset($pipes[1]) && \is_resource($pipes[1])) {
            $stdout = (string)stream_get_contents($pipes[1]);
            fclose($pipes[1]);
        }

        if (isset($pipes[2]) && \is_resource($pipes[2])) {
            $stderr = (string)stream_get_contents($pipes[2]);
            fclose($pipes[2]);
        }

        $exit = proc_close($proc);

        return ['exitCode' => (int)$exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private static function pickPayload(array $r): string
    {
        $stderr = trim((string)($r['stderr'] ?? ''));
        $stdout = trim((string)($r['stdout'] ?? ''));

        if ($stderr !== '') {
            return $stderr;
        }

        return $stdout;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $payload): array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }

        $parts = preg_split('/\r\n|\r|\n/', $payload);
        if (!\is_array($parts)) {
            return [];
        }

        $out = [];
        foreach ($parts as $p) {
            if (!\is_string($p)) {
                continue;
            }
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $out[] = $p;
        }

        /** @var list<string> $out */
        return $out;
    }

    private static function assertPayloadIsSafe(string $payload): void
    {
        // Must not leak absolute paths or dotenv-like secrets.
        self::assertSame(0, preg_match('~(?i)\b[A-Z]:[\\\\/]~', $payload), 'payload MUST NOT contain drive-letter paths');
        self::assertSame(0, preg_match('~\\\\\\\\\S+~', $payload), 'payload MUST NOT contain UNC paths');
        self::assertFalse(str_contains($payload, '/home/'), 'payload MUST NOT contain /home/');
        self::assertFalse(str_contains($payload, '/Users/'), 'payload MUST NOT contain /Users/');
        self::assertSame(
            0,
            preg_match('~\b(?:TOKEN|PASSWORD|AUTH|COOKIE)\s*=\s*\S+~i', $payload),
            'payload MUST NOT contain dotenv-like secret patterns'
        );
    }

    private static function debugProcessResult(array $r): string
    {
        return 'exit=' . (string)($r['exitCode'] ?? 'n/a')
            . "\nstdout:\n" . (string)($r['stdout'] ?? '')
            . "\nstderr:\n" . (string)($r['stderr'] ?? '');
    }

    private static function removeDirRecursive(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($it as $item) {
            $path = $item->getPathname();

            if ($item->isDir()) {
                @rmdir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
