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

namespace Coretsia\Tools\Spikes\fingerprint;

use Coretsia\Devtools\InternalToolkit\Path;
use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

/**
 * Safe, deterministic explain builder for the fingerprint spike.
 *
 * MUST (epic 0.60.0):
 *  - list changed file paths as normalized repo-relative paths only (forward slashes)
 *  - list changed tracked env keys by name only (no values)
 *  - NEVER print dotenv values; only safe forms are allowed (hash(value), len(value))
 *
 * Notes:
 *  - This class performs NO IO over snapshot paths. It only diffs caller-provided snapshots.
 *  - Output ordering is deterministic (byte-order strcmp).
 *  - Dotenv bucket is cemented to accept ONLY safe meta: {sha256,len}. No raw values.
 */
final class FingerprintExplainer
{
    private const int SCHEMA_VERSION = 1;

    private const string MSG_REPO_ROOT_INVALID = 'fingerprint-explain-repo-root-invalid';
    private const string MSG_INPUT_INVALID = 'fingerprint-explain-input-invalid';
    private const string MSG_DUPLICATE_NORMALIZED_PATH = 'fingerprint-explain-duplicate-normalized-path';
    private const string MSG_DOTENV_META_REQUIRED = 'fingerprint-explain-dotenv-meta-required';
    private const string MSG_INVALID_SHA256 = 'fingerprint-explain-invalid-sha256';

    private string $repoRootAbs;

