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

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\_support\FixtureRoot;

/**
 * Deterministic fingerprint calculator for the repo_min fixture.
 *
 * Cemented bucket taxonomy (epic 0.60.0):
 *  - code: everything except config PHP files and .env* files
 *  - config: config/*\*.php + apps/*\/config/**.php
 *  - dotenv: .env* files (no parsing; file-based hashing only)
 *  - tracked_env: allowlisted env keys from fixtures/repo_min/tracked_env_allowlist.php
 *  - schema_versions: fixed schema constants included as inputs
 *
 * MUST:
 *  - file hashing MUST use DeterministicFile::hashSha256NormalizedEol()
 *  - ordering MUST be byte-order (strcmp), locale-independent
 *  - final fingerprint MUST be:
 *      sha256( StableJsonEncoder::encode( bucketsMapSortedByKey ) )
 *
 * Security:
 *  - This class NEVER prints anything.
 *  - Optional snapshots returned by calculate(...) contain ONLY safe data:
 *      - file paths are repo-relative only
 *      - dotenv snapshot values are {sha256,len} only (no raw content)
 *      - tracked_env snapshot values are tokens (presence + sha256), never raw values
 */
final class FingerprintCalculator
{
    private const int SCHEMA_VERSION = 1;
    private const int SCHEMA_BUCKET_FILES_V1 = 1;
    private const int SCHEMA_BUCKET_TRACKED_ENV_V1 = 1;

    private const string MSG_REPO_ROOT_INVALID = 'fingerprint-repo-root-invalid';
    private const string MSG_ALLOWLIST_INVALID = 'fingerprint-tracked-env-allowlist-invalid';
    private const string MSG_ALLOWLIST_NOT_SORTED = 'fingerprint-tracked-env-allowlist-not-sorted';
    private const string MSG_ALLOWLIST_DUPLICATE = 'fingerprint-tracked-env-allowlist-duplicate';
    private const string MSG_FINAL_HASH_FAILED = 'fingerprint-final-hash-failed';
    private const string MSG_BUCKET_HASH_FAILED = 'fingerprint-bucket-hash-failed';
    private const string MSG_TRACKED_ENV_HASH_FAILED = 'fingerprint-tracked-env-hash-failed';

    private string $repoRootAbs;

    /**
     * @throws DeterministicException
     */
    public function __construct()
    {
        try {
            $root = FixtureRoot::path('repo_min/skeleton');
        } catch (\Throwable $e) {
            self::failRepoRootInvalid($e);
        }

        $rp = \realpath($root);
        if (!\is_string($rp) || $rp === '' || !\is_dir($rp)) {
            self::failRepoRootInvalid();
        }

        $this->repoRootAbs = $rp;
    }

    public function repoRoot(): string
    {
        return $this->repoRootAbs;
    }

    /**
     * Computes fingerprint and optionally safe explain snapshots.
     *
     * Returned structure is intentionally DTO-like to be friendly for StableJsonEncoder / explain tooling.
     *
     * @return array{
     *   fingerprint: string,
     *   bucket_digests: array<string,string>,
     *   snapshots?: array{
     *     code: array<string,string>,
     *     config: array<string,string>,
     *     dotenv: array<string, array{sha256:string,len:int}>,
     *     tracked_env: array<string,string>
     *   }
     * }
     *
     * @throws DeterministicException
     */
    public function calculate(bool $includeSnapshots = false): array
    {
        $paths = $this->listAllRepoFiles();

        $dotenvPaths = [];
        $configPaths = [];
        $codePaths = [];

        foreach ($paths as $rel) {
            if ($this->isDotenvPath($rel)) {
                $dotenvPaths[] = $rel;
                continue;
            }

            if ($this->isConfigPhpPath($rel)) {
                $configPaths[] = $rel;
                continue;
            }

            // Code bucket (cemented): everything else under repo root.
            $codePaths[] = $rel;
        }

        $codeSnapshot = $this->hashFilesToSnapshot($codePaths);
        $configSnapshot = $this->hashFilesToSnapshot($configPaths);
        $dotenvSnapshot = $this->hashDotenvFilesToSnapshot($dotenvPaths);

        $allowlist = $this->loadTrackedEnvAllowlist();
        $trackedSnapshot = $this->computeTrackedEnvSnapshot($allowlist);

        $bucketDigests = [
            'code' => $this->digestFilesBucket($codeSnapshot),
            'config' => $this->digestFilesBucket($configSnapshot),
            'dotenv' => $this->digestFilesBucket($this->dotenvSnapshotToShaMap($dotenvSnapshot)),
            'tracked_env' => $this->digestTrackedEnvBucket($allowlist, $trackedSnapshot),
            'schema_versions' => $this->digestSchemaVersionsBucket(),
        ];

        \uksort($bucketDigests, static fn (string $a, string $b): int => \strcmp($a, $b));

        $encodedBuckets = StableJsonEncoder::encode($bucketDigests);
        $final = self::hashSha256OrFail($encodedBuckets, self::MSG_FINAL_HASH_FAILED);

        $out = [
            'fingerprint' => $final,
            'bucket_digests' => $bucketDigests,
        ];

        if ($includeSnapshots) {
            $out['snapshots'] = [
                'code' => $codeSnapshot,
                'config' => $configSnapshot,
                'dotenv' => $dotenvSnapshot,
                'tracked_env' => $trackedSnapshot,
            ];
        }

        /** @var array{
         *   fingerprint:string,
         *   bucket_digests:array<string,string>,
         *   snapshots?:array{
         *     code: array<string,string>,
         *     config: array<string,string>,
         *     dotenv: array<string, array{sha256:string,len:int}>,
         *     tracked_env: array<string,string>
         *   }
         * } $out
         */
        return $out;
    }

