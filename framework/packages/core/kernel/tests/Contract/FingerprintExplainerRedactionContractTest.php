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

final class FingerprintExplainerRedactionContractTest extends TestCase
{
    public function testExplainOutputIncludesOnlySafeIdsKeyPathsRelativePathsHashLenAndValidationStatus(): void
    {
        $explain = new FingerprintExplainer()->explain(self::fingerprintInput());

        self::assertSame(1, $explain['schemaVersion']);
        self::assertNotSame([], $explain['entries']);

        self::assertTrue(
            self::containsEntry($explain['entries'], [
                'kind' => 'config_value',
                'keyPath' => 'kernel.safe',
                'sourceType' => 'string',
                'hash' => \str_repeat('a', 64),
                'len' => 32,
            ]),
        );

        self::assertTrue(
            self::containsEntry($explain['entries'], [
                'kind' => 'source_candidate',
                'bucket' => 'package_config',
                'sourceId' => 'package-default/kernel',
                'path' => 'framework/packages/core/kernel/config/kernel.php',
                'hash' => \str_repeat('b', 64),
                'len' => 100,
            ]),
        );

        self::assertTrue(
            self::containsEntry($explain['entries'], [
                'kind' => 'validation_subject',
                'keyPath' => 'custom',
                'validation' => 'unvalidated',
            ]),
        );
    }

    public function testExplainOutputDoesNotIncludeRawConfigValuesRawEnvValuesOrAbsolutePaths(): void
    {
        $explain = new FingerprintExplainer()->explain(self::fingerprintInput());
        $encoded = \json_encode($explain, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('raw-config-secret-value', $encoded);
        self::assertStringNotContainsString('raw-env-secret-value', $encoded);
        self::assertStringNotContainsString('/private/absolute/path', $encoded);
        self::assertStringNotContainsString('/tmp/coretsia-secret.php', $encoded);
        self::assertStringNotContainsString('C:\\private\\secret.php', $encoded);
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
     * @return array<string, mixed>
     */
    private static function fingerprintInput(): array
    {
        return [
            'schemaVersion' => 1,
            'fingerprintPolicy' => [
                'skeletonIgnorePrefixes' => [
                    'var/cache',
                    'var/maintenance',
                    '/private/absolute/path',
                ],
            ],
            'compiledConfig' => [
                'valueFingerprints' => [
                    'kernel.safe' => [
                        'hash' => \str_repeat('a', 64),
                        'len' => 32,
                        'type' => 'string',
                        'raw' => 'raw-config-secret-value',
                    ],
                ],
                'sources' => [
                    [
                        'kind' => 'package_default',
                        'keyPath' => 'kernel.safe',
                        'path' => '/tmp/coretsia-secret.php',
                        'root' => 'kernel',
                        'sourceId' => 'package-default/kernel',
                        'type' => 'package_default',
                        'meta' => [
                            'hash' => \str_repeat('c', 64),
                            'length' => 88,
                            'rawValue' => 'raw-config-secret-value',
                        ],
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
                    'validated' => [
                        [
                            'ownership' => 'ruleset_owned',
                            'root' => 'kernel',
                            'validation' => 'validated',
                        ],
                    ],
                ],
            ],
            'sourceCandidates' => [
                'package_config' => [
                    [
                        'sourceId' => 'package-default/kernel',
                        'path' => 'framework/packages/core/kernel/config/kernel.php',
                        'exists' => 'true',
                        'hash' => \str_repeat('b', 64),
                        'len' => 100,
                        'rawValue' => 'raw-config-secret-value',
                    ],
                    [
                        'sourceId' => 'package-default/private',
                        'path' => '/private/absolute/path',
                        'exists' => 'true',
                        'hash' => \str_repeat('d', 64),
                        'len' => 100,
                    ],
                ],
            ],
            'envOverlay' => [
                'mappings' => [
                    [
                        'env' => 'KERNEL_SAFE',
                        'kind' => 'env_overlay',
                        'path' => 'kernel.safe',
                        'root' => 'kernel',
                        'sourceId' => 'env/KERNEL_SAFE',
                        'type' => 'string',
                        'rawValue' => 'raw-env-secret-value',
                    ],
                ],
                'sources' => [
                    'KERNEL_SAFE' => [
                        'hasSource' => true,
                        'source' => [
                            'keyPath' => 'kernel.safe',
                            'path' => 'C:\\private\\secret.php',
                            'root' => 'kernel',
                            'sourceId' => 'env/KERNEL_SAFE',
                            'type' => 'env',
                            'rawValue' => 'raw-env-secret-value',
                        ],
                    ],
                ],
            ],
        ];
    }
}