    /**
     * @throws DeterministicException
     */
    public function __construct(string $repoRootAbs)
    {
        $repoRootAbs = \rtrim(\trim($repoRootAbs), "/\\");
        if ($repoRootAbs === '' || !\is_dir($repoRootAbs)) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_REPO_ROOT_INVALID,
                self::MSG_REPO_ROOT_INVALID,
            );
        }

        /**
         * IMPORTANT (Windows):
         * - DO NOT realpath() here.
         *   realpath() may return an 8.3 short-path variant, while snapshot keys may use long paths.
         *   Path::normalizeRelative(...) is lexical and must see the same root form as the snapshot inputs.
         *
         * We only validate that repoRoot is absolute/valid by invoking the lexical normalizer
         * against itself (no dependency on filesystem existence of snapshot paths).
         */
        try {
            Path::normalizeRelative($repoRootAbs, $repoRootAbs);
        } catch (\InvalidArgumentException) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_REPO_ROOT_INVALID,
                self::MSG_REPO_ROOT_INVALID,
            );
        }

        $this->repoRootAbs = $repoRootAbs;
    }

    public function repoRoot(): string
    {
        return $this->repoRootAbs;
    }

    /**
     * Builds a deterministic, safe explain payload for two snapshots.
     *
     * Snapshot contract (caller-provided; NO IO here):
     *  - buckets['code'|'config'] is map: path => digest(string, e.g. sha256 hex)
     *  - buckets['dotenv'] is map: path => array{sha256:string, len:int} (SAFE META ONLY)
     *  - buckets['tracked_env'] is map: ENV_KEY_NAME => token (scalar); token is NEVER printed
     *
     * Output contract:
     *  - changed file paths are ALWAYS normalized repo-relative (forward slashes)
     *  - tracked env changes list key names ONLY (no values)
     *  - dotenv values are NEVER printed; only {sha256,len} is allowed when requested
     *
     * @param array<string, array> $beforeBuckets
     * @param array<string, array> $afterBuckets
     *
     * @throws DeterministicException
     */
    public function explain(array $beforeBuckets, array $afterBuckets, bool $includeDotenvSafeMeta = false): array
    {
        $codeBefore = $this->normalizeFileSnapshotDigestOnly($beforeBuckets['code'] ?? []);
        $codeAfter = $this->normalizeFileSnapshotDigestOnly($afterBuckets['code'] ?? []);

        $configBefore = $this->normalizeFileSnapshotDigestOnly($beforeBuckets['config'] ?? []);
        $configAfter = $this->normalizeFileSnapshotDigestOnly($afterBuckets['config'] ?? []);

        $dotenvBefore = $this->normalizeDotenvSnapshotMetaOnly($beforeBuckets['dotenv'] ?? []);
        $dotenvAfter = $this->normalizeDotenvSnapshotMetaOnly($afterBuckets['dotenv'] ?? []);

        $trackedBefore = $this->normalizeTrackedEnvSnapshot($beforeBuckets['tracked_env'] ?? []);
        $trackedAfter = $this->normalizeTrackedEnvSnapshot($afterBuckets['tracked_env'] ?? []);

        $codeDiff = $this->diffFileSnapshot($codeBefore, $codeAfter);
        $configDiff = $this->diffFileSnapshot($configBefore, $configAfter);
        $dotenvDiff = $this->diffFileSnapshot($dotenvBefore, $dotenvAfter);

        $trackedDiff = $this->diffScalarMap($trackedBefore, $trackedAfter);

        $allChanged = $this->mergeChangedPaths([
            $codeDiff['added'], $codeDiff['removed'], $codeDiff['modified'],
            $configDiff['added'], $configDiff['removed'], $configDiff['modified'],
            $dotenvDiff['added'], $dotenvDiff['removed'], $dotenvDiff['modified'],
        ]);

        $out = [
            'schema_version' => self::SCHEMA_VERSION,
            'changed_files' => [
                'code' => $codeDiff,
                'config' => $configDiff,
                'dotenv' => $dotenvDiff,
            ],
            'changed_tracked_env_keys' => [
                'added' => $trackedDiff['added'],
                'removed' => $trackedDiff['removed'],
                'changed' => $trackedDiff['changed'],
            ],
            'changed_file_paths' => $allChanged,
        ];

        if ($includeDotenvSafeMeta) {
            $out['dotenv_safe_meta'] = $this->buildDotenvSafeMeta($dotenvBefore, $dotenvAfter, $dotenvDiff);
        }

        /** @var array<string,mixed> $out */
        return $out;
    }

    /**
     * Deterministic stable JSON convenience wrapper.
     *
     * @param array<string,mixed> $explain
     * @throws DeterministicException
     */
    public function toStableJson(array $explain): string
    {
        return StableJsonEncoder::encode($explain);
    }

    /**
     * @param array $snapshot map<path, digest>
     * @return array<string, string> map<normalizedRelPath, digest>
     */
    private function normalizeFileSnapshotDigestOnly(array $snapshot): array
    {
        $out = [];

        foreach ($snapshot as $k => $v) {
            if (!\is_string($k) || $k === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_INPUT_INVALID,
                    self::MSG_INPUT_INVALID,
                );
            }
            if (!\is_string($v) || $v === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_INPUT_INVALID,
                    self::MSG_INPUT_INVALID,
                );
            }

            $rel = Path::normalizeRelative($k, $this->repoRootAbs);
            if ($rel === '.' || $rel === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_INPUT_INVALID,
                    self::MSG_INPUT_INVALID,
                );
            }

            $this->assertSha256Hex($v);

            if (isset($out[$rel])) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_DUPLICATE_NORMALIZED_PATH,
                    self::MSG_DUPLICATE_NORMALIZED_PATH,
                );
            }

            $out[$rel] = $v;
        }

        \uksort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $out;
    }

    /**
     * Dotenv bucket is cemented to SAFE META ONLY:
     *  - sha256: hex
     *  - len: byte length (>=0)
     *
     * @param array $snapshot map<path, array{sha256:string,len:int}>
     * @return array<string, array{sha256:string, len:int}> map<normalizedRelPath, meta>
     */
    private function normalizeDotenvSnapshotMetaOnly(array $snapshot): array
    {
        $out = [];

        foreach ($snapshot as $k => $v) {
            if (!\is_string($k) || $k === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_INPUT_INVALID,
                    self::MSG_INPUT_INVALID,
                );
            }
            if (!\is_array($v)) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_DOTENV_META_REQUIRED,
                    self::MSG_DOTENV_META_REQUIRED,
                );
            }

            $sha = $v['sha256'] ?? null;
            $len = $v['len'] ?? null;

            if (!\is_string($sha) || $sha === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_DOTENV_META_REQUIRED,
                    self::MSG_DOTENV_META_REQUIRED,
                );
            }
            if (!\is_int($len) || $len < 0) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_DOTENV_META_REQUIRED,
                    self::MSG_DOTENV_META_REQUIRED,
                );
            }

            $this->assertSha256Hex($sha);

            $rel = Path::normalizeRelative($k, $this->repoRootAbs);
            if ($rel === '.' || $rel === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_INPUT_INVALID,
                    self::MSG_INPUT_INVALID,
                );
            }

            if (isset($out[$rel])) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_DUPLICATE_NORMALIZED_PATH,
                    self::MSG_DUPLICATE_NORMALIZED_PATH,
                );
            }

            $out[$rel] = ['sha256' => $sha, 'len' => $len];
        }

        \uksort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $out;
    }

    /**
     * @param array $snapshot map<envKey, token>
     * @return array<string, string> map<envKey, tokenString>
     */
    private function normalizeTrackedEnvSnapshot(array $snapshot): array
    {
        $out = [];

        foreach ($snapshot as $k => $v) {
            if (!\is_string($k) || $k === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_INPUT_INVALID,
                    self::MSG_INPUT_INVALID,
                );
            }

            if (\is_array($v) || \is_object($v) || \is_resource($v)) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_INPUT_INVALID,
                    self::MSG_INPUT_INVALID,
                );
            }

            $out[$k] = (string)$v;
        }

        \uksort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $out;
    }

    /**
     * @param array<string, string|array{sha256:string, len:int}> $before
     * @param array<string, string|array{sha256:string, len:int}> $after
     * @return array{added:list<string>, removed:list<string>, modified:list<string>}
     */
    private function diffFileSnapshot(array $before, array $after): array
    {
        $added = [];
        $removed = [];
        $modified = [];

        foreach ($after as $path => $vAfter) {
            if (!\array_key_exists($path, $before)) {
                $added[] = $path;
                continue;
            }

            $vBefore = $before[$path];

            if (!$this->valueEquals($vBefore, $vAfter)) {
                $modified[] = $path;
            }
        }

        foreach ($before as $path => $_vBefore) {
            if (!\array_key_exists($path, $after)) {
                $removed[] = $path;
            }
        }

        \usort($added, static fn (string $a, string $b): int => \strcmp($a, $b));
        \usort($removed, static fn (string $a, string $b): int => \strcmp($a, $b));
        \usort($modified, static fn (string $a, string $b): int => \strcmp($a, $b));

        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified,
        ];
    }

    /**
     * @param array<string,string> $before
     * @param array<string,string> $after
     * @return array{added:list<string>, removed:list<string>, changed:list<string>}
     */
    private function diffScalarMap(array $before, array $after): array
    {
        $added = [];
        $removed = [];
        $changed = [];

        foreach ($after as $k => $vAfter) {
            if (!\array_key_exists($k, $before)) {
                $added[] = $k;
                continue;
            }

            if ($before[$k] !== $vAfter) {
                $changed[] = $k;
            }
        }

        foreach ($before as $k => $_vBefore) {
            if (!\array_key_exists($k, $after)) {
                $removed[] = $k;
            }
        }

        \usort($added, static fn (string $a, string $b): int => \strcmp($a, $b));
        \usort($removed, static fn (string $a, string $b): int => \strcmp($a, $b));
        \usort($changed, static fn (string $a, string $b): int => \strcmp($a, $b));

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    private function valueEquals(string|array $a, string|array $b): bool
    {
        if (\is_string($a) && \is_string($b)) {
            return $a === $b;
        }

        if (\is_array($a) && \is_array($b)) {
            return ($a['sha256'] ?? null) === ($b['sha256'] ?? null)
                && ($a['len'] ?? null) === ($b['len'] ?? null);
        }

        return false;
    }

    /**
     * @param list<list<string>> $groups
     * @return list<string> unique, sorted by strcmp
     */
    private function mergeChangedPaths(array $groups): array
    {
        $set = [];

        foreach ($groups as $list) {
            foreach ($list as $p) {
                if (!\is_string($p) || $p === '') {
                    continue;
                }
                $set[$p] = true;
            }
        }

        $out = \array_keys($set);
        \usort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        /** @var list<string> $out */
        return $out;
    }

    /**
     * Builds safe dotenv meta (sha256 + len only) for changed dotenv files.
     *
     * @param array<string, array{sha256:string, len:int}> $before
     * @param array<string, array{sha256:string, len:int}> $after
     * @param array{added:list<string>, removed:list<string>, modified:list<string>} $diff
     * @return array<string, array{before?:array{sha256:string,len:int}, after?:array{sha256:string,len:int}}>
     */
    private function buildDotenvSafeMeta(array $before, array $after, array $diff): array
    {
        $out = [];

        $all = $this->mergeChangedPaths([$diff['added'], $diff['removed'], $diff['modified']]);

        foreach ($all as $path) {
            $row = [];

            if (\array_key_exists($path, $before)) {
                $meta = $before[$path];
                $row['before'] = ['sha256' => $meta['sha256'], 'len' => $meta['len']];
            }

            if (\array_key_exists($path, $after)) {
                $meta = $after[$path];
                $row['after'] = ['sha256' => $meta['sha256'], 'len' => $meta['len']];
            }

            if ($row !== []) {
                $out[$path] = $row;
            }
        }

        \uksort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $out;
    }

    private function assertSha256Hex(string $hex): void
    {
        if (\strlen($hex) !== 64 || \preg_match('/\A[a-f0-9]{64}\z/', $hex) !== 1) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_FINGERPRINT_EXPLAIN_INVALID_SHA256,
                self::MSG_INVALID_SHA256,
            );
        }
    }
}
