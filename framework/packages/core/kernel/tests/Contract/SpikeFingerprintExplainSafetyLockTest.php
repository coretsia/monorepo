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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintExplainer;
use PHPUnit\Framework\TestCase;

final class SpikeFingerprintExplainSafetyLockTest extends TestCase
{
    public function testExplainOutputKeepsOnlySafePhaseZeroMetadata(): void
    {
        $explain = new FingerprintExplainer()->explain(self::fingerprintInput());

        self::assertSame(1, $explain['schemaVersion']);
        self::assertNotSame([], $explain['entries']);

        self::assertTrue(
            self::containsEntry($explain['entries'], [
                'kind' => 'source_candidate',
                'bucket' => 'repo_min',
                'sourceId' => 'phase0/repo_min/skeleton',
                'path' => 'config/app.php',
                'hash' => \str_repeat('a', 64),
                'len' => 160,
            ]),
        );

        self::assertTrue(
            self::containsEntry($explain['entries'], [
                'kind' => 'validation_subject',
                'keyPath' => 'custom',
                'validation' => 'unvalidated',
            ]),
        );

        self::assertTrue(
            self::containsEntry($explain['entries'], [
                'kind' => 'fingerprint_policy',
                'path' => 'var/cache',
                'sourceType' => 'skeleton_ignore_prefix',
            ]),
        );
    }

    public function testExplainOutputDoesNotLeakRawValuesSecretsOrAbsolutePaths(): void
    {
        $explain = new FingerprintExplainer()->explain(self::fingerprintInput());
        $encoded = \json_encode($explain, \JSON_THROW_ON_ERROR);

        foreach (
            [
                'raw-config-secret-value',
                'raw-env-secret-value',
                '/private/coretsia/repo_min/skeleton/config/app.php',
                '/tmp/coretsia/repo_min',
                'C:\\private\\coretsia\\repo_min',
                'php warning',
                'stack trace',
            ] as $forbiddenNeedle
        ) {
            self::assertStringNotContainsString($forbiddenNeedle, $encoded);
        }
    }

    /**
     * @param list<array<string, bool|int|string>> $entries
     * @param array<string, bool|int|string> $expected
     */
    private static function containsEntry(array $entries, array $expected): bool
    {
        foreach ($entries as $entry) {
            foreach ($expected as $key => $value) {
                if (($entry[$key] ?? null) !== $value) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    private static function fingerprintInput(): array
    {
        return [
            'schemaVersion' => 1,
            'fingerprintPolicy' => [
                'skeletonIgnorePrefixes' => [
                    'var/cache',
                    'var/maintenance',
                    '/private/coretsia/repo_min',
                    'C:\\private\\coretsia\\repo_min',
                ],
            ],
            'compiledConfig' => [
                'valueFingerprints' => [
                    'custom.feature.value' => [
                        'hash' => \str_repeat('b', 64),
                        'len' => 23,
                        'type' => 'string',
                        'raw' => 'raw-config-secret-value',
                    ],
                ],
                'validationSubjects' => [
                    'unvalidated' => [
                        [
                            'ownership' => 'user_owned',
                            'root' => 'custom',
                            'validation' => 'unvalidated',
                        ],
                    ],
                    'validated' => [],
                ],
            ],
            'sourceCandidates' => [
                'repo_min' => [
                    [
                        'sourceId' => 'phase0/repo_min/skeleton',
                        'path' => 'config/app.php',
                        'exists' => 'true',
                        'hash' => \str_repeat('a', 64),
                        'len' => 160,
                        'rawValue' => 'raw-config-secret-value',
                    ],
                    [
                        'sourceId' => 'phase0/repo_min/private',
                        'path' => '/private/coretsia/repo_min/skeleton/config/app.php',
                        'exists' => 'true',
                        'hash' => \str_repeat('c', 64),
                        'len' => 200,
                    ],
                ],
            ],
            'envOverlay' => [
                'mappings' => [
                    [
                        'env' => 'APP_SECRET',
                        'kind' => 'env_overlay',
                        'path' => 'custom.feature.value',
                        'root' => 'custom',
                        'sourceId' => 'env/APP_SECRET',
                        'type' => 'string',
                        'rawValue' => 'raw-env-secret-value',
                    ],
                ],
                'sources' => [
                    'APP_SECRET' => [
                        'hasSource' => true,
                        'source' => [
                            'keyPath' => 'custom.feature.value',
                            'path' => 'C:\\private\\coretsia\\repo_min\\.env',
                            'root' => 'custom',
                            'sourceId' => 'env/APP_SECRET',
                            'type' => 'env',
                            'rawValue' => 'raw-env-secret-value',
                        ],
                    ],
                ],
            ],
            'unsafeDiagnostics' => [
                'message' => 'php warning with stack trace',
            ],
        ];
    }
}
