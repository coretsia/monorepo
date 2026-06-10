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

final class FingerprintInstalledManifestNormalizationTest extends TestCase
{
    public function testPackageSourceCandidatesAreSortedByLogicalSourceId(): void
    {
        $input = self::builder()->build(
            bootstrapConfig: self::bootstrapConfig(),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            compiledConfig: self::compiledConfig(),
            packageDefaultSources: [
                [
                    'root' => 'kernel',
                    'packageId' => 'vendor.zeta',
                    'moduleId' => 'vendor.zeta',
                    'path' => 'config/zeta.php',
                    'filesystemPath' => self::missingPath('zeta.php'),
                    'sourceId' => 'package-default/zeta',
                    'precedence' => 30,
                ],
                [
                    'root' => 'kernel',
                    'packageId' => 'vendor.alpha',
                    'moduleId' => 'vendor.alpha',
                    'path' => 'config/alpha.php',
                    'filesystemPath' => self::missingPath('alpha.php'),
                    'sourceId' => 'package-default/alpha',
                    'precedence' => 10,
                ],
            ],
            packageRuleSources: [],
        );

        self::assertSame(
            [
                'package-default/alpha',
                'package-default/zeta',
            ],
            \array_column($input['sourceCandidates']['package_config'], 'sourceId'),
        );
    }

    public function testMissingInstalledManifestCandidatesStayLogicalAndDoNotLeakFilesystemPaths(): void
    {
        $input = self::builder()->build(
            bootstrapConfig: self::bootstrapConfig(),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            compiledConfig: self::compiledConfig(),
            packageDefaultSources: [
                [
                    'root' => 'kernel',
                    'packageId' => 'vendor.alpha',
                    'moduleId' => 'vendor.alpha',
                    'path' => 'config/alpha.php',
                    'filesystemPath' => self::missingPath('alpha.php'),
                    'sourceId' => 'package-default/alpha',
                    'precedence' => 10,
                ],
            ],
            packageRuleSources: [],
        );

        $candidate = $input['sourceCandidates']['package_config'][0];

        self::assertSame('package-default/alpha', $candidate['sourceId']);
        self::assertSame('config/alpha.php', $candidate['path']);
        self::assertSame('false', $candidate['exists']);

        $encoded = \json_encode($input, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString(self::skeletonRoot(), $encoded);
        self::assertStringNotContainsString('missing/alpha.php', $encoded);
    }

    private static function builder(): ConfigFingerprintInputBuilder
    {
        return new ConfigFingerprintInputBuilder();
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
                    'files' => [],
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
                'kernel' => [
                    'safe' => true,
                ],
            ],
            'sources' => [],
            'owners' => [],
            'envOverlayMappings' => [],
            'configSourceFiles' => [],
            'validation' => ConfigValidationResult::success(),
            'validationSubjects' => [
                'unvalidated' => [],
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
                return false;
            }

            public function get(string $name): EnvValue
            {
                throw new \LogicException('Env values must not be read by installed manifest normalization tests.');
            }

            public function all(): array
            {
                return [];
            }

            public function sourceOf(string $name): ?ConfigValueSource
            {
                return null;
            }
        };
    }

    private static function skeletonRoot(): string
    {
        $path = \sys_get_temp_dir() . '/coretsia-installed-manifest-fingerprint-test';

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
