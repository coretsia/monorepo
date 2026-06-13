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

final class FingerprintExplainerDeterminismContractTest extends TestCase
{
    public function testExplainOutputIsStableOnRepeatedRuns(): void
    {
        $explainer = new FingerprintExplainer();
        $input = self::fingerprintInput();

        self::assertSame(
            $explainer->explain($input),
            $explainer->explain($input),
        );
    }

    public function testMapInsertionOrderDoesNotChangeExplainOutput(): void
    {
        $explainer = new FingerprintExplainer();

        self::assertSame(
            $explainer->explain(self::fingerprintInput()),
            $explainer->explain(self::fingerprintInputWithDifferentMapInsertionOrder()),
        );
    }

    public function testExplainEntriesAreDeterministicallySorted(): void
    {
        $explain = new FingerprintExplainer()->explain(self::fingerprintInput());

        $entryKeys = \array_map(
            static fn (array $entry): string => \implode(
                "\x1F",
                [
                    (string)($entry['kind'] ?? ''),
                    (string)($entry['bucket'] ?? ''),
                    (string)($entry['sourceId'] ?? ''),
                    (string)($entry['path'] ?? ''),
                    (string)($entry['keyPath'] ?? ''),
                    (string)($entry['sourceType'] ?? ''),
                    (string)($entry['validation'] ?? ''),
                ],
            ),
            $explain['entries'],
        );

        $sorted = $entryKeys;
        \sort($sorted, \SORT_STRING);

        self::assertSame($sorted, $entryKeys);
    }

    public function testDiffOutputIsDeterministic(): void
    {
        $explainer = new FingerprintExplainer();

        $expected = self::fingerprintInput();
        $actual = self::fingerprintInput();

        $actual['compiledConfig']['valueFingerprints']['kernel.safe']['hash'] = \str_repeat('f', 64);

        self::assertSame(
            $explainer->diff($expected, $actual),
            $explainer->diff($expected, $actual),
        );

        $diff = $explainer->diff($expected, $actual);

        self::assertSame(1, $diff['schemaVersion']);
        self::assertGreaterThanOrEqual(1, $diff['summary']['changedCount']);
    }

