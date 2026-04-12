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
use Coretsia\Tools\Spikes\_support\ErrorCodes;

final class FingerprintWorkflow
{
    private const string MSG_RESULT_INVALID = 'fingerprint-workflow-result-invalid';

    private function __construct()
    {
    }

    /**
     * @return array{
     *   fixture_repo_root_abs:string,
     *   fingerprint:string,
     *   bucket_digests:array<string,string>,
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
    public static function run(bool $includeSnapshots = false): array
    {
        $calculator = new FingerprintCalculator();
        $result = $calculator->calculate($includeSnapshots);

        $fingerprint = $result['fingerprint'] ?? null;
        $bucketDigests = $result['bucket_digests'] ?? null;

        if (!\is_string($fingerprint) || \preg_match('/\A[a-f0-9]{64}\z/', $fingerprint) !== 1) {
            self::failResultInvalid();
        }

        if (!\is_array($bucketDigests) || \array_is_list($bucketDigests)) {
            self::failResultInvalid();
        }

        foreach ($bucketDigests as $key => $value) {
            if (!\is_string($key) || $key === '' || !\is_string($value)) {
                self::failResultInvalid();
            }

            if (\preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
                self::failResultInvalid();
            }
        }

        $repoRoot = $calculator->repoRoot();
        if ($repoRoot === '') {
            self::failResultInvalid();
        }

        $out = [
            'fixture_repo_root_abs' => $repoRoot,
            'fingerprint' => $fingerprint,
            'bucket_digests' => $bucketDigests,
        ];

        if ($includeSnapshots === true) {
            $snapshots = $result['snapshots'] ?? null;

            if (!\is_array($snapshots) || \array_is_list($snapshots)) {
                self::failResultInvalid();
            }

            $out['snapshots'] = $snapshots;
        }

        /** @var array{
         *   fixture_repo_root_abs:string,
         *   fingerprint:string,
         *   bucket_digests:array<string,string>,
         *   snapshots?: array{
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
     * @throws DeterministicException
     */
    private static function failResultInvalid(): never
    {
        throw new DeterministicException(
            ErrorCodes::CORETSIA_FINGERPRINT_RESULT_INVALID,
            self::MSG_RESULT_INVALID,
        );
    }
}
