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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Boot\ArrayEnvRepository;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnvironmentOverlayProjectionTest extends TestCase
{
    public function testConfigPathProjectionUsesUppercaseAsciiAndUnderscores(): void
    {
        self::assertSame(
            'KERNEL_BOOT_DEFAULT_ENV',
            EnvironmentOverlayLoader::envNameForConfigPath('kernel.boot.default_env'),
        );

        self::assertSame(
            'KERNEL_BOOT_DEFAULT_ENV',
            EnvironmentOverlayLoader::envNameForConfigPath('kernel.boot.default-env'),
        );

        self::assertSame(
            'CUSTOM_FEATURE_FLAG',
            EnvironmentOverlayLoader::envNameForConfigPath('custom.feature-flag'),
        );
    }

    public function testUnknownEnvVarsDoNotCreateConfigKeys(): void
    {
        $result = new EnvironmentOverlayLoader()->load(
            env: new ArrayEnvRepository([
                'UNKNOWN_ENV_VAR' => 'must-not-create-config-key',
            ]),
            rulesets: [
                self::kernelRuleset(),
            ],
        );

        self::assertSame([], $result['config']);
        self::assertSame([], $result['sources']);
        self::assertNotContains('UNKNOWN_ENV_VAR', \array_column($result['mappings'], 'env'));
    }

    public function testRulesetEnvOverlayMapsKernelBootDefaultEnv(): void
    {
        $result = new EnvironmentOverlayLoader()->load(
            env: new ArrayEnvRepository([
                'KERNEL_BOOT_DEFAULT_ENV' => 'prod',
            ]),
            rulesets: [
                self::kernelRuleset(),
            ],
        );

        self::assertSame(
            [
                'kernel' => [
                    'boot' => [
                        'default_env' => 'prod',
                    ],
                ],
            ],
            $result['config'],
        );

        self::assertSame(
            [
                [
                    'env' => 'KERNEL_BOOT_DEFAULT_ENV',
                    'kind' => 'ruleset',
                    'path' => 'kernel.boot.default_env',
                    'root' => 'kernel',
                    'sourceId' => 'env-overlay/ruleset/KERNEL_BOOT_DEFAULT_ENV',
                    'type' => 'non-empty-string-no-ws',
                ],
            ],
            \array_values(
                \array_filter(
                    $result['mappings'],
                    static fn (array $mapping): bool => $mapping['path'] === 'kernel.boot.default_env',
                )
            ),
        );

        self::assertArrayHasKey('kernel.boot.default_env', $result['sources']);
        self::assertTrue($result['sources']['kernel.boot.default_env']->isRedacted());
    }

    #[DataProvider('acceptedBoolTokenProvider')]
    public function testBoolEnvCoercionAcceptsOnlyCanonicalTokens(string $rawValue, bool $expected): void
    {
        $result = new EnvironmentOverlayLoader()->load(
            env: new ArrayEnvRepository([
                'CUSTOM_FEATURE_ENABLED' => $rawValue,
            ]),
            rulesets: [],
            explicitMappings: [
                [
                    'path' => 'custom.feature.enabled',
                    'env' => 'CUSTOM_FEATURE_ENABLED',
                    'type' => 'bool',
                    'sourceId' => 'custom/env/feature_enabled',
                ],
            ],
        );

        self::assertSame(
            [
                'custom' => [
                    'feature' => [
                        'enabled' => $expected,
                    ],
                ],
            ],
            $result['config'],
        );
    }

    #[DataProvider('rejectedBoolTokenProvider')]
    public function testBoolEnvCoercionRejectsNonCanonicalTokensWithoutLeakingRawEnvValue(string $rawValue): void
    {
        try {
            new EnvironmentOverlayLoader()->load(
                env: new ArrayEnvRepository([
                    'CUSTOM_FEATURE_ENABLED' => $rawValue,
                ]),
                rulesets: [],
                explicitMappings: [
                    [
                        'path' => 'custom.feature.enabled',
                        'env' => 'CUSTOM_FEATURE_ENABLED',
                        'type' => 'bool',
                        'sourceId' => 'custom/env/feature_enabled',
                    ],
                ],
            );

            self::fail('Expected ConfigInvalidException was not thrown.');
        } catch (ConfigInvalidException $exception) {
            self::assertSame(ConfigInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ConfigInvalidException::REASON_SOURCE_INVALID, $exception->reason());
            if ($rawValue !== '') {
                self::assertStringNotContainsString($rawValue, $exception->getMessage());
            }
            self::assertStringNotContainsString('CUSTOM_FEATURE_ENABLED', $exception->getMessage());
            self::assertStringNotContainsString('custom.feature.enabled', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0:string,1:bool}>
     */
    public static function acceptedBoolTokenProvider(): iterable
    {
        yield 'true' => ['true', true];
        yield 'one' => ['1', true];
        yield 'false' => ['false', false];
        yield 'zero' => ['0', false];
    }

    /**
     * @return iterable<string, array{0:string}>
     */
    public static function rejectedBoolTokenProvider(): iterable
    {
        yield 'yes' => ['yes'];
        yield 'no' => ['no'];
        yield 'uppercase-true' => ['TRUE'];
        yield 'uppercase-false' => ['FALSE'];
        yield 'empty' => [''];
        yield 'secret-looking' => ['secret-token-value'];
    }

    private static function kernelRuleset(): ConfigRuleset
    {
        return ConfigRuleset::fromArray('kernel', [
            'configRoot' => 'kernel',
            'schemaVersion' => 1,
            'additionalKeys' => true,
            'type' => 'map',
            'keys' => [
                'boot' => [
                    'type' => 'map',
                    'required' => false,
                    'additionalKeys' => true,
                    'keys' => [
                        'default_env' => [
                            'type' => 'non-empty-string-no-ws',
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
