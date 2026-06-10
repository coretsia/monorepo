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

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Env\EnvValue;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintExplainer;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;

final class FingerprintPathSeparatorContractTest extends TestCase
{
    public function testFingerprintInputNormalizesCandidatePathsToForwardSlashes(): void
    {
        $input = self::buildInputWithBackslashPaths();

        self::assertSame(
            'config/kernel.php',
            $input['sourceCandidates']['package_config'][0]['path'],
        );

        self::assertSame(
            'apps/api/config/kernel.php',
            $input['sourceCandidates']['skeleton_config'][0]['path'],
        );

        $encoded = \json_encode($input, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('config\\kernel.php', $encoded);
        self::assertStringNotContainsString('apps\\api\\config\\kernel.php', $encoded);
    }

    public function testFingerprintExplainNormalizesRelativePathSeparatorsToForwardSlashes(): void
    {
        $explain = (new FingerprintExplainer())->explain(self::buildInputWithBackslashPaths());

        self::assertTrue(
            self::containsExplainPath($explain['entries'], 'config/kernel.php'),
        );

        self::assertTrue(
            self::containsExplainPath($explain['entries'], 'apps/api/config/kernel.php'),
        );

        foreach ($explain['entries'] as $entry) {
            if (!isset($entry['path'])) {
                continue;
            }

            self::assertIsString($entry['path']);
            self::assertStringNotContainsString('\\', $entry['path']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildInputWithBackslashPaths(): array
    {
        return new ConfigFingerprintInputBuilder()->build(
            bootstrapConfig: self::bootstrapConfig(),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            compiledConfig: [
                'config' => [
                    'kernel' => [
                        'safe' => true,
                    ],
                ],
                'sources' => [],
                'owners' => [],
                'envOverlayMappings' => [],
                'configSourceFiles' => [
                    [
                        'exists' => false,
                        'kind' => 'app',
                        'layer' => 'app',
                        'path' => 'apps\\api\\config\\kernel.php',
                        'readable' => false,
                        'root' => 'kernel',
                        'sourceId' => 'app/config/kernel',
                    ],
                ],
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
            ],
            packageDefaultSources: [
                [
                    'root' => 'kernel',
                    'packageId' => 'core.kernel',
                    'moduleId' => 'core.kernel',
                    'path' => 'config\\kernel.php',
                    'filesystemPath' => self::missingPath('kernel.php'),
                    'sourceId' => 'package-default/kernel',
                    'precedence' => 10,
                ],
            ],
            packageRuleSources: [],
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

    private static function envRepository(): EnvRepositoryInterface
    {
        return new class() implements EnvRepositoryInterface {
            public function has(string $name): bool
            {
                return false;
            }

            public function get(string $name): EnvValue
            {
                throw new \LogicException('Env values must not be read by path separator contract tests.');
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
        $path = \sys_get_temp_dir() . '/coretsia-fingerprint-path-separator-test';

        if (!\is_dir($path)) {
            \mkdir($path, 0777, true);
        }

        return $path;
    }

    private static function missingPath(string $name): string
    {
        return self::skeletonRoot() . '/missing/' . $name;
    }

    /**
     * @param list<array<string, bool|int|string>> $entries
     */
    private static function containsExplainPath(array $entries, string $expectedPath): bool
    {
        foreach ($entries as $entry) {
            if (($entry['path'] ?? null) === $expectedPath) {
                return true;
            }
        }

        return false;
    }
}
