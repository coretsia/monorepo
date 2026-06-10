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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpikeConfigExplainTraceIsSafeContractTest extends TestCase
{
    public function testExplainTraceDoesNotLeakRawConfigValuesOrUnsafeMetadata(): void
    {
        $config = [
            'http' => [
                'database' => [
                    'password' => 'super-secret-password',
                    'dsn' => 'mysql://user:pass@example.test/database',
                ],
                'middleware' => [
                    'system_pre' => [
                        'AuthorizationBearerToken',
                    ],
                ],
            ],
        ];

        $sources = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'http',
                sourceId: 'spike/defaults/http',
                path: 'spike/config-merge/scenarios.php',
                keyPath: 'http',
                directive: null,
                precedence: 10,
                redacted: false,
                meta: [
                    'kind' => 'package_default',
                    'sourceOrder' => 0,
                    'envName' => 'HTTP_DATABASE_DSN',
                    'hash' => 'not-a-valid-hash',
                    'length' => -1,
                ],
            ),
            new ConfigValueSource(
                type: ConfigSourceType::Env,
                root: 'http',
                sourceId: 'env/http/database/password',
                path: null,
                keyPath: 'http.database.password',
                directive: null,
                precedence: 500,
                redacted: true,
                meta: [
                    'kind' => 'env_overlay',
                    'sourceOrder' => 1,
                    'envName' => 'HTTP_DATABASE_PASSWORD',
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
                'validated' => [],
                'unvalidated' => [
                    [
                        'ownership' => 'user_owned',
                        'root' => 'http',
                        'validation' => 'unvalidated',
                    ],
                ],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [
                [
                    'env' => 'HTTP_DATABASE_PASSWORD',
                    'kind' => 'ruleset',
                    'path' => 'http.database.password',
                    'root' => 'http',
                    'sourceId' => 'env/http/database/password',
                    'type' => 'env',
                ],
                [
                    'env' => 'HTTP_DATABASE_DSN',
                    'kind' => 'ruleset',
                    'path' => 'http.database.dsn',
                    'root' => 'http',
                    'sourceId' => '/absolute/path/must/be/dropped',
                    'type' => 'env',
                ],
            ],
            owners: [
                [
                    'root' => 'http',
                    'sourceId' => 'spike/defaults/http',
                    'path' => 'spike/config-merge/scenarios.php',
                    'packageId' => 'core/kernel',
                    'moduleId' => 'core.kernel',
                    'kind' => 'package_default',
                    'type' => 'package_default',
                ],
                [
                    'root' => 'http',
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

        self::assertStringContainsString('HTTP_DATABASE_PASSWORD', $encoded);
        self::assertStringContainsString(\str_repeat('a', 64), $encoded);
        self::assertStringContainsString('sha256', $encoded);
        self::assertStringContainsString('"length":21', $encoded);

        $passwordPath = self::pathRow($explain, 'http.database.password');

        self::assertSame('env', $passwordPath['sourceType']);
        self::assertTrue($passwordPath['redacted']);
        self::assertSame('env/http/database/password', $passwordPath['sourceId']);
        self::assertSame('scalar:string', $passwordPath['valueShape']);

        self::assertSame(
            [
                'http.database.password',
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

    public function testExplainTraceIsDeterministicForSameInputs(): void
    {
        $config = [
            'http' => [
                'middleware' => [
                    'system_pre' => [
                        'CorrelationId',
                    ],
                ],
            ],
        ];

        $sources = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'http',
                sourceId: 'spike/defaults/http',
                path: 'spike/config-merge/scenarios.php',
                keyPath: 'http',
                directive: null,
                precedence: 10,
                redacted: false,
                meta: [
                    'kind' => 'package_default',
                    'sourceOrder' => 0,
                ],
            ),
            new ConfigValueSource(
                type: ConfigSourceType::AppConfig,
                root: 'http',
                sourceId: 'spike/app/http_middleware_system_pre',
                path: 'spike/config-merge/scenarios.php',
                keyPath: 'http.middleware.system_pre',
                directive: '@append',
                precedence: 400,
                redacted: false,
                meta: [
                    'kind' => 'spike_scenario',
                    'sourceOrder' => 1,
                ],
            ),
        ];

        $explainer = new ConfigExplainer();

        $first = $explainer->explain(
            config: $config,
            sources: $sources,
            validationSubjects: [
                'validated' => [],
                'unvalidated' => [
                    [
                        'ownership' => 'user_owned',
                        'root' => 'http',
                        'validation' => 'unvalidated',
                    ],
                ],
            ],
            validationResult: ConfigValidationResult::success(),
            envOverlayMappings: [],
            owners: [],
        );

        $second = $explainer->explain(
            config: $config,
            sources: \array_reverse($sources),
            validationSubjects: [
                'unvalidated' => [
                    [
                        'validation' => 'unvalidated',
                        'root' => 'http',
                        'ownership' => 'user_owned',
                    ],
                ],
                'validated' => [],
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
    }

    #[DataProvider('forbiddenRawMetaKeysProvider')]
    public function testConfigValueSourceRejectsForbiddenRawMetaKeys(string $forbiddenKey): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConfigValueSource(
            type: ConfigSourceType::Env,
            root: 'http',
            sourceId: 'env/http/password',
            path: null,
            keyPath: 'http.database.password',
            directive: null,
            precedence: 500,
            redacted: true,
            meta: [
                $forbiddenKey => 'super-secret-password',
            ],
        );
    }

    public static function forbiddenRawMetaKeysProvider(): iterable
    {
        yield 'rawEnvValue' => ['rawEnvValue'];
        yield 'token' => ['token'];
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
