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
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Env\EnvValue;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintExplainer;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FingerprintIncludesUserOwnedConfigRootsTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-fingerprint-user-roots-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testCustomRootsAffectConfigFingerprint(): void
    {
        $withoutCustom = self::fingerprintForConfig([
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ]);

        $withCustom = self::fingerprintForConfig([
            'custom_root' => [
                'feature' => [
                    'enabled' => true,
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ]);

        self::assertNotSame($withoutCustom, $withCustom);
    }

    public function testChangingUserOwnedConfigValueChangesFingerprint(): void
    {
        $first = self::fingerprintForConfig([
            'custom_root' => [
                'feature' => [
                    'name' => 'first-value',
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ]);

        $second = self::fingerprintForConfig([
            'custom_root' => [
                'feature' => [
                    'name' => 'second-value',
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ]);

        self::assertNotSame($first, $second);
    }

    public function testUserOwnedRootsArePresentInCompiledConfigArtifact(): void
    {
        $compiledConfig = self::compiledConfig([
            'custom_root' => [
                'feature' => [
                    'enabled' => true,
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ]);

        $artifact = new CompiledConfigBuilder(
            new ArtifactEnvelopeFactory(new PayloadNormalizer()),
        )->build(
            compiledConfig: $compiledConfig,
            fingerprint: \str_repeat('a', 64),
        );

        self::assertSame(
            [
                'feature' => [
                    'enabled' => true,
                ],
            ],
            $artifact['payload']['config']['custom_root'],
        );
    }

    public function testFingerprintExplainMarksUserOwnedRootsAsUnvalidatedWhenNoRulesExist(): void
    {
        $input = self::fingerprintInputForConfig([
            'custom_root' => [
                'feature' => [
                    'name' => 'safe-visible-through-hash-only',
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ]);

        $explain = new FingerprintExplainer()->explain($input);

        self::assertTrue(
            self::containsEntry(
                entries: $explain['entries'],
                expected: [
                    'kind' => 'validation_subject',
                    'keyPath' => 'custom_root',
                    'validation' => 'unvalidated',
                ],
            ),
        );
    }

    public function testFingerprintExplainDoesNotLeakRawCustomValues(): void
    {
        $input = self::fingerprintInputForConfig([
            'custom_root' => [
                'feature' => [
                    'name' => 'raw-custom-secret-value',
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ]);

        $explain = new FingerprintExplainer()->explain($input);
        $encoded = \json_encode($explain, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('raw-custom-secret-value', $encoded);
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function fingerprintForConfig(array $config): string
    {
        return self::fingerprintCalculator()->calculate(
            self::fingerprintInputForConfig($config),
        );
    }

    /**
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    private static function fingerprintInputForConfig(array $config): array
    {
        return new ConfigFingerprintInputBuilder()->build(
            bootstrapConfig: self::bootstrapConfig(),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            compiledConfig: self::compiledConfig($config),
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: \array_keys($config),
            explicitRuleSources: [],
            modePresetSourceCandidates: [],
        );
    }

    /**
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    private static function compiledConfig(array $config): array
    {
        $validator = new ConfigValidator();

        $validation = $validator->validate($config, [
            self::kernelRuleset(),
        ]);

        self::assertTrue($validation->isSuccess());

        return [
            'config' => $config,
            'sources' => self::configSources($config),
            'owners' => [],
            'envOverlayMappings' => [],
            'configSourceFiles' => [],
            'validation' => ConfigValidationResult::success(),
            'validationSubjects' => $validator->validationSubjects($config, [
                self::kernelRuleset(),
            ]),
        ];
    }

    /**
     * @param array<string,mixed> $config
     *
     * @return list<ConfigValueSource>
     */
    private static function configSources(array $config): array
    {
        $sources = [];

        foreach (\array_keys($config) as $root) {
            if (!\is_string($root)) {
                continue;
            }

            $sources[] = new ConfigValueSource(
                type: \Coretsia\Contracts\Config\ConfigSourceType::SkeletonConfig,
                root: $root,
                sourceId: 'skeleton-config/test/' . $root,
                path: 'skeleton/config/roots.php',
                keyPath: $root,
                directive: null,
                precedence: 100,
                redacted: false,
                meta: [
                    'kind' => 'aggregate_root_map',
                    'layer' => 'skeleton_shared',
                ],
            );
        }

        return $sources;
    }

    private static function kernelRuleset(): ConfigRuleset
    {
        return ConfigRuleset::fromArray('kernel', [
            'configRoot' => 'kernel',
            'schemaVersion' => 1,
            'additionalKeys' => false,
            'type' => 'map',
            'keys' => [
                'boot' => [
                    'type' => 'map',
                    'required' => true,
                    'additionalKeys' => false,
                    'keys' => [
                        'default_env' => [
                            'type' => 'non-empty-string-no-ws',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ]);
    }

    private static function fingerprintCalculator(): FingerprintCalculator
    {
        $testCase = new self('runTest');

        $span = $testCase->createStub(SpanInterface::class);

        $tracer = $testCase->createStub(TracerPortInterface::class);
        $tracer
            ->method('startSpan')
            ->willReturn($span);
        $tracer
            ->method('currentSpan')
            ->willReturn(null);
        $tracer
            ->method('inSpan')
            ->willReturnCallback(
                static fn (
                    string $_name,
                    callable $callback,
                    array $_attributes = [],
                ): mixed => $callback($span),
            );

        return new FingerprintCalculator(
            payloadNormalizer: new PayloadNormalizer(),
            tracer: $tracer,
            meter: $testCase->createStub(MeterPortInterface::class),
            logger: $testCase->createStub(LoggerInterface::class),
            stopwatch: new Stopwatch(),
        );
    }

    private static function bootstrapConfig(): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'prod',
            preset: 'default',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: \sys_get_temp_dir() . '/coretsia-fingerprint-user-roots-bootstrap',
        );
    }

    private static function modulePlan(): ModulePlan
    {
        return new ModulePlan(
            app: 'web',
            preset: 'default',
            enabled: [],
            disabled: [],
            optionalMissing: [],
            topologicalOrder: [],
            modules: [],
            warnings: [],
        );
    }

    /**
     * @return array<string,mixed>
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
                throw new \LogicException('Env values must not be read by user-owned config fingerprint tests.');
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

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $items = \scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (\is_dir($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            \unlink($itemPath);
        }

        \rmdir($path);
    }
}
