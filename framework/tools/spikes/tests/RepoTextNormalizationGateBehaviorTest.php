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

use PHPUnit\Framework\TestCase;

final class RepoTextNormalizationGateBehaviorTest extends TestCase
{
    public function test_default_mode_passes_on_real_repo_root_and_is_silent_and_cwd_independent(): void
    {
        $gate = self::gateScriptPath();

        $cwd = sys_get_temp_dir();

        $r = self::runPhpScript($gate, [], $cwd);

        self::assertSame(0, $r['exitCode'], self::debugProcessResult($r));
        self::assertSame('', trim($r['stdout']), 'stdout must be empty on pass');
        self::assertSame('', trim($r['stderr']), 'stderr must be empty on pass');
    }

    public function test_path_outside_repo_root_is_rejected_with_scan_failed_code_and_safe_payload(): void
    {
        $gate = self::gateScriptPath();

        $repoRoot = self::repoRootPath();
        $outside = realpath($repoRoot . DIRECTORY_SEPARATOR . '..');
        self::assertIsString($outside);
        self::assertNotSame('', $outside);

        $r = self::runPhpScript($gate, ['--path=' . $outside], sys_get_temp_dir());

        self::assertSame(1, $r['exitCode'], self::debugProcessResult($r));

        $payload = self::pickPayload($r);
        $lines = self::lines($payload);

        self::assertNotSame([], $lines, 'expected non-empty output on failure');
        self::assertSame('CORETSIA_REPO_TEXT_POLICY_SCAN_FAILED', $lines[0], self::debugProcessResult($r));

        self::assertPayloadIsSafe($payload);
    }

    private static function gateScriptPath(): string
    {
        // __DIR__ = framework/tools/spikes/tests; dirname(..., 2) => framework/tools
        $toolsRoot = realpath(\dirname(__DIR__, 2));
        if (!\is_string($toolsRoot) || $toolsRoot === '') {
            throw new \RuntimeException('tools-root-missing');
        }

        $gate = $toolsRoot . '/gates/repo_text_normalization_gate.php';
        if (!is_file($gate)) {
            throw new \RuntimeException('repo-text-normalization-gate-missing');
        }

        return $gate;
    }

    private static function repoRootPath(): string
    {
        $toolsRoot = realpath(\dirname(__DIR__, 2)); // framework/tools
        if (!\is_string($toolsRoot) || $toolsRoot === '') {
            throw new \RuntimeException('tools-root-missing');
        }

        $repoRoot = realpath($toolsRoot . '/..' . '/..');
        if (!\is_string($repoRoot) || $repoRoot === '') {
            throw new \RuntimeException('repo-root-missing');
        }

        return $repoRoot;
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
}