    /**
     * @return list<string>
     *
     * @throws DeterministicException
     */
    private function listAllRepoFiles(): array
    {
        $lister = new DeterministicFileLister($this->repoRootAbs);

        $paths = $lister->listAllFiles();

        \usort($paths, static fn (string $a, string $b): int => \strcmp($a, $b));

        /** @var list<string> $paths */
        return $paths;
    }

    private function isDotenvPath(string $rel): bool
    {
        $name = \basename(\str_replace('\\', '/', $rel));

        return \str_starts_with($name, '.env');
    }

    private function isConfigPhpPath(string $rel): bool
    {
        if (!\str_ends_with($rel, '.php')) {
            return false;
        }

        if (\str_starts_with($rel, 'config/')) {
            return true;
        }

        return \preg_match('~\Aapps/[^/]+/config/~', $rel) === 1;
    }

    /**
     * @param list<string> $paths
     * @return array<string,string>
     *
     * @throws DeterministicException
     */
    private function hashFilesToSnapshot(array $paths): array
    {
        \usort($paths, static fn (string $a, string $b): int => \strcmp($a, $b));

        $out = [];

        foreach ($paths as $rel) {
            $abs = $this->absFromRel($rel);

            $sha = DeterministicFile::hashSha256NormalizedEol($abs);
            $out[$rel] = $sha;
        }

        \uksort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $out;
    }

    /**
     * Dotenv snapshot is safe meta only: {sha256,len} (no raw content).
     *
     * @param list<string> $paths
     * @return array<string,array{sha256:string,len:int}>
     *
     * @throws DeterministicException
     */
    private function hashDotenvFilesToSnapshot(array $paths): array
    {
        \usort($paths, static fn (string $a, string $b): int => \strcmp($a, $b));

        $out = [];

        foreach ($paths as $rel) {
            $abs = $this->absFromRel($rel);

            $sha = DeterministicFile::hashSha256NormalizedEol($abs);
            $normalized = DeterministicFile::readTextNormalizedEol($abs);
            $len = \strlen($normalized);

            $out[$rel] = [
                'sha256' => $sha,
                'len' => $len,
            ];
        }

        \uksort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $out;
    }

    /**
     * @param array<string,array{sha256:string,len:int}> $dotenvSnapshot
     * @return array<string,string>
     */
    private function dotenvSnapshotToShaMap(array $dotenvSnapshot): array
    {
        $out = [];

        foreach ($dotenvSnapshot as $rel => $meta) {
            $out[$rel] = (string)($meta['sha256'] ?? '');
        }

        \uksort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $out;
    }

    /**
     * @param array<string,string> $snapshot
     *
     * @throws DeterministicException
     */
    private function digestFilesBucket(array $snapshot): string
    {
        $entries = [];

        $paths = \array_keys($snapshot);
        \usort($paths, static fn (string $a, string $b): int => \strcmp($a, $b));

        foreach ($paths as $rel) {
            $entries[] = [
                'path' => $rel,
                'sha256' => $snapshot[$rel],
            ];
        }

        $json = StableJsonEncoder::encode([
            'schema' => self::SCHEMA_BUCKET_FILES_V1,
            'files' => $entries,
        ]);

        return self::hashSha256OrFail($json, self::MSG_BUCKET_HASH_FAILED);
    }

