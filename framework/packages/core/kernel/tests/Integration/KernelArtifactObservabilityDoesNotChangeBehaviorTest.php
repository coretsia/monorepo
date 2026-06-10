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
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class KernelArtifactObservabilityDoesNotChangeBehaviorTest extends TestCase
{
    private string $skeletonRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skeletonRoot = ArtifactPipelineTestSupport::temporaryRoot('artifact-observability-behavior');
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->skeletonRoot);

        parent::tearDown();
    }

    public function testFailingMeterDoesNotFailArtifactWrite(): void
    {
        $targetPath = $this->skeletonRoot . '/var/cache/web/custom.txt';

        $result = self::artifactWriter(
            tracer: self::noopTracer(),
            meter: self::failingMeter(),
            logger: self::noopLogger(),
        )->writeTextArtifact(
            targetPath: $targetPath,
            relativePath: 'var/cache/web/custom.txt',
            bytes: "ok\n",
        );

        self::assertSame('custom.txt', $result['basename']);
        self::assertSame("ok\n", \file_get_contents($targetPath));
    }

    public function testFailingTracerDoesNotFailFingerprintCalculation(): void
    {
        $fingerprint = self::fingerprintCalculator(
            tracer: self::failingTracer(),
            meter: self::noopMeter(),
            logger: self::noopLogger(),
        )->calculate([
            'schemaVersion' => 1,
            'safe' => [
                'value' => 'hash-only',
            ],
        ]);

        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $fingerprint);
    }

    public function testFailingLoggerDoesNotFailCacheVerification(): void
    {
        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        $result = self::cacheVerifier(
            tracer: self::noopTracer(),
            meter: self::noopMeter(),
            logger: self::failingLogger(),
        )->verify(
            bootstrapConfig: self::bootstrapConfig($this->skeletonRoot),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: ArtifactPipelineTestSupport::kernelConfig(),
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: [],
        );

        self::assertSame('clean', $result['outcome']);
        self::assertTrue($result['clean']);
        self::assertFalse($result['dirty']);
        self::assertFalse($result['invalid']);
    }

    public function testObservabilityFailuresDoNotChangeCleanDirtyInvalidVerificationSemantics(): void
    {
        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        $clean = self::verifyWithFailingObservability($this->skeletonRoot);

        self::assertSame('clean', $clean['outcome']);
        self::assertTrue($clean['clean']);
        self::assertFalse($clean['dirty']);
        self::assertFalse($clean['invalid']);

        $path = ArtifactPipelineTestSupport::artifactPath($this->skeletonRoot, 'config.php');
        $bytes = \file_get_contents($path);

        self::assertIsString($bytes);

        \file_put_contents(
            $path,
            \str_replace(
                "<?php\n\nreturn",
                "<?php\n\n/* drift */\nreturn",
                $bytes,
            ),
        );

        $dirty = self::verifyWithFailingObservability($this->skeletonRoot);

        self::assertSame('dirty', $dirty['outcome']);
        self::assertFalse($dirty['clean']);
        self::assertTrue($dirty['dirty']);
        self::assertFalse($dirty['invalid']);

        \file_put_contents($path, "<?php\nreturn [\n");

        $invalid = self::verifyWithFailingObservability($this->skeletonRoot);

        self::assertSame('invalid', $invalid['outcome']);
        self::assertFalse($invalid['clean']);
        self::assertFalse($invalid['dirty']);
        self::assertTrue($invalid['invalid']);
    }

    public function testArtifactFingerprintAndCacheServicesDependOnlyOnObservabilityPortsAndStopwatch(): void
    {
        self::assertConstructorUsesOnlyAllowedObservabilityTypes(
            ArtifactWriter::class,
            [
                TracerPortInterface::class,
                MeterPortInterface::class,
                LoggerInterface::class,
                Stopwatch::class,
            ],
        );

        self::assertConstructorUsesOnlyAllowedObservabilityTypes(
            FingerprintCalculator::class,
            [
                TracerPortInterface::class,
                MeterPortInterface::class,
                LoggerInterface::class,
                Stopwatch::class,
            ],
        );

        self::assertConstructorUsesOnlyAllowedObservabilityTypes(
            CacheVerifier::class,
            [
                TracerPortInterface::class,
                MeterPortInterface::class,
                LoggerInterface::class,
                Stopwatch::class,
            ],
        );
    }

    public function testArtifactFingerprintAndCacheServicesDoNotInstantiateConcreteObservabilityImplementations(): void
    {
        foreach (
            [
                'src/Artifacts/ArtifactWriter.php',
                'src/Artifacts/Fingerprint/FingerprintCalculator.php',
                'src/Artifacts/Verifier/CacheVerifier.php',
                'src/Provider/KernelServiceFactory.php',
            ] as $relativePath
        ) {
            $source = self::sourceWithoutComments($relativePath);

            foreach (
                [
                    'Noop',
                    'NullLogger',
                    'NoopLogger',
                    'NoopMeter',
                    'NoopTracer',
                    'new Logger',
                    'new Meter',
                    'new Tracer',
                ] as $forbiddenNeedle
            ) {
                self::assertStringNotContainsString(
                    $forbiddenNeedle,
                    $source,
                    $relativePath . ' must not instantiate or select concrete observability implementations.',
                );
            }
        }
    }

    public function testFakeNoopObservabilityPortsCanBeInjectedWithoutChangingCompileVerifyBehavior(): void
    {
        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        $result = self::cacheVerifier(
            tracer: self::noopTracer(),
            meter: self::noopMeter(),
            logger: self::noopLogger(),
        )->verify(
            bootstrapConfig: self::bootstrapConfig($this->skeletonRoot),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: ArtifactPipelineTestSupport::kernelConfig(),
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: [],
        );

        self::assertSame('clean', $result['outcome']);
        self::assertTrue($result['clean']);
        self::assertFalse($result['dirty']);
        self::assertFalse($result['invalid']);
    }

    public function testRealVsNoopDefaultObservabilityBindingIsNotAssertedByThisEpic(): void
    {
        $factorySource = self::sourceWithoutComments('src/Provider/KernelServiceFactory.php');

        self::assertStringContainsString(TracerPortInterface::class, $factorySource);
        self::assertStringContainsString(MeterPortInterface::class, $factorySource);
        self::assertStringContainsString(LoggerInterface::class, $factorySource);

        self::assertStringNotContainsString('NoopTracer', $factorySource);
        self::assertStringNotContainsString('NoopMeter', $factorySource);
        self::assertStringNotContainsString('NoopLogger', $factorySource);
        self::assertStringNotContainsString('NullLogger', $factorySource);
    }

    /**
     * @return array<string,mixed>
     */
    private static function verifyWithFailingObservability(string $skeletonRoot): array
    {
        return self::cacheVerifier(
            tracer: self::failingTracer(),
            meter: self::failingMeter(),
            logger: self::failingLogger(),
        )->verify(
            bootstrapConfig: self::bootstrapConfig($skeletonRoot),
            modulePlan: self::modulePlan(),
            env: self::envRepository(),
            kernelConfig: ArtifactPipelineTestSupport::kernelConfig(),
            packageDefaultSources: [],
            packageRuleSources: [],
            splitRoots: [],
            explicitRuleSources: [],
            explicitEnvOverlayMappings: [],
            modePresetSourceCandidates: [],
        );
    }

    private static function artifactWriter(
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        LoggerInterface $logger,
    ): ArtifactWriter {
        return new ArtifactWriter(
            phpArrayDumper: new StablePhpArrayDumper(new PayloadNormalizer()),
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
            stopwatch: new Stopwatch(),
        );
    }

    private static function fingerprintCalculator(
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        LoggerInterface $logger,
    ): FingerprintCalculator {
        return new FingerprintCalculator(
            payloadNormalizer: new PayloadNormalizer(),
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
            stopwatch: new Stopwatch(),
        );
    }

    private static function cacheVerifier(
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        LoggerInterface $logger,
    ): CacheVerifier {
        $envelopeFactory = new ArtifactEnvelopeFactory(new PayloadNormalizer());

        return new CacheVerifier(
            configKernel: self::configKernel(),
            fingerprintInputBuilder: new ConfigFingerprintInputBuilder(
                payloadNormalizer: new PayloadNormalizer(),
                fileLister: new DeterministicFileLister(),
            ),
            fingerprintCalculator: self::fingerprintCalculator(
                tracer: $tracer,
                meter: $meter,
                logger: $logger,
            ),
            moduleManifestBuilder: new ModuleManifestBuilder($envelopeFactory),
            compiledConfigBuilder: new CompiledConfigBuilder($envelopeFactory),
            stubContainerBuilder: new StubContainerBuilder($envelopeFactory),
            phpArrayDumper: new StablePhpArrayDumper(new PayloadNormalizer()),
            artifactReader: new PhpArtifactReader(),
            schemaValidator: new ArtifactSchemaValidator(),
            pathResolver: new ArtifactPathResolver(),
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
            stopwatch: new Stopwatch(),
        );
    }

    private static function configKernel(): ConfigKernel
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
            meter: self::noopMeter(),
            tracer: self::noopTracer(),
            stopwatch: new Stopwatch(),
            logger: self::noopLogger(),
            defaultExplicitEnvOverlayMappings: [],
        );
    }

    private static function bootstrapConfig(string $skeletonRoot): BootstrapConfig
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

    private static function envRepository(): EnvRepositoryInterface
    {
        return new class() implements EnvRepositoryInterface {
            public function has(string $name): bool
            {
                return false;
            }

            public function get(string $name): EnvValue
            {
                throw new \LogicException('Env values must not be read by observability behavior tests.');
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
     * @param list<class-string> $allowedTypes
     */
    private static function assertConstructorUsesOnlyAllowedObservabilityTypes(
        string $className,
        array $allowedTypes,
    ): void {
        $constructor = new \ReflectionMethod($className, '__construct');

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();

            if (!\in_array($typeName, $allowedTypes, true)) {
                continue;
            }

            self::assertContains(
                $typeName,
                $allowedTypes,
                $className . ' observability dependency must use public ports/interfaces only.',
            );
        }
    }

    private static function noopTracer(): TracerPortInterface
    {
        return new class() implements TracerPortInterface {
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                return KernelArtifactObservabilityDoesNotChangeBehaviorTest::span($name);
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                $span = KernelArtifactObservabilityDoesNotChangeBehaviorTest::span($name);

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

    private static function failingTracer(): TracerPortInterface
    {
        return new class() implements TracerPortInterface {
            public function startSpan(string $name, array $attributes = []): SpanInterface
            {
                throw new \RuntimeException('observability-tracer-failure');
            }

            public function inSpan(
                string $name,
                callable $callback,
                array $attributes = [],
            ): mixed {
                throw new \RuntimeException('observability-tracer-failure');
            }

            public function currentSpan(): ?SpanInterface
            {
                throw new \RuntimeException('observability-tracer-failure');
            }
        };
    }

    public static function span(string $name): SpanInterface
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

    private static function noopMeter(): MeterPortInterface
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

    private static function failingMeter(): MeterPortInterface
    {
        return new class() implements MeterPortInterface {
            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
                throw new \RuntimeException('observability-meter-failure');
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
                throw new \RuntimeException('observability-meter-failure');
            }
        };
    }

    private static function noopLogger(): LoggerInterface
    {
        return new NullLogger();
    }

    private static function failingLogger(): LoggerInterface
    {
        return new class() extends AbstractLogger {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                throw new \RuntimeException('observability-logger-failure');
            }
        };
    }

    private static function sourceWithoutComments(string $relativePath): string
    {
        $path = self::packageRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
