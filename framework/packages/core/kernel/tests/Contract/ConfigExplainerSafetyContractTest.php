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

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use PHPUnit\Framework\TestCase;

final class ConfigExplainerSafetyContractTest extends TestCase
{
    public function testExplainDoesNotLeakRawConfigValuesOrUnsafeMetadata(): void
    {
        $config = [
            'kernel' => [
                'boot' => [
                    'default_env' => 'super-secret-password',
                    'default_preset' => 'mysql://user:pass@example.test/database',
                ],
                'runtime' => [
                    'http_driver' => 'http.classic',
                ],
                'modules' => [
                    'discovery' => [
                        'allowed_sources' => [
                            'AuthorizationBearerToken',
                        ],
                    ],
                ],
            ],
        ];

        $sources = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'kernel',
                sourceId: 'core/kernel/config/defaults/kernel',
                path: 'framework/packages/core/kernel/config/kernel.php',
                keyPath: 'kernel',
                directive: null,
                precedence: 10,
                redacted: false,
                meta: [
                    'kind' => 'package_default',
                    'packageId' => 'core/kernel',
                    'sourceOrder' => 0,

                    // Deliberately unsafe metadata: ConfigExplainer must drop it.
                    'hash' => 'not-a-valid-hash',
                    'length' => -1,
                ],
            ),
            new ConfigValueSource(
                type: ConfigSourceType::Env,
                root: 'kernel',
                sourceId: 'env-overlay/ruleset/KERNEL_BOOT_DEFAULT_ENV',
                path: null,
                keyPath: 'kernel.boot.default_env',
                directive: null,
                precedence: 500,
                redacted: true,
                meta: [
                    'envName' => 'KERNEL_BOOT_DEFAULT_ENV',
                    'envSourceId' => null,
                    'envSourceType' => null,
                    'kind' => 'env_overlay',
                    'mappingKind' => 'ruleset',
                    'valueType' => 'non-empty-string',

                    // Safe metadata intentionally retained by ConfigExplainer.
                    'hash' => \str_repeat('a', 64),
                    'hashAlgorithm' => 'sha256',
                    'length' => 21,
                ],
            ),
        ];

        $explain = new ConfigExplainer()->explain(
            config: $config,
            sources: $sources,
            validationSubjects: [
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'kernel',
                        'validation' => 'validated',
                    ],
                ],
                'unvalidated' => [],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [
                [
                    'env' => 'KERNEL_BOOT_DEFAULT_ENV',
                    'kind' => 'ruleset',
                    'path' => 'kernel.boot.default_env',
                    'root' => 'kernel',
                    'sourceId' => 'env-overlay/ruleset/KERNEL_BOOT_DEFAULT_ENV',
                    'type' => 'non-empty-string',
                ],
                [
                    'env' => 'KERNEL_RUNTIME_HTTP_DRIVER',
                    'kind' => 'ruleset',
                    'path' => 'kernel.runtime.http_driver',
                    'root' => 'kernel',
                    'sourceId' => '/absolute/path/must/be-dropped',
                    'type' => 'non-empty-string-no-ws',
                ],
            ],
            owners: [
                [
                    'root' => 'kernel',
                    'sourceId' => 'core/kernel/config/defaults/kernel',
                    'path' => 'framework/packages/core/kernel/config/kernel.php',
                    'packageId' => 'core/kernel',
                    'moduleId' => 'core.kernel',
                    'kind' => 'package_default',
                    'type' => 'package_default',
                ],
                [
                    'root' => 'kernel',
                    'sourceId' => '/absolute/owner/source',
                    'path' => '/var/www/secret/config.php',
                    'packageId' => 'core/kernel',
                    'moduleId' => 'core.kernel',
                    'kind' => 'package_default',
                    'type' => 'package_default',
                ],
            ],
        );

        $encoded = \json_encode($explain, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('super-secret-password', $encoded);
        self::assertStringNotContainsString('mysql://', $encoded);
        self::assertStringNotContainsString('user:pass', $encoded);
        self::assertStringNotContainsString('example.test', $encoded);
        self::assertStringNotContainsString('AuthorizationBearerToken', $encoded);
        self::assertStringNotContainsString('/var/www/secret', $encoded);
        self::assertStringNotContainsString('/absolute/path', $encoded);
        self::assertStringNotContainsString('/absolute/owner/source', $encoded);
        self::assertStringNotContainsString('not-a-valid-hash', $encoded);

        self::assertStringContainsString('KERNEL_BOOT_DEFAULT_ENV', $encoded);
        self::assertStringContainsString(\str_repeat('a', 64), $encoded);
        self::assertStringContainsString('sha256', $encoded);
        self::assertStringContainsString('"length":21', $encoded);

        $defaultEnvPath = self::pathRow(
            $explain,
            'kernel.boot.default_env',
        );

        self::assertSame('env', $defaultEnvPath['sourceType']);
        self::assertTrue($defaultEnvPath['redacted']);
        self::assertSame(
            'env-overlay/ruleset/KERNEL_BOOT_DEFAULT_ENV',
            $defaultEnvPath['sourceId'],
        );
        self::assertSame('scalar:string', $defaultEnvPath['valueShape']);

        self::assertSame(
            [
                'kernel.boot.default_env',
            ],
            $explain['envOverlay']['effectivePaths'],
        );

        foreach ($explain['sourceRanks'] as $rank) {
            self::assertArrayHasKey('meta', $rank);

            self::assertArrayNotHasKey('password', $rank['meta']);
            self::assertArrayNotHasKey('dsn', $rank['meta']);
            self::assertArrayNotHasKey('absolutePath', $rank['meta']);
            self::assertArrayNotHasKey('rawEnvValue', $rank['meta']);
            self::assertArrayNotHasKey('token', $rank['meta']);
        }
    }

    public function testExplainIsDeterministicForEquivalentInputs(): void
    {
        $config = [
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ];

        $sources = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'kernel',
                sourceId: 'core/kernel/config/defaults/kernel',
                path: 'framework/packages/core/kernel/config/kernel.php',
                keyPath: 'kernel',
                directive: null,
                precedence: 10,
                redacted: false,
                meta: [
                    'kind' => 'package_default',
                    'packageId' => 'core/kernel',
                    'sourceOrder' => 0,
                ],
            ),
            new ConfigValueSource(
                type: ConfigSourceType::AppConfig,
                root: 'kernel',
                sourceId: 'skeleton-config/app_environment/split_root/kernel',
                path: 'skeleton/apps/main/config/environments/prod/kernel.php',
                keyPath: 'kernel.boot.default_env',
                directive: 'replace',
                precedence: 401,
                redacted: false,
                meta: [
                    'appEnv' => 'prod',
                    'appTarget' => 'main',
                    'kind' => 'split_root_subtree',
                    'layer' => 'app_environment',
                    'sourceOrder' => 1,
                ],
            ),
        ];

        $explainer = new ConfigExplainer();

        $first = $explainer->explain(
            config: $config,
            sources: $sources,
            validationSubjects: [
                'validated' => [
                    [
                        'ownership' => 'ruleset_owned',
                        'root' => 'kernel',
                        'validation' => 'validated',
                    ],
                ],
                'unvalidated' => [],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [],
        );

        $second = $explainer->explain(
            config: $config,
            sources: \array_reverse($sources),
            validationSubjects: [
                'unvalidated' => [],
                'validated' => [
                    [
                        'validation' => 'validated',
                        'root' => 'kernel',
                        'ownership' => 'ruleset_owned',
                    ],
                ],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [],
        );

        self::assertSame($first, $second);
        self::assertSame(
            self::sorted(\array_column($first['paths'], 'path')),
            \array_column($first['paths'], 'path'),
        );

        $defaultEnvPath = self::pathRow(
            $first,
            'kernel.boot.default_env',
        );

        self::assertSame(
            'replace',
            $defaultEnvPath['directive'],
        );
    }

    /**
     * @param array<string,mixed> $explain
     *
     * @return array<string,mixed>
     */
    private static function pathRow(array $explain, string $path): array
    {
        foreach ($explain['paths'] as $row) {
            if ($row['path'] === $path) {
                return $row;
            }
        }

        self::fail('Missing explain path row: ' . $path);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        \sort($values, \SORT_STRING);

        return $values;
    }
}
