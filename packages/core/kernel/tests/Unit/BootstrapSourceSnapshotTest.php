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
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArrayEnvRepository;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\BootstrapSourceSnapshot;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use Coretsia\Kernel\DependencySync\Verification\DependencySyncVerificationCodec;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class BootstrapSourceSnapshotTest extends TestCase
{
    public function testSnapshotIsReadonlyAndRetainsAnImmutableValidatedSourceShape(): void
    {
        $repository = new ArrayEnvRepository(['PACKAGE_TOKEN' => 'not-for-diagnostics']);
        $source = self::source();
        $snapshot = self::snapshot(
            overrides: [
                'appEnv' => 'local',
                'activeTargets' => ['web', 'worker'],
                'preset' => 'micro',
                'presets' => ['worker' => 'enterprise'],
                'moduleOverrides' => [
                    'web' => ['include' => [], 'exclude' => []],
                    'worker' => ['include' => ['platform.worker'], 'exclude' => []],
                ],
                'debug' => true,
                'artifactsCacheDir' => 'var/cache-custom',
            ],
            sourceProvenance: ['presets.worker' => $source],
            envRepository: $repository,
        );

        self::assertTrue(new \ReflectionClass(BootstrapSourceSnapshot::class)->isReadOnly());
        self::assertSame(['web', 'worker'], $snapshot->activeTargets());
        self::assertSame('local', $snapshot->appEnv());
        self::assertSame(BootstrapEnvSourcePolicy::StrictDotenv, $snapshot->envSourcePolicy());
        self::assertSame($repository, $snapshot->envRepository());
        self::assertSame($source, $snapshot->sourceProvenance()['presets.worker']);
        self::assertSame('not-for-diagnostics', $snapshot->envRepository()->get('PACKAGE_TOKEN')->value());

        $overrides = $snapshot->overrides();
        $overrides['presets']['worker'] = 'micro';
        $overrides['moduleOverrides']['worker']['include'][] = 'platform.logging';
        $provenance = $snapshot->sourceProvenance();
        unset($provenance['presets.worker']);

        self::assertSame('enterprise', $snapshot->overrides()['presets']['worker']);
        self::assertSame(['platform.worker'], $snapshot->overrides()['moduleOverrides']['worker']['include']);
        self::assertSame($source, $snapshot->sourceProvenance()['presets.worker']);
    }

    public function testConstructorRejectsNoncanonicalAndUnboundedSourcesWithoutRawDiagnostics(): void
    {
        $valid = ['appEnv' => 'local'];
        $cases = [
            ['', 'local', $valid, [], BootstrapException::REASON_INVALID_APPLICATION_ROOT],
            ["bad\nroot", 'local', $valid, [], BootstrapException::REASON_INVALID_APPLICATION_ROOT],
            ['/project', '', ['appEnv' => ''], [], BootstrapException::REASON_OVERRIDES_INVALID],
            ['/project', 'local', ['appEnv' => 'other'], [], BootstrapException::REASON_OVERRIDES_INVALID],
            [
                '/project',
                'local',
                ['appEnv' => 'local', 'activeTargets' => ['worker', 'web']],
                [],
                BootstrapException::REASON_OVERRIDES_INVALID,
            ],
            [
                '/project',
                'local',
                ['appEnv' => 'local', 'preset' => \str_repeat('x', 5000) . "\nprivate-value"],
                [],
                BootstrapException::REASON_OVERRIDES_INVALID,
            ],
            [
                '/project',
                'local',
                ['appEnv' => 'local', 'moduleOverrides' => ['web' => ['include' => []]]],
                [],
                BootstrapException::REASON_OVERRIDES_INVALID,
            ],
            ['/project', 'local', $valid, ['invalid' => 'raw-secret'], BootstrapException::REASON_OVERRIDES_INVALID],
        ];

        foreach ($cases as [$root, $appEnv, $overrides, $sources, $reason]) {
            try {
                new BootstrapSourceSnapshot(
                    applicationRoot: $root,
                    appEnv: $appEnv,
                    envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
                    overrides: $overrides,
                    sourceProvenance: $sources,
                    envRepository: new ArrayEnvRepository([]),
                );
                self::fail('Expected invalid snapshot source state.');
            } catch (BootstrapException $exception) {
                self::assertSame(BootstrapException::ERROR_CODE, $exception->errorCode());
                self::assertSame($reason, $exception->reason());
                self::assertStringNotContainsString('private-value', $exception->getMessage());
                self::assertStringNotContainsString('raw-secret', $exception->getMessage());
            }
        }
    }

    public function testSnapshotRequiresExplicitApplicationMembershipAndFiltersInactiveSources(): void
    {
        $snapshot = self::snapshot(overrides: [
            'appEnv' => 'local',
            'preset' => 'micro',
            'presets' => ['web' => 'express', 'worker' => 'enterprise'],
            'moduleOverrides' => [
                'web' => ['include' => [], 'exclude' => []],
                'worker' => ['include' => ['platform.worker'], 'exclude' => []],
            ],
            'debug' => true,
            'artifactsCacheDir' => 'var/private-cache',
        ]);

        self::assertNull($snapshot->activeTargets());
        self::assertSame(
            [
                'appEnv' => 'local',
                'envSourcePolicy' => 'strict_dotenv',
                'presets' => ['web' => 'express'],
                'moduleOverrides' => [],
            ],
            $snapshot->installationOverridesForTargets(['web']),
        );
        self::assertSame(
            $snapshot->installationOverridesForTargets(['worker', 'web']),
            $snapshot->installationOverridesForTargets(['web', 'worker']),
        );
        self::assertSame(
            ['worker' => ['include' => ['platform.worker'], 'exclude' => []]],
            $snapshot->installationOverridesForTargets(['worker', 'web'])['moduleOverrides'],
        );
        self::assertArrayNotHasKey('debug', $snapshot->installationOverridesForTargets(['worker']));
        self::assertArrayNotHasKey('artifactsCacheDir', $snapshot->installationOverridesForTargets(['worker']));

        foreach ([[], ['web', 'web'], ['unknown'], ['worker', 'invalid']] as $targets) {
            try {
                $snapshot->installationOverridesForTargets($targets);
                self::fail('Snapshot must not infer or accept noncanonical application membership.');
            } catch (BootstrapException $exception) {
                self::assertSame(BootstrapException::REASON_INVALID_APP_TARGET, $exception->reason());
            }
        }
    }

    public function testPartialModuleOverridePresenceIsPreservedUntilSourceMergeCompletion(): void
    {
        $partial = BootstrapOverridesLoader::normalizePartialOverrides([
            'moduleOverrides' => [
                'web' => ['include' => []],
                'worker' => ['exclude' => ['platform.worker']],
            ],
        ]);

        self::assertSame(['include' => []], $partial['moduleOverrides']['web']);
        self::assertArrayNotHasKey('exclude', $partial['moduleOverrides']['web']);
        self::assertArrayNotHasKey('include', $partial['moduleOverrides']['worker']);
        self::assertSame(
            ['include' => [], 'exclude' => []],
            BootstrapOverridesLoader::completeModuleOverrides($partial)['moduleOverrides']['web'],
        );
        self::assertSame(
            ['include' => [], 'exclude' => ['platform.worker']],
            BootstrapOverridesLoader::completeModuleOverrides($partial)['moduleOverrides']['worker'],
        );
    }

    public function testRootIdentityAndFrozenBootstrapValuesAreEnforcedWithoutReloadingSources(): void
    {
        $root = self::applicationRoot();
        $otherRoot = self::applicationRoot();

        try {
            $resolver = ModeInfrastructureTestSupport::bootstrapConfigResolver();
            $config = self::kernelConfig();
            $source = $resolver->sourceResolver();
            $snapshot = $source->resolveApplicationRoot($root, $config);
            $repository = $snapshot->envRepository();

            self::assertSame($root, $snapshot->applicationRoot());
            $snapshot->assertApplicationRoot($root);
            self::assertSame($repository, $snapshot->envRepository());

            // A second source read would now throw; pre-resolved resolution must not read it.
            \file_put_contents(
                $root . '/config/app.php',
                '<?php throw new \\RuntimeException("unexpected-source-read");',
            );

            foreach ([AppTarget::Web, AppTarget::Worker] as $target) {
                $resolved = $resolver->resolve(new BootstrapInput($root, $target), $config, $snapshot);
                self::assertSame($target, $resolved->appTarget());
                self::assertSame('local', $resolved->appEnv());
            }

            foreach (
                [
                    [
                        new BootstrapInput($otherRoot, AppTarget::Web),
                        BootstrapException::REASON_INVALID_APPLICATION_ROOT,
                    ],
                    [
                        new BootstrapInput($root, AppTarget::Web, appEnv: 'production'),
                        BootstrapException::REASON_OVERRIDES_INVALID,
                    ],
                    [
                        new BootstrapInput(
                            $root,
                            AppTarget::Web,
                            envSourcePolicy: BootstrapEnvSourcePolicy::AllowSystem,
                        ),
                        BootstrapException::REASON_ENV_SOURCE_POLICY_INVALID,
                    ],
                ] as [$input, $reason]
            ) {
                try {
                    $resolver->resolve($input, $config, $snapshot);
                    self::fail('Snapshot cannot be applied to another root or frozen bootstrap context.');
                } catch (BootstrapException $exception) {
                    self::assertSame($reason, $exception->reason());
                    self::assertStringNotContainsString($root, $exception->getMessage());
                }
            }
        } finally {
            self::remove($root);
            self::remove($otherRoot);
        }
    }

    public function testSnapshotDoesNotExposeRootOrRawDotenvValuesInApprovedPlanningPayload(): void
    {
        $secretJson = '{"worker":"hybrid","web":"micro"}';
        $snapshot = self::snapshot(
            overrides: ['appEnv' => 'local', 'presets' => ['web' => 'micro']],
            applicationRoot: '/private/source-root',
            envRepository: new ArrayEnvRepository([
                'CORETSIA_BOOT_PRESETS' => $secretJson,
                'PRIVATE_AUTHORIZATION_TOKEN' => 'private-secret-value',
            ]),
        );
        $identity = $snapshot->installationOverridesForTargets(['web']);
        $plan = new ProjectPackagePlan(
            applications: ['web'],
            effectivePresetsByTarget: ['web' => 'micro'],
            fixedPresetByTarget: [],
            enabledModuleIdsByTarget: ['web' => ['core.foundation', 'core.kernel']],
            excludedModuleIdsByTarget: ['web' => []],
            unionModuleIds: ['core.foundation', 'core.kernel'],
            expectedModuleMetadataById: [
                'core.foundation' => [
                    'composerName' => 'coretsia/core-foundation',
                    'requires' => [],
                    'conflicts' => [],
                ],
                'core.kernel' => [
                    'composerName' => 'coretsia/core-kernel',
                    'requires' => ['core.foundation'],
                    'conflicts' => [],
                ],
            ],
            desiredRootRequirements: [
                'coretsia/core-foundation' => '^0.11.0',
                'coretsia/core-kernel' => '^0.11.0',
            ],
            bootstrapInstallationIdentity: $identity,
        );
        $payload = DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
            $plan,
            'sha256:' . \str_repeat('1', 64),
        );

        self::assertSame('micro', $identity['presets']['web']);
        self::assertStringNotContainsString($secretJson, $payload);
        self::assertStringNotContainsString('private-secret-value', $payload);
        self::assertStringNotContainsString('/private/source-root', $payload);
        self::assertStringNotContainsString('PRIVATE_AUTHORIZATION_TOKEN', $payload);
        self::assertStringNotContainsString('sourceProvenance', $payload);
        self::assertStringNotContainsString('applicationRoot', $payload);
        self::assertFalse(new \ReflectionClass(BootstrapSourceSnapshot::class)->hasMethod('installationIdentity'));
    }

    public function testCanonicalTargetSelectionIgnoresSourceMapInsertionOrder(): void
    {
        $left = self::snapshot(overrides: [
            'appEnv' => 'local',
            'presets' => ['web' => 'micro', 'worker' => 'hybrid'],
            'moduleOverrides' => [
                'web' => ['include' => ['platform.logging'], 'exclude' => []],
                'worker' => ['include' => ['platform.worker'], 'exclude' => []],
            ],
        ]);
        $right = self::snapshot(
            overrides: BootstrapOverridesLoader::completeModuleOverrides(
                BootstrapOverridesLoader::normalizePartialOverrides([
                    'appEnv' => 'local',
                    'moduleOverrides' => [
                        'worker' => ['include' => ['platform.worker'], 'exclude' => []],
                        'web' => ['include' => ['platform.logging'], 'exclude' => []],
                    ],
                    'presets' => ['worker' => 'hybrid', 'web' => 'micro'],
                ]),
            ),
        );

        self::assertSame(
            $left->installationOverridesForTargets(['worker', 'web']),
            $right->installationOverridesForTargets(['web', 'worker']),
        );
    }

    /** @param array<string, mixed> $overrides */
    private static function snapshot(
        array $overrides = ['appEnv' => 'local'],
        string $applicationRoot = '/project',
        array $sourceProvenance = [],
        ?ArrayEnvRepository $envRepository = null,
    ): BootstrapSourceSnapshot {
        return new BootstrapSourceSnapshot(
            applicationRoot: $applicationRoot,
            appEnv: 'local',
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            overrides: $overrides,
            sourceProvenance: $sourceProvenance,
            envRepository: $envRepository ?? new ArrayEnvRepository([]),
        );
    }

    private static function source(): ConfigValueSource
    {
        return new ConfigValueSource(
            type: ConfigSourceType::Dotenv,
            root: 'env',
            sourceId: 'dotenv/.env',
            path: '.env',
            keyPath: 'presets.worker',
            precedence: 1,
            redacted: true,
        );
    }

    /** @return array<string, mixed> */
    private static function kernelConfig(): array
    {
        return require \dirname(__DIR__, 2) . '/config/kernel.php';
    }

    private static function applicationRoot(): string
    {
        $root = \sys_get_temp_dir() . '/coretsia-source-snapshot-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($root . '/config', 0777, true));

        return $root;
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