    public function testExplainDoesNotLeakUnsafeRawInputEvenWhenPresentInInputFixture(): void
    {
        $explain = new FingerprintExplainer()->explain(self::fingerprintInput());
        $encoded = \json_encode($explain, \JSON_THROW_ON_ERROR);

        foreach (
            [
                'raw-config-secret-value',
                'raw-env-secret-value',
                '/private/coretsia/config.php',
                'C:\\private\\coretsia\\.env',
                'php warning text',
                'stack trace text',
                'previous throwable message',
            ] as $forbiddenNeedle
        ) {
            self::assertStringNotContainsString($forbiddenNeedle, $encoded);
        }
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
                ],
            ],
            'compiledConfig' => [
                'valueFingerprints' => [
                    'custom.feature.enabled' => [
                        'hash' => \str_repeat('a', 64),
                        'len' => 4,
                        'type' => 'bool',
                        'raw' => 'raw-config-secret-value',
                    ],
                    'kernel.safe' => [
                        'hash' => \str_repeat('b', 64),
                        'len' => 12,
                        'type' => 'string',
                    ],
                ],
                'sources' => [
                    [
                        'kind' => 'skeleton_config',
                        'keyPath' => 'kernel.safe',
                        'path' => 'config/kernel.php',
                        'root' => 'kernel',
                        'sourceId' => 'skeleton/config/kernel',
                        'type' => 'skeleton_config',
                        'meta' => [
                            'hash' => \str_repeat('c', 64),
                            'length' => 128,
                        ],
                    ],
                    [
                        'kind' => 'skeleton_config',
                        'keyPath' => 'custom.feature.enabled',
                        'path' => '/private/coretsia/config.php',
                        'root' => 'custom',
                        'sourceId' => 'skeleton/config/custom',
                        'type' => 'skeleton_config',
                        'meta' => [
                            'rawValue' => 'raw-config-secret-value',
                        ],
                    ],
                ],
                'validationSubjects' => [
                    'validated' => [
                        [
                            'ownership' => 'ruleset_owned',
                            'root' => 'kernel',
                            'validation' => 'validated',
                        ],
                    ],
                    'unvalidated' => [
                        [
                            'ownership' => 'user_owned',
                            'root' => 'custom',
                            'validation' => 'unvalidated',
                        ],
                    ],
                ],
            ],
            'sourceCandidates' => [
                'package_config' => [
                    [
                        'sourceId' => 'package-default/kernel',
                        'path' => 'config/kernel.php',
                        'exists' => 'true',
                        'hash' => \str_repeat('d', 64),
                        'len' => 256,
                    ],
                    [
                        'sourceId' => 'package-default/missing',
                        'path' => 'config/missing.php',
                        'exists' => 'false',
                    ],
                ],
            ],
            'dotenvCandidates' => [
                [
                    'sourceId' => 'dotenv/.env',
                    'path' => '.env',
                    'exists' => 'true',
                    'hash' => \str_repeat('e', 64),
                    'len' => 64,
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
                            'path' => 'C:\\private\\coretsia\\.env',
                            'root' => 'kernel',
                            'sourceId' => 'env/KERNEL_SAFE',
                            'type' => 'env',
                            'rawValue' => 'raw-env-secret-value',
                        ],
                    ],
                ],
            ],
            'unsafeDiagnostics' => [
                'phpWarning' => 'php warning text',
                'stackTrace' => 'stack trace text',
                'previous' => 'previous throwable message',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function fingerprintInputWithDifferentMapInsertionOrder(): array
    {
        return [
            'unsafeDiagnostics' => [
                'previous' => 'previous throwable message',
                'stackTrace' => 'stack trace text',
                'phpWarning' => 'php warning text',
            ],
            'envOverlay' => [
                'sources' => [
                    'KERNEL_SAFE' => [
                        'source' => [
                            'type' => 'env',
                            'sourceId' => 'env/KERNEL_SAFE',
                            'root' => 'kernel',
                            'path' => 'C:\\private\\coretsia\\.env',
                            'keyPath' => 'kernel.safe',
                            'rawValue' => 'raw-env-secret-value',
                        ],
                        'hasSource' => true,
                    ],
                ],
                'mappings' => [
                    [
                        'type' => 'string',
                        'sourceId' => 'env/KERNEL_SAFE',
                        'root' => 'kernel',
                        'rawValue' => 'raw-env-secret-value',
                        'path' => 'kernel.safe',
                        'kind' => 'env_overlay',
                        'env' => 'KERNEL_SAFE',
                    ],
                ],
            ],
            'dotenvCandidates' => [
                [
                    'len' => 64,
                    'hash' => \str_repeat('e', 64),
                    'exists' => 'true',
                    'path' => '.env',
                    'sourceId' => 'dotenv/.env',
                ],
            ],
            'sourceCandidates' => [
                'package_config' => [
                    [
                        'len' => 256,
                        'hash' => \str_repeat('d', 64),
                        'exists' => 'true',
                        'path' => 'config/kernel.php',
                        'sourceId' => 'package-default/kernel',
                    ],
                    [
                        'exists' => 'false',
                        'path' => 'config/missing.php',
                        'sourceId' => 'package-default/missing',
                    ],
                ],
            ],
            'compiledConfig' => [
                'validationSubjects' => [
                    'unvalidated' => [
                        [
                            'validation' => 'unvalidated',
                            'root' => 'custom',
                            'ownership' => 'user_owned',
                        ],
                    ],
                    'validated' => [
                        [
                            'validation' => 'validated',
                            'root' => 'kernel',
                            'ownership' => 'ruleset_owned',
                        ],
                    ],
                ],
                'sources' => [
                    [
                        'type' => 'skeleton_config',
                        'sourceId' => 'skeleton/config/kernel',
                        'root' => 'kernel',
                        'path' => 'config/kernel.php',
                        'meta' => [
                            'length' => 128,
                            'hash' => \str_repeat('c', 64),
                        ],
                        'keyPath' => 'kernel.safe',
                        'kind' => 'skeleton_config',
                    ],
                    [
                        'type' => 'skeleton_config',
                        'sourceId' => 'skeleton/config/custom',
                        'root' => 'custom',
                        'path' => '/private/coretsia/config.php',
                        'meta' => [
                            'rawValue' => 'raw-config-secret-value',
                        ],
                        'keyPath' => 'custom.feature.enabled',
                        'kind' => 'skeleton_config',
                    ],
                ],
                'valueFingerprints' => [
                    'kernel.safe' => [
                        'type' => 'string',
                        'len' => 12,
                        'hash' => \str_repeat('b', 64),
                    ],
                    'custom.feature.enabled' => [
                        'type' => 'bool',
                        'raw' => 'raw-config-secret-value',
                        'len' => 4,
                        'hash' => \str_repeat('a', 64),
                    ],
                ],
            ],
            'fingerprintPolicy' => [
                'skeletonIgnorePrefixes' => [
                    'var/cache',
                    'var/maintenance',
                ],
            ],
            'schemaVersion' => 1,
        ];
    }
}
