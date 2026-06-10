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

use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Env\EnvValue;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\ArtifactWriter;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Builders\StubContainerBuilder;
use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Artifacts\Verifier\CacheVerifier;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\ConfigRulesLoader;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader;
use Coretsia\Kernel\Config\Loaders\PackageDefaultsConfigLoader;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ArtifactPipelineTestSupport
{
    private function __construct()
    {
    }

    public static function temporaryRoot(string $name): string
    {
        $root = \sys_get_temp_dir()
            . '/coretsia-'
            . $name
            . '-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root, 0777, true);

        return $root;
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function writeRootConfig(string $skeletonRoot, array $config): void
    {
        self::writePhpReturn($skeletonRoot . '/config/roots.php', $config);
    }

    /**
     * @param array<string,mixed> $value
     */
    public static function writePhpReturn(string $path, array $value): void
    {
        $directory = \dirname($path);

        if (!\is_dir($directory)) {
            \mkdir($directory, 0777, true);
        }

        \file_put_contents(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($value, true) . ";\n",
        );
    }

    public static function removeTree(string $path): void
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

            if (\is_dir($itemPath) && !\is_link($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            \unlink($itemPath);
        }

        \rmdir($path);
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaultConfig(string $value = 'safe-value'): array
    {
        return [
            'custom' => [
                'feature' => [
                    'value' => $value,
                ],
            ],
            'kernel' => [
                'boot' => [
                    'default_env' => 'prod',
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function kernelConfig(): array
    {
        return [
            'artifacts' => [
                'cache_dir' => 'var/cache',
            ],
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

    public static function bootstrapConfig(string $skeletonRoot): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'prod',
            preset: 'default',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: $skeletonRoot,
        );
    }

    public static function modulePlan(): ModulePlan
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

    public static function envRepository(): EnvRepositoryInterface
    {
        return new class() implements EnvRepositoryInterface {
            public function has(string $name): bool
            {
                return false;
            }

            public function get(string $name): EnvValue
            {
                throw new \LogicException('Env values must not be read by artifact integration tests.');
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
     * @param array<string,mixed> $config
     */
    public static function compileArtifacts(TestCase $testCase, string $skeletonRoot, array $config): array
    {
        self::writeRootConfig($skeletonRoot, $config);

        return self::artifactCompiler($testCase)->compile(
            bootstrapConfig: self::bootstrapConfig($skeletonRoot),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: [],
        );
    }

    public static function verifyArtifacts(TestCase $testCase, string $skeletonRoot): array
    {
        return self::cacheVerifier($testCase)->verify(
            bootstrapConfig: self::bootstrapConfig($skeletonRoot),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: [],
        );
    }

    public static function fingerprintForCurrentConfig(TestCase $testCase, string $skeletonRoot): string
    {
        $compiled = self::configKernel($testCase)->compile(
            bootstrapConfig: self::bootstrapConfig($skeletonRoot),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            explain: false,
        );

        $input = self::fingerprintInputBuilder()->build(
            bootstrapConfig: self::bootstrapConfig($skeletonRoot),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: self::kernelConfig(),
            compiledConfig: $compiled,
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            modePresetSourceCandidates: [],
        );

        return self::fingerprintCalculator($testCase)->calculate($input);
    }

    /**
     * @return array<string,string>
     */
    public static function artifactBytes(string $skeletonRoot): array
    {
        $paths = self::artifactPaths($skeletonRoot);
        $bytes = [];

        foreach ($paths as $basename => $path) {
            $content = \file_get_contents($path);

            TestCase::assertIsString($content);

            $bytes[$basename] = $content;
        }

        \ksort($bytes, \SORT_STRING);

        return $bytes;
    }

    /**
     * @return array<string,string>
     */
    public static function artifactPaths(string $skeletonRoot): array
    {
        return [
            'config.php' => $skeletonRoot . '/var/cache/web/config.php',
            'container.php' => $skeletonRoot . '/var/cache/web/container.php',
            'module-manifest.php' => $skeletonRoot . '/var/cache/web/module-manifest.php',
        ];
    }

    public static function artifactPath(string $skeletonRoot, string $basename): string
    {
        $paths = self::artifactPaths($skeletonRoot);

        if (!isset($paths[$basename])) {
            throw new \InvalidArgumentException('test-artifact-basename-invalid');
        }

        return $paths[$basename];
    }

    public static function artifactCompiler(TestCase $testCase): ArtifactCompiler
    {
        $envelopeFactory = self::envelopeFactory();

        return new ArtifactCompiler(
            configKernel: self::configKernel($testCase),
            fingerprintInputBuilder: self::fingerprintInputBuilder(),
            fingerprintCalculator: self::fingerprintCalculator($testCase),
            moduleManifestBuilder: new ModuleManifestBuilder($envelopeFactory),
            compiledConfigBuilder: new CompiledConfigBuilder($envelopeFactory),
            stubContainerBuilder: new StubContainerBuilder($envelopeFactory),
            artifactWriter: self::artifactWriter($testCase),
            pathResolver: new ArtifactPathResolver(),
        );
    }

    public static function cacheVerifier(TestCase $testCase): CacheVerifier
    {
        $envelopeFactory = self::envelopeFactory();

        return new CacheVerifier(
            configKernel: self::configKernel($testCase),
            fingerprintInputBuilder: self::fingerprintInputBuilder(),
            fingerprintCalculator: self::fingerprintCalculator($testCase),
            moduleManifestBuilder: new ModuleManifestBuilder($envelopeFactory),
            compiledConfigBuilder: new CompiledConfigBuilder($envelopeFactory),
            stubContainerBuilder: new StubContainerBuilder($envelopeFactory),
            phpArrayDumper: new StablePhpArrayDumper(new PayloadNormalizer()),
            artifactReader: new PhpArtifactReader(),
            schemaValidator: new ArtifactSchemaValidator(),
            pathResolver: new ArtifactPathResolver(),
            tracer: self::tracer($testCase),
            meter: self::meter(),
            logger: self::logger(),
            stopwatch: new Stopwatch(),
        );
    }

    public static function artifactWriter(TestCase $testCase): ArtifactWriter
    {
        return new ArtifactWriter(
            phpArrayDumper: new StablePhpArrayDumper(new PayloadNormalizer()),
            tracer: self::tracer($testCase),
            meter: self::meter(),
            logger: self::logger(),
            stopwatch: new Stopwatch(),
        );
    }

    private static function configKernel(TestCase $testCase): ConfigKernel
    {
        $namespaceGuard = new ConfigNamespaceGuard([
            'coretsia',
            '_internal',
        ]);

        $directiveProcessor = new DirectiveProcessor($namespaceGuard);

        return new ConfigKernel(
            merger: new ConfigMerger($directiveProcessor),
            rulesLoader: new ConfigRulesLoader(),
            validator: new ConfigValidator(),
            explainer: new ConfigExplainer(),
            packageDefaultsLoader: new PackageDefaultsConfigLoader($directiveProcessor),
            skeletonLoader: new SkeletonConfigLoader($directiveProcessor),
            environmentOverlayLoader: new EnvironmentOverlayLoader(),
            meter: self::meter(),
            tracer: self::tracer($testCase),
            stopwatch: new Stopwatch(),
            logger: self::logger(),
            defaultExplicitEnvOverlayMappings: [],
        );
    }

    private static function fingerprintInputBuilder(): ConfigFingerprintInputBuilder
    {
        return new ConfigFingerprintInputBuilder(
            payloadNormalizer: new PayloadNormalizer(),
            fileLister: new DeterministicFileLister(),
        );
    }

    private static function fingerprintCalculator(TestCase $testCase): FingerprintCalculator
    {
        return new FingerprintCalculator(
            payloadNormalizer: new PayloadNormalizer(),
            tracer: self::tracer($testCase),
            meter: self::meter(),
            logger: self::logger(),
            stopwatch: new Stopwatch(),
        );
    }

    private static function envelopeFactory(): ArtifactEnvelopeFactory
    {
        return new ArtifactEnvelopeFactory(new PayloadNormalizer());
    }

    private static function tracer(TestCase $_testCase): TracerPortInterface
    {
        return new class() implements TracerPortInterface {
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                return ArtifactPipelineTestSupport::span($name);
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = ArtifactPipelineTestSupport::span($name);

                try {
                    return $callback($span);
                } finally {
                    $span->end();
                }
            }

            public function currentSpan(): ?SpanInterface
            {
                return null;
            }
        };
    }

    public static function span(string $name = 'kernel.test'): SpanInterface
    {
        return new class($name) implements SpanInterface {
            public function __construct(
                private readonly string $name,
            ) {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function setAttribute(string $key, mixed $value): void
            {
            }

            public function setAttributes(array $attributes): void
            {
            }

            public function addEvent(string $name, array $attributes = []): void
            {
            }

            public function recordException(\Throwable $throwable, array $attributes = []): void
            {
            }

            public function end(): void
            {
            }
        };
    }

    private static function meter(): MeterPortInterface
    {
        return new class() implements MeterPortInterface {
            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
            }
        };
    }

    private static function logger(): LoggerInterface
    {
        return new NullLogger();
    }
}
