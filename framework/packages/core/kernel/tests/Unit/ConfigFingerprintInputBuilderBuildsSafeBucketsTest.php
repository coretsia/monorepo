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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Env\EnvValue;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;

final class ConfigFingerprintInputBuilderBuildsSafeBucketsTest extends TestCase
{
    public function testIncludesDeclaredSourceCandidatesAsLogicalIdsAndRepresentsMissingAsFalse(): void
    {
        $input = self::buildInput();

        self::assertSame(
            'package-default/kernel',
            $input['sourceCandidates']['package_config'][0]['sourceId'],
        );

        self::assertSame(
            'false',
            $input['sourceCandidates']['package_config'][0]['exists'],
        );

        self::assertSame(
            'config/kernel.php',
            $input['sourceCandidates']['package_config'][0]['path'],
        );

        self::assertSame(
            1,
            $input['observabilityMetadata']['sourceCandidateCounts']['package_config'],
        );

        self::assertSame(
            1,
            $input['observabilityMetadata']['missingCandidateCounts']['package_config'],
        );
    }

    public function testIncludesUserOwnedRootsAsUnvalidatedWhenNoRulesExist(): void
    {
        $input = self::buildInput();

        self::assertSame(
            [
                [
                    'ownership' => 'user_owned',
                    'root' => 'custom',
                    'validation' => 'unvalidated',
                ],
            ],
            $input['compiledConfig']['validationSubjects']['unvalidated'],
        );

        self::assertContains(
            'custom',
            $input['compiledConfig']['roots'],
        );

        self::assertArrayHasKey(
            'custom.enabled',
            $input['compiledConfig']['valueFingerprints'],
        );
    }

    public function testIncludesModulePlanIdentity(): void
    {
        $input = self::buildInput();

        self::assertSame(1, $input['modulePlan']['schemaVersion']);
        self::assertSame(0, $input['modulePlan']['moduleCount']);
        self::assertSame(0, $input['modulePlan']['enabledModuleCount']);
        self::assertSame(0, $input['modulePlan']['disabledModuleCount']);
        self::assertSame(0, $input['modulePlan']['warningCount']);

        self::assertIsString($input['modulePlan']['hash']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $input['modulePlan']['hash']);
    }

    public function testDoesNotIncludeRawConfigOrEnvValues(): void
    {
        $input = self::buildInput();
        $encoded = \json_encode($input, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('raw-config-secret-value', $encoded);
        self::assertStringNotContainsString('raw-custom-secret-value', $encoded);
        self::assertStringNotContainsString('raw-env-secret-value', $encoded);
        self::assertStringNotContainsString('/private/absolute/path', $encoded);
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildInput(): array
    {
        $builder = new ConfigFingerprintInputBuilder();

        return $builder->build(
            bootstrapConfig: self::bootstrapConfig(),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            compiledConfig: self::compiledConfig(),
            packageDefaultSources: [
                [
                    'root' => 'kernel',
                    'packageId' => 'core.kernel',
                    'moduleId' => 'core.kernel',
                    'path' => 'config/kernel.php',
                    'filesystemPath' => self::missingPath('kernel-default.php'),
                    'sourceId' => 'package-default/kernel',
                    'precedence' => 10,
                ],
            ],
            packageRuleSources: [],
            splitRoots: [
                'custom',
                'kernel',
            ],
            explicitRuleSources: [],
            modePresetSourceCandidates: [],
        );
    }

    private static function bootstrapConfig(): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: 'micro',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Api,
            skeletonRoot: self::skeletonRoot(),
        );
    }

    private static function modulePlan(): ModulePlan
    {
        return new ModulePlan(
            app: 'api',
            preset: 'micro',
            enabled: [],
            disabled: [],
            optionalMissing: [],
            topologicalOrder: [],
            modules: [],
            warnings: [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function kernelConfig(): array
    {
        return [
            'env' => [
                'dotenv' => [
                    'files' => [
                        '.env',
                        '.env.local',
                        '.env.<env>',
                    ],
                ],
            ],
            'fingerprint' => [
                'skeleton_ignore_prefixes' => [
                    'var/cache',
                    'var/maintenance',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function compiledConfig(): array
    {
        return [
            'config' => [
                'custom' => [
                    'enabled' => true,
                    'secret' => 'raw-custom-secret-value',
                ],
                'kernel' => [
                    'safe' => 'raw-config-secret-value',
                ],
            ],
            'sources' => [
                new ConfigValueSource(
                    type: ConfigSourceType::PackageDefault,
                    root: 'kernel',
                    sourceId: 'package-default/kernel',
                    path: 'framework/packages/core/kernel/config/kernel.php',
                    keyPath: 'kernel.safe',
                    directive: null,
                    precedence: 10,
                    redacted: false,
                    meta: [
                        'hash' => \str_repeat('a', 64),
                        'length' => 123,
                    ],
                ),
            ],
            'owners' => [],
            'envOverlayMappings' => [
                [
                    'env' => 'KERNEL_SAFE',
                    'kind' => 'env_overlay',
                    'path' => 'kernel.safe',
                    'root' => 'kernel',
                    'sourceId' => 'env/KERNEL_SAFE',
                    'type' => 'string',
                ],
            ],
            'configSourceFiles' => [
                [
                    'exists' => false,
                    'kind' => 'shared',
                    'layer' => 'skeleton',
                    'path' => 'config/kernel.php',
                    'readable' => false,
                    'root' => 'kernel',
                    'sourceId' => 'skeleton/config/kernel',
                ],
            ],
            'validation' => ConfigValidationResult::success(),
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
        ];
    }

    private static function envRepository(): EnvRepositoryInterface
    {
        return new class() implements EnvRepositoryInterface {
            public function has(string $name): bool
            {
                return $name === 'KERNEL_SAFE';
            }

            public function get(string $name): EnvValue
            {
                throw new \LogicException('Env values must not be read by fingerprint input builder tests.');
            }

            public function all(): array
            {
                return [
                    'KERNEL_SAFE' => 'raw-env-secret-value',
                ];
            }

            public function sourceOf(string $name): ?ConfigValueSource
            {
                if ($name !== 'KERNEL_SAFE') {
                    return null;
                }

                return new ConfigValueSource(
                    type: ConfigSourceType::Env,
                    root: 'kernel',
                    sourceId: 'env/KERNEL_SAFE',
                    path: null,
                    keyPath: 'kernel.safe',
                    directive: null,
                    precedence: 500,
                    redacted: true,
                    meta: [
                        'source' => 'dotenv',
                    ],
                );
            }
        };
    }

    private static function skeletonRoot(): string
    {
        $path = \sys_get_temp_dir() . '/coretsia-fingerprint-builder-test';

        if (!\is_dir($path)) {
            \mkdir($path, 0777, true);
        }

        return $path;
    }

    private static function missingPath(string $name): string
    {
        return self::skeletonRoot() . '/missing/' . $name;
    }
}