    /**
     * Loads the cemented allowlist and validates:
     *  - list<string>
     *  - unique
     *  - strictly sorted by strcmp (byte-order)
     *
     * @return list<string>
     *
     * @throws DeterministicException
     */
    private function loadTrackedEnvAllowlist(): array
    {
        $path = FixtureRoot::path('repo_min/tracked_env_allowlist.php');

        /** @var mixed $value */
        $value = require $path;

        if (!\is_array($value) || !\array_is_list($value)) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_FINGERPRINT_TRACKED_ENV_ALLOWLIST_INVALID,
                self::MSG_ALLOWLIST_INVALID,
            );
        }

        /** @var list<string> $list */
        $list = [];

        $seen = [];
        $prev = null;

        foreach ($value as $k) {
            if (!\is_string($k) || $k === '') {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_TRACKED_ENV_ALLOWLIST_INVALID,
                    self::MSG_ALLOWLIST_INVALID,
                );
            }

            if (isset($seen[$k])) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_TRACKED_ENV_ALLOWLIST_DUPLICATE,
                    self::MSG_ALLOWLIST_DUPLICATE,
                );
            }
            $seen[$k] = true;

            if ($prev !== null && \strcmp($prev, $k) >= 0) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_TRACKED_ENV_ALLOWLIST_NOT_SORTED,
                    self::MSG_ALLOWLIST_NOT_SORTED,
                );
            }

            $prev = $k;
            $list[] = $k;
        }

        return $list;
    }

    /**
     * Computes tracked_env snapshot as token strings, never raw values.
     *
     * Token format (cemented):
     *  - missing:  "0"
     *  - present:  "1:<sha256(value)>"
     *
     * Missing vs empty is distinguishable:
     *  - getenv($k) === false => missing ("0")
     *  - getenv($k) === ''    => present ("1:<sha256('')>")
     *
     * @param list<string> $allowlist
     * @return array<string,string>
     *
     * @throws DeterministicException
     */
    private function computeTrackedEnvSnapshot(array $allowlist): array
    {
        $out = [];

        foreach ($allowlist as $key) {
            $v = \getenv($key);

            if ($v === false) {
                $out[$key] = '0';
                continue;
            }

            $sha = self::hashSha256OrFail((string)$v, self::MSG_TRACKED_ENV_HASH_FAILED);
            $out[$key] = '1:' . $sha;
        }

        \uksort($out, static fn (string $a, string $b): int => \strcmp($a, $b));

        return $out;
    }

    /**
     * tracked_env bucket digest is sha256 of a stable JSON list of entries in allowlist order:
     *  - {key, present} always
     *  - {sha256} only when present
     *
     * @param list<string> $allowlist
     * @param array<string,string> $snapshot
     *
     * @throws DeterministicException
     */
    private function digestTrackedEnvBucket(array $allowlist, array $snapshot): string
    {
        $entries = [];

        foreach ($allowlist as $key) {
            $token = $snapshot[$key] ?? '0';

            if ($token === '0') {
                $entries[] = [
                    'key' => $key,
                    'present' => 0,
                ];
                continue;
            }

            if (!\str_starts_with($token, '1:')) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_RESULT_INVALID,
                    ErrorCodes::CORETSIA_FINGERPRINT_RESULT_INVALID,
                );
            }

            $sha = \substr($token, 2);
            if (!\is_string($sha) || \preg_match('/\A[a-f0-9]{64}\z/', $sha) !== 1) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_FINGERPRINT_RESULT_INVALID,
                    ErrorCodes::CORETSIA_FINGERPRINT_RESULT_INVALID,
                );
            }

            $entries[] = [
                'key' => $key,
                'present' => 1,
                'sha256' => $sha,
            ];
        }

        $json = StableJsonEncoder::encode([
            'schema' => self::SCHEMA_BUCKET_TRACKED_ENV_V1,
            'keys' => $entries,
        ]);

        return self::hashSha256OrFail($json, self::MSG_BUCKET_HASH_FAILED);
    }

    /**
     * @throws DeterministicException
     */
    private function digestSchemaVersionsBucket(): string
    {
        $schema = [
            'fingerprint_calculator' => self::SCHEMA_VERSION,
            'bucket_files' => self::SCHEMA_BUCKET_FILES_V1,
            'bucket_tracked_env' => self::SCHEMA_BUCKET_TRACKED_ENV_V1,
        ];

        $json = StableJsonEncoder::encode($schema);

        return self::hashSha256OrFail($json, self::MSG_BUCKET_HASH_FAILED);
    }

    private function absFromRel(string $rel): string
    {
        $relFs = \str_replace('\\', '/', $rel);
        $relOs = \str_replace('/', \DIRECTORY_SEPARATOR, $relFs);

        return \rtrim($this->repoRootAbs, '/\\') . \DIRECTORY_SEPARATOR . \ltrim($relOs, '/\\');
    }

    /**
     * @throws DeterministicException
     */
    private static function hashSha256OrFail(string $value, string $message): string
    {
        $hash = \hash('sha256', $value);

        if ($hash === false || $hash === '') {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_FINGERPRINT_HASH_FAILED,
                $message,
            );
        }

        return $hash;
    }

    /**
     * @throws DeterministicException
     */
    private static function failRepoRootInvalid(?\Throwable $previous = null): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_FINGERPRINT_REPO_ROOT_INVALID,
            self::MSG_REPO_ROOT_INVALID,
            $previous,
        );
    }
}
