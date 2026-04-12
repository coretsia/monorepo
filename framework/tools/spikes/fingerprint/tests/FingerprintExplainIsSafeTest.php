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

namespace Coretsia\Tools\Spikes\fingerprint\tests;

use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\fingerprint\FingerprintExplainer;
use PHPUnit\Framework\TestCase;

/**
 * Epic 0.60.0 (MUST):
 *  - Explain MUST NOT leak dotenv values; only safe meta is allowed.
 *  - Explain MUST NOT leak absolute paths; only normalized repo-relative paths.
 *  - tracked_env changes MUST list key names only (no values/tokens).
 */
final class FingerprintExplainIsSafeTest extends TestCase
{
    private ?string $tmpRoot = null;

    protected function tearDown(): void
    {
        if ($this->tmpRoot !== null) {
            $this->rmTree($this->tmpRoot);
        }

        $this->tmpRoot = null;
    }

    public function test_explain_payload_is_safe_and_repo_relative_only(): void
    {
        $repoRoot = $this->makeTempDir('repo_root');

        // Ensure repoRoot exists and has at least one directory entry (Windows friendliness).
        $this->mkdir($repoRoot . '/x');
        DeterministicFile::writeTextLf($repoRoot . '/x/.keep', "keep\n");

        $explainer = new FingerprintExplainer($repoRoot);

        // Invariant: explainer MUST NOT canonicalize repoRoot (Windows: avoid long/short path mismatch).
        self::assertSame(rtrim($repoRoot, "/\\"), $explainer->repoRoot());

        $before = [
            'code' => [
                $repoRoot . '/src/A.php' => hash('sha256', 'a1'),
                $repoRoot . '/src/B.php' => hash('sha256', 'b1'),
            ],
            'config' => [
                $repoRoot . '/config/app.php' => hash('sha256', 'c1'),
            ],
            'dotenv' => [
                $repoRoot . '/.env' => ['sha256' => hash('sha256', 'dotenv1'), 'len' => 11],
            ],
            'tracked_env' => [
                'CORETSIA_TRACKED_ENV_ALPHA' => 'TOKEN_SHOULD_NOT_LEAK',
            ],
        ];

        $after = [
            'code' => [
                $repoRoot . '/src/A.php' => hash('sha256', 'a2'), // modified
                $repoRoot . '/src/C.php' => hash('sha256', 'c2'), // added
            ],
            'config' => [
                $repoRoot . '/config/app.php' => hash('sha256', 'c1'), // unchanged
                $repoRoot . '/config/http.php' => hash('sha256', 'c3'), // added
            ],
            'dotenv' => [
                $repoRoot . '/.env' => ['sha256' => hash('sha256', 'dotenv2'), 'len' => 12], // modified meta
            ],
            'tracked_env' => [
                'CORETSIA_TRACKED_ENV_ALPHA' => 'TOKEN_SHOULD_NOT_LEAK_CHANGED',
                'CORETSIA_TRACKED_ENV_BETA' => 'TOKEN_SHOULD_NOT_LEAK_2',
            ],
        ];

        $out = $explainer->explain($before, $after, false);

        // MUST: only normalized repo-relative paths (forward slashes).
        self::assertSame([
            '.env',
            'config/http.php',
            'src/A.php',
            'src/B.php',
            'src/C.php',
        ], $out['changed_file_paths']);

        $json = $explainer->toStableJson($out);

        // Safety: no absolute paths.
        self::assertStringNotContainsString($repoRoot, $json);

        // Safety: no tracked_env tokens.
        self::assertStringNotContainsString('TOKEN_SHOULD_NOT_LEAK', $json);
        self::assertStringNotContainsString('TOKEN_SHOULD_NOT_LEAK_CHANGED', $json);

        // Safety: no dotenv raw values (we only provided meta; still guard obvious secrets).
        self::assertStringNotContainsString('dotenv1', $json);
        self::assertStringNotContainsString('dotenv2', $json);

        // Determinism: stable JSON is stable across reruns.
        self::assertSame($json, $explainer->toStableJson($out));

        // When safe meta is requested, ONLY {sha256,len} may appear (no raw content).
        $out2 = $explainer->explain($before, $after, true);
        $json2 = $explainer->toStableJson($out2);

        self::assertArrayHasKey('dotenv_safe_meta', $out2);
        self::assertIsArray($out2['dotenv_safe_meta']);

        self::assertStringNotContainsString($repoRoot, $json2);
        self::assertStringNotContainsString('TOKEN_SHOULD_NOT_LEAK', $json2);
        self::assertStringNotContainsString('dotenv1', $json2);
        self::assertStringNotContainsString('dotenv2', $json2);

        $safeMeta = $out2['dotenv_safe_meta']['.env'] ?? null;
        self::assertIsArray($safeMeta);
        self::assertSame([
            'before' => ['sha256' => hash('sha256', 'dotenv1'), 'len' => 11],
            'after' => ['sha256' => hash('sha256', 'dotenv2'), 'len' => 12],
        ], $safeMeta);
    }

    private function makeTempDir(string $suffix): string
    {
        if ($this->tmpRoot === null) {
            $base = rtrim(sys_get_temp_dir(), '/\\');
            $this->tmpRoot = $base . DIRECTORY_SEPARATOR . 'coretsia_spikes_explain_' . bin2hex(random_bytes(8));
            $this->mkdir($this->tmpRoot);
        }

        $dir = $this->tmpRoot . DIRECTORY_SEPARATOR . $suffix;
        $this->mkdir($dir);

        return $dir;
    }

    private function mkdir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (@mkdir($dir, 0777, true) !== true && !is_dir($dir)) {
            self::fail('mkdir-failed');
        }
    }

    private function rmTree(string $path): void
    {
        if ($path === '' || $path === DIRECTORY_SEPARATOR) {
            return;
        }

        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $info) {
            if (!$info instanceof \SplFileInfo) {
                continue;
            }

            $p = $info->getPathname();

            if ($info->isLink() || $info->isFile()) {
                @unlink($p);
                continue;
            }

            if ($info->isDir()) {
                @rmdir($p);
            }
        }

        @rmdir($path);
    }
}
