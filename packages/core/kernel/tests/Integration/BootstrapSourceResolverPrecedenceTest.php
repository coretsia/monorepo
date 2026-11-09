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

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\BootstrapSourceResolver;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use PHPUnit\Framework\TestCase;

final class BootstrapSourceResolverPrecedenceTest extends TestCase
{
    public function testAllFourDotenvLayersMergePerTargetAndPerFieldWithSafeProvenance(): void
    {
        $root = self::root();

        try {
            self::writeAppConfig($root, [
                'appEnv' => 'test',
                'activeTargets' => ['web', 'worker'],
                'preset' => 'express',
                'presets' => ['api' => 'enterprise', 'worker' => 'micro'],
                'moduleOverrides' => [
                    'web' => ['include' => ['platform.logging'], 'exclude' => ['platform.worker']],
                    'worker' => ['include' => ['platform.worker'], 'exclude' => ['platform.redaction']],
                ],
            ]);
            self::dotenv($root, '.env', [
                'CORETSIA_BOOT_PRESETS={"web":"micro","worker":"hybrid"}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":["platform.errors"]}',
            ]);
            self::dotenv($root, '.env.local', [
                'CORETSIA_BOOT_PRESETS={"web":"express"}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":["platform.cli"]}',
            ]);
            self::dotenv($root, '.env.test', [
                'CORETSIA_BOOT_PRESETS={"worker":"enterprise"}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={"worker":[]}',
            ]);
            self::dotenv($root, '.env.test.local', [
                'CORETSIA_BOOT_PRESETS={"api":"hybrid"}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"worker":["platform.worker"]}',
            ]);

            $snapshot = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());
            $overrides = $snapshot->overrides();

            self::assertSame('test', $snapshot->appEnv());
            self::assertSame(BootstrapEnvSourcePolicy::StrictDotenv, $snapshot->envSourcePolicy());
            self::assertSame(['web', 'worker'], $snapshot->activeTargets());
            self::assertSame(['api' => 'hybrid', 'web' => 'express', 'worker' => 'enterprise'], $overrides['presets']);
            self::assertSame(
                ['include' => ['platform.cli'], 'exclude' => ['platform.worker']],
                $overrides['moduleOverrides']['web']
            );
            self::assertSame(
                ['include' => ['platform.worker'], 'exclude' => []],
                $overrides['moduleOverrides']['worker']
            );

            $sources = $snapshot->sourceProvenance();
            self::assertSame('dotenv/.env.local', $sources['presets.web']->sourceId());
            self::assertSame('dotenv/.env.test', $sources['presets.worker']->sourceId());
            self::assertSame('dotenv/.env.test.local', $sources['presets.api']->sourceId());
            self::assertSame('dotenv/.env.local', $sources['moduleOverrides.web.include']->sourceId());
            self::assertSame('bootstrap/config_app', $sources['moduleOverrides.web.exclude']->sourceId());
            self::assertSame('dotenv/.env.test', $sources['moduleOverrides.worker.exclude']->sourceId());
            self::assertSame('dotenv/.env.test.local', $sources['moduleOverrides.worker.include']->sourceId());
            self::assertSame(ConfigSourceType::Dotenv, $sources['presets.web']->type());
            self::assertTrue($sources['presets.web']->isRedacted());
            self::assertStringNotContainsString(
                $root,
                \json_encode($sources['presets.web']->toArray(), \JSON_THROW_ON_ERROR),
            );

            $first = $snapshot->installationOverridesForTargets(['web', 'worker']);
            self::assertSame(['web' => 'express', 'worker' => 'enterprise'], $first['presets']);
            self::assertArrayNotHasKey('api', $first['presets']);
        } finally {
            self::remove($root);
        }
    }

    public function testEmptyMapsAreNoOpsAndExplicitEmptyListsClearOnlyOneField(): void
    {
        $root = self::root();

        try {
            self::writeAppConfig($root, [
                'appEnv' => 'test',
                'presets' => ['web' => 'micro', 'worker' => 'hybrid'],
                'moduleOverrides' => [
                    'web' => ['include' => ['platform.logging'], 'exclude' => ['platform.worker']],
                    'worker' => ['include' => ['platform.worker'], 'exclude' => ['platform.errors']],
                ],
            ]);
            self::dotenv($root, '.env', [
                'CORETSIA_BOOT_PRESETS={}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={}',
            ]);
            self::dotenv($root, '.env.test', [
                'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":[]}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={"worker":[]}',
            ]);

            $snapshot = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());
            self::assertSame(['web' => 'micro', 'worker' => 'hybrid'], $snapshot->overrides()['presets']);
            self::assertSame(
                ['include' => [], 'exclude' => ['platform.worker']],
                $snapshot->overrides()['moduleOverrides']['web']
            );
            self::assertSame(
                ['include' => ['platform.worker'], 'exclude' => []],
                $snapshot->overrides()['moduleOverrides']['worker']
            );
            self::assertSame(
                'dotenv/.env.test',
                $snapshot->sourceProvenance()['moduleOverrides.web.include']->sourceId(),
            );
            self::assertSame(
                'bootstrap/config_app',
                $snapshot->sourceProvenance()['moduleOverrides.web.exclude']->sourceId(),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testCanonicalJsonMapKeyOrderDoesNotChangeNormalizedSourceValues(): void
    {
        $root = self::root();

        try {
            self::dotenv($root, '.env', [
                'CORETSIA_BOOT_PRESETS={"web":"micro","worker":"hybrid"}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":["platform.logging"],"worker":["platform.worker"]}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={"worker":[],"web":[]}',
            ]);
            $first = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());

            self::dotenv($root, '.env', [
                'CORETSIA_BOOT_PRESETS={"worker":"hybrid","web":"micro"}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"worker":["platform.worker"],"web":["platform.logging"]}',
                'CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={"web":[],"worker":[]}',
            ]);
            $second = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());

            self::assertSame($first->overrides(), $second->overrides());
            self::assertSame(
                $first->installationOverridesForTargets(['web', 'worker']),
                $second->installationOverridesForTargets(['worker', 'web']),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testValidActiveTargetsJsonListReplacesWholeMembershipAtEachDotenvLayer(): void
    {
        $root = self::root();

        try {
            self::writeAppConfig($root, [
                'appEnv' => 'test',
                'activeTargets' => ['api'],
            ]);
            $appConfig = (string) \file_get_contents($root . '/config/app.php');

            foreach (
                [
                    ['.env', 'CORETSIA_BOOT_ACTIVE_TARGETS=["worker","web"]', ['web', 'worker']],
                    ['.env.local', 'CORETSIA_BOOT_ACTIVE_TARGETS=["worker"]', ['worker']],
                    ['.env.test', 'CORETSIA_BOOT_ACTIVE_TARGETS=["api","web"]', ['api', 'web']],
                    ['.env.test.local', 'CORETSIA_BOOT_ACTIVE_TARGETS=["worker","web"]', ['web', 'worker']],
                ] as [$file, $line, $expected]
            ) {
                self::dotenv($root, $file, [$line]);

                $snapshot = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());

                self::assertSame('test', $snapshot->appEnv());
                self::assertSame($expected, $snapshot->activeTargets());
                self::assertSame($expected, $snapshot->overrides()['activeTargets']);
                self::assertSame('dotenv/' . $file, $snapshot->sourceProvenance()['activeTargets']->sourceId());
                self::assertArrayNotHasKey(
                    'activeTargets',
                    $snapshot->installationOverridesForTargets(['web']),
                );
            }

            self::assertSame($appConfig, \file_get_contents($root . '/config/app.php'));
        } finally {
            self::remove($root);
        }
    }

    public function testMalformedBootstrapMapsAndAliasesAreRejectedWithoutRawValues(): void
    {
        $root = self::root();
        $invalid = [
            'CORETSIA_BOOT_PRESETS=["web=micro"]',
            'CORETSIA_BOOT_PRESETS={"web":"micro","web":"enterprise"}',
            'CORETSIA_BOOT_PRESETS={"web":"micro","w\\u0065b":"hybrid"}',
            'CORETSIA_BOOT_PRESETS={"web":[]}',
            'CORETSIA_BOOT_PRESETS={"web":{}}',
            'CORETSIA_BOOT_PRESETS={"unknown":"micro"}',
            'CORETSIA_BOOT_PRESETS={"web":null}',
            'CORETSIA_BOOT_PRESETS={"web":"micro"',
            'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE=["web=platform.logging"]',
            'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":{}}',
            'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":{"x":"platform.logging"}}',
            'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":["platform.logging",1]}',
            'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"unknown":["platform.logging"]}',
            'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":["platform.logging","platform.logging"]}',
            'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":["Platform.Logging"]}',
            'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"web":["not valid"]}',
            'CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={"web":{}}',
            'CORETSIA_BOOT_ACTIVE_TARGETS={"web":true}',
            'CORETSIA_BOOT_ACTIVE_TARGETS=[]',
            'CORETSIA_BOOT_ACTIVE_TARGETS=["web","web"]',
            'CORETSIA_BOOT_ACTIVE_TARGETS=["hidden-target"]',
            'CORETSIA_BOOT_PRESET_WEB=enterprise',
            'CORETSIA_BOOT_MODULE_OVERRIDES_WEB_INCLUDE=["platform.logging"]',
            'CORETSIA_BOOT_UNKNOWN_SECRET=private-value',
        ];

        try {
            foreach ($invalid as $line) {
                self::dotenv($root, '.env', [$line]);
                self::assertInvalidSource($root, BootstrapException::REASON_OVERRIDES_INVALID, 'private-value');
            }
        } finally {
            self::remove($root);
        }
    }

    public function testInvalidLowerPriorityAndInactiveTargetModulesCannotBeHiddenByHigherLayer(): void
    {
        $root = self::root();

        try {
            foreach (
                [
                    'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"worker":["not valid"]}',
                    'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"worker":["platform.worker","platform.worker"]}',
                    'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"worker":["Platform.Worker"]}',
                ] as $line
            ) {
                self::dotenv($root, '.env', [$line]);
                self::dotenv($root, '.env.local', [
                    'CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={"worker":["platform.worker"]}',
                ]);
                self::assertInvalidSource($root, BootstrapException::REASON_OVERRIDES_INVALID, 'not valid');
            }
        } finally {
            self::remove($root);
        }
    }

    public function testStrictJsonShapeDoesNotChangeGenericStableJsonDecoding(): void
    {
        self::assertSame(['web' => []], StableJsonDecoder::decodeStableMap('{"web":{}}'));
        self::assertSame(['web' => []], StableJsonDecoder::decodeStableMap('{"web":[]}'));
        self::assertSame(['web' => 'micro'], StableJsonDecoder::decodeStrictStringMap('{"web":"micro"}'));
        self::assertSame(['web' => []], StableJsonDecoder::decodeStrictStringListMap('{"web":[]}'));

        foreach (
            [
                ['list', '{"web":{}}'],
                ['list', '{"web":null}'],
                ['string', '{"web":[]}'],
                ['string', '{"web":"micro","web":"hybrid"}'],
            ] as [$kind, $json]
        ) {
            try {
                if ($kind === 'list') {
                    StableJsonDecoder::decodeStrictStringListMap($json);
                } else {
                    StableJsonDecoder::decodeStrictStringMap($json);
                }
                self::fail('Strict JSON-map validation must reject the wrong nested shape.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringNotContainsString($json, $exception->getMessage());
            }
        }
    }

    public function testBooleanScalarsAndArtifactsCacheDirectoryUseCanonicalValidation(): void
    {
        $root = self::root();

        try {
            foreach (['true' => true, 'false' => false, '1' => true, '0' => false] as $token => $expected) {
                self::dotenv($root, '.env', ['CORETSIA_BOOT_DEBUG=' . $token]);
                self::assertSame(
                    $expected,
                    self::resolver()->resolveApplicationRoot($root, self::kernelConfig())->overrides()['debug'],
                );
            }

            foreach (['TRUE', 'FALSE', 'yes', 'no', 'on', 'off', '2', ''] as $invalid) {
                self::dotenv($root, '.env', ['CORETSIA_BOOT_DEBUG=' . $invalid]);
                self::assertInvalidSource($root, BootstrapException::REASON_OVERRIDES_INVALID, $invalid);
            }

            foreach (
                [
                    'CORETSIA_BOOT_PRESET=',
                    'CORETSIA_BOOT_APP_ENV=',
                    'CORETSIA_BOOT_ARTIFACTS_CACHE_DIR=',
                ] as $invalid
            ) {
                self::dotenv($root, '.env', [$invalid]);
                self::assertInvalidSource($root, BootstrapException::REASON_OVERRIDES_INVALID);
            }

            self::dotenv($root, '.env', ['CORETSIA_BOOT_ARTIFACTS_CACHE_DIR=var/generated-cache']);
            self::assertSame(
                'var/generated-cache',
                self::resolver()->resolveApplicationRoot($root, self::kernelConfig())->overrides()['artifactsCacheDir'],
            );
            self::dotenv($root, '.env', ['CORETSIA_BOOT_ARTIFACTS_CACHE_DIR=../private-cache']);
            self::assertInvalidSource($root, BootstrapException::REASON_OVERRIDES_INVALID, '../private-cache');
        } finally {
            self::remove($root);
        }
    }

    public function testResourceLimitsRejectOversizedOrExcessivelyNestedBootstrapMaps(): void
    {
        $root = self::root();

        try {
            self::dotenv($root, '.env', ['CORETSIA_BOOT_PRESET=' . \str_repeat('x', 4_096)]);
            self::assertSame(
                \str_repeat('x', 4_096),
                self::resolver()->resolveApplicationRoot($root, self::kernelConfig())->overrides()['preset'],
            );

            foreach (
                [
                    'CORETSIA_BOOT_PRESET=' . \str_repeat('x', 4_097),
                    'CORETSIA_BOOT_PRESETS={"web":"' . \str_repeat('x', 66_000) . '"}',
                    'CORETSIA_BOOT_PRESETS=' . \str_repeat('[', 70) . '0' . \str_repeat(']', 70),
                ] as $line
            ) {
                self::dotenv($root, '.env', [$line]);
                self::assertInvalidSource($root, BootstrapException::REASON_OVERRIDES_INVALID);
            }
        } finally {
            self::remove($root);
        }
    }

    public function testAppEnvFreezeAndEnvironmentSpecificMismatchFailClosed(): void
    {
        $root = self::root();

        try {
            self::writeAppConfig($root, ['appEnv' => 'test', 'preset' => 'express']);
            self::dotenv($root, '.env', ['CORETSIA_BOOT_APP_ENV=test']);
            self::dotenv($root, '.env.test', ['CORETSIA_BOOT_APP_ENV=production']);
            self::assertInvalidSource($root, BootstrapException::REASON_OVERRIDES_INVALID, 'production');

            self::dotenv($root, '.env.test', ['CORETSIA_BOOT_APP_ENV=test']);
            $snapshot = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());
            self::assertSame('test', $snapshot->appEnv());
            self::assertSame('dotenv/.env', $snapshot->sourceProvenance()['appEnv']->sourceId());
        } finally {
            self::remove($root);
        }
    }

    public function testExplicitAppEnvOverridesDifferentProcessValueWithoutSpuriousConflict(): void
    {
        $root = self::root();

        try {
            self::dotenv($root, '.env.test', ['CORETSIA_BOOT_PRESET=micro']);
            self::withSystemEnv(
                ['CORETSIA_BOOT_APP_ENV' => 'production'],
                static function () use ($root): void {
                    $snapshot = self::resolver()->resolveApplicationRoot(
                        $root,
                        self::kernelConfig(),
                        'test',
                        BootstrapEnvSourcePolicy::AllowSystem,
                    );

                    self::assertSame('test', $snapshot->appEnv());
                    self::assertSame(BootstrapEnvSourcePolicy::AllowSystem, $snapshot->envSourcePolicy());
                    self::assertSame('bootstrap/input', $snapshot->sourceProvenance()['appEnv']->sourceId());
                },
            );
        } finally {
            self::remove($root);
        }
    }

    public function testSystemAppEnvSelectionRequiresExplicitAllowSystemPolicy(): void
    {
        $root = self::root();

        try {
            self::withSystemEnv(
                ['CORETSIA_BOOT_APP_ENV' => 'production', 'CORETSIA_BOOT_PRESETS' => '{"web":"hybrid"}'],
                static function () use ($root): void {
                    $strict = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());
                    self::assertSame('local', $strict->appEnv());
                    self::assertSame(BootstrapEnvSourcePolicy::StrictDotenv, $strict->envSourcePolicy());
                    self::assertSame([], $strict->installationOverridesForTargets(['web'])['presets']);

                    $system = self::resolver()->resolveApplicationRoot(
                        $root,
                        self::kernelConfig(),
                        explicitPolicy: BootstrapEnvSourcePolicy::AllowSystem,
                    );
                    self::assertSame('production', $system->appEnv());
                    self::assertSame('hybrid', $system->overrides()['presets']['web']);
                    self::assertSame('system_env', $system->sourceProvenance()['presets.web']->sourceId());
                },
            );
        } finally {
            self::remove($root);
        }
    }

    public function testProductionDefaultAllowSystemDoesNotReopenAppEnvSelection(): void
    {
        $root = self::root();

        try {
            self::writeAppConfig($root, ['appEnv' => 'production']);
            self::dotenv($root, '.env.production', ['CORETSIA_BOOT_PRESET=micro']);

            self::withSystemEnv(
                ['CORETSIA_BOOT_APP_ENV' => 'test', 'CORETSIA_BOOT_PRESET' => 'hybrid'],
                static function () use ($root): void {
                    $snapshot = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());
                    self::assertSame('production', $snapshot->appEnv());
                    self::assertSame(BootstrapEnvSourcePolicy::AllowSystem, $snapshot->envSourcePolicy());
                    self::assertSame('hybrid', $snapshot->overrides()['preset']);
                    self::assertSame('bootstrap/config_app', $snapshot->sourceProvenance()['appEnv']->sourceId());
                },
            );
        } finally {
            self::remove($root);
        }
    }

    public function testStrictDotenvIgnoresProcessBootstrapKeysIncludingUnknownOnes(): void
    {
        $root = self::root();

        try {
            self::dotenv($root, '.env', ['CORETSIA_BOOT_PRESET=micro']);
            $before = (string) \file_get_contents($root . '/.env');

            self::withSystemEnv(
                [
                    'CORETSIA_BOOT_APP_ENV' => 'production',
                    'CORETSIA_BOOT_PRESET' => 'enterprise',
                    'CORETSIA_BOOT_UNKNOWN_PRIVATE_TOKEN' => 'not-for-diagnostics',
                ],
                static function () use ($root): void {
                    $snapshot = self::resolver()->resolveApplicationRoot($root, self::kernelConfig());
                    self::assertSame('local', $snapshot->appEnv());
                    self::assertSame('micro', $snapshot->overrides()['preset']);
                    self::assertSame(BootstrapEnvSourcePolicy::StrictDotenv, $snapshot->envSourcePolicy());
                },
            );

            self::assertSame($before, \file_get_contents($root . '/.env'));
            self::assertFileDoesNotExist($root . '/config/app.php');
        } finally {
            self::remove($root);
        }
    }

    public function testAllowSystemRejectsUnknownProcessBootstrapKeyWithoutRawDiagnostics(): void
    {
        $root = self::root();

        try {
            self::withSystemEnv(
                ['CORETSIA_BOOT_UNKNOWN_PRIVATE_TOKEN' => 'not-for-diagnostics'],
                static function () use ($root): void {
                    try {
                        self::resolver()->resolveApplicationRoot(
                            $root,
                            self::kernelConfig(),
                            explicitPolicy: BootstrapEnvSourcePolicy::AllowSystem,
                        );
                        self::fail('Unknown allowed process bootstrap source must fail closed.');
                    } catch (BootstrapException $exception) {
                        self::assertSame(BootstrapException::REASON_OVERRIDES_INVALID, $exception->reason());
                        self::assertStringNotContainsString('not-for-diagnostics', $exception->getMessage());
                        self::assertStringNotContainsString($root, $exception->getMessage());
                    }
                },
            );
        } finally {
            self::remove($root);
        }
    }

    public function testExplicitInputSourceValuesStayTargetLocalAfterSnapshotResolution(): void
    {
        $root = self::root();

        try {
            self::writeAppConfig($root, [
                'activeTargets' => ['web', 'worker'],
                'preset' => 'micro',
                'presets' => ['worker' => 'hybrid'],
            ]);
            $config = self::kernelConfig();
            $resolver = new BootstrapConfigResolver(
                new ModuleIdSetNormalizer(),
                self::resolver(),
            );
            $snapshot = $resolver->sourceResolver()->resolveApplicationRoot($root, $config);

            $web = $resolver->resolve(
                new BootstrapInput(
                    $root,
                    AppTarget::Web,
                    preset: 'express',
                    debug: true,
                    artifactsCacheDir: 'var/special',
                ),
                $config,
                $snapshot,
            );
            $worker = $resolver->resolve(new BootstrapInput($root, AppTarget::Worker), $config, $snapshot);

            self::assertSame('express', $web->preset());
            self::assertSame('hybrid', $worker->preset());
            self::assertTrue($web->debug());
            self::assertFalse($worker->debug());
            self::assertSame('var/special', $web->artifactsCacheDir());
            self::assertSame('micro', $snapshot->overrides()['preset']);
            self::assertSame(['web', 'worker'], $snapshot->activeTargets());
        } finally {
            self::remove($root);
        }
    }

    private static function resolver(): BootstrapSourceResolver
    {
        $dotenvLoader = new DotenvLoader();

        return new BootstrapSourceResolver(
            new BootstrapOverridesLoader(),
            $dotenvLoader,
            new EnvRepositoryBuilder($dotenvLoader),
        );
    }

    /** @return array<string, mixed> */
    private static function kernelConfig(): array
    {
        return require \dirname(__DIR__, 2) . '/config/kernel.php';
    }

    private static function root(): string
    {
        $root = \sys_get_temp_dir() . '/coretsia-source-precedence-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($root . '/config', 0777, true));

        return $root;
    }

    /** @param array<string, mixed> $values */
    private static function writeAppConfig(string $root, array $values): void
    {
        self::assertIsInt(
            \file_put_contents($root . '/config/app.php', "<?php\n\nreturn " . \var_export($values, true) . ";\n"),
        );
    }

    /** @param list<string> $lines */
    private static function dotenv(string $root, string $file, array $lines): void
    {
        self::assertIsInt(\file_put_contents($root . '/' . $file, \implode("\n", $lines) . "\n"));
    }

    private static function assertInvalidSource(
        string $root,
        string $reason,
        string $rawValue = '',
    ): void {
        try {
            self::resolver()->resolveApplicationRoot($root, self::kernelConfig());
            self::fail('Invalid bootstrap source must fail before target-local resolution.');
        } catch (BootstrapException $exception) {
            self::assertSame(BootstrapException::ERROR_CODE, $exception->errorCode());
            self::assertSame($reason, $exception->reason());
            self::assertStringNotContainsString($root, $exception->getMessage());
            if ($rawValue !== '') {
                self::assertStringNotContainsString($rawValue, $exception->getMessage());
            }
        }
    }

    /** @param array<string, string> $values */
    private static function withSystemEnv(array $values, callable $callback): void
    {
        $previous = [];

        foreach ($values as $name => $value) {
            $previous[$name] = [
                'getenv' => \getenv($name),
                'envExists' => \array_key_exists($name, $_ENV),
                'env' => $_ENV[$name] ?? null,
                'serverExists' => \array_key_exists($name, $_SERVER),
                'server' => $_SERVER[$name] ?? null,
            ];
            \putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        try {
            $callback();
        } finally {
            foreach ($previous as $name => $state) {
                if ($state['getenv'] === false) {
                    \putenv($name);
                } else {
                    \putenv($name . '=' . $state['getenv']);
                }

                if ($state['envExists']) {
                    $_ENV[$name] = $state['env'];
                } else {
                    unset($_ENV[$name]);
                }

                if ($state['serverExists']) {
                    $_SERVER[$name] = $state['server'];
                } else {
                    unset($_SERVER[$name]);
                }
            }
        }
    }

    private static function remove(string $path): void
    {
        if (\is_file($path) || \is_link($path)) {
            self::assertTrue(\unlink($path));
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }

        self::assertTrue(\rmdir($path));
    }
}
