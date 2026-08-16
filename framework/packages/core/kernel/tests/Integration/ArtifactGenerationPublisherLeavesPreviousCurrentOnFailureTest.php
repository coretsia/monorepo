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

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestBuilder;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactPublicationSet;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Module\ModulePlan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArtifactGenerationPublisherLeavesPreviousCurrentOnFailureTest extends TestCase
{
    #[DataProvider('failureScenarioProvider')]
    public function testPublicationFailureLeavesPreviousCurrentSelectedAndValid(
        string $scenario,
        string $expectedReason,
    ): void {
        if (!\function_exists('proc_open')) {
            self::markTestSkipped('proc_open() is unavailable in this environment.');
        }

        $root = ArtifactPipelineTestSupport::temporaryRoot(
            'artifact-generation-failure-' . $scenario,
        );
        $artifactRoot = $root . '/var/cache/web';

        try {
            \mkdir($artifactRoot, 0777, true);

            $previousPublicationSet = self::publicationSet(
                fingerprint: self::fingerprint('a'),
                configValue: 'previous-value',
            );
            $previousGeneration = self::publisher($this)->publish(
                artifactRoot: $artifactRoot,
                publicationSet: $previousPublicationSet,
            );

            $pathResolver = new ArtifactGenerationPathResolver();
            $currentPath = $pathResolver->currentPath($artifactRoot);
            $currentBefore = \file_get_contents($currentPath);
            $previousBytes = self::generationBytes($previousGeneration);

            self::assertIsString($currentBefore);
            self::assertSame(
                self::fingerprint('a') . "\n",
                $currentBefore,
            );

            $childResult = self::runFailureChild(
                root: $root,
                artifactRoot: $artifactRoot,
                scenario: $scenario,
                fingerprint: self::fingerprint('b'),
                configValue: 'next-value',
            );

            self::assertSame('failed', $childResult['status'] ?? null);
            self::assertSame(
                $expectedReason,
                $childResult['reason'] ?? null,
            );
            self::assertSame(
                ArtifactGenerationPublishException::ERROR_CODE
                . ': '
                . $expectedReason,
                $childResult['message'] ?? null,
            );

            $encodedChildResult = \json_encode(
                $childResult,
                \JSON_THROW_ON_ERROR,
            );

            self::assertStringNotContainsString(
                $artifactRoot,
                $encodedChildResult,
            );
            self::assertStringNotContainsString(
                self::fingerprint('a'),
                $encodedChildResult,
            );
            self::assertStringNotContainsString(
                self::fingerprint('b'),
                $encodedChildResult,
            );

            self::assertSame(
                $currentBefore,
                \file_get_contents($currentPath),
                'A failed publication must not replace current.',
            );

            $located = self::locator()->locate($artifactRoot);

            self::assertInstanceOf(ArtifactGeneration::class, $located);
            self::assertTrue(
                $located->generationId()->equals(
                    $previousGeneration->generationId(),
                ),
            );

            self::validator()->validate($located);
            self::assertSame(
                $previousBytes,
                self::generationBytes($located),
            );

            self::assertNoTransientPublicationState($artifactRoot);

            foreach (
                self::finalizedGenerations($artifactRoot) as $generation
            ) {
                self::validator()->validate($generation);
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @return iterable<string, array{0:string, 1:string}>
     */
    public static function failureScenarioProvider(): iterable
    {
        yield 'first-artifact-write' => [
            'first-artifact-write',
            ArtifactGenerationPublishException::REASON_WRITE_FAILED,
        ];

        yield 'second-artifact-write' => [
            'second-artifact-write',
            ArtifactGenerationPublishException::REASON_WRITE_FAILED,
        ];

        yield 'third-artifact-write' => [
            'third-artifact-write',
            ArtifactGenerationPublishException::REASON_WRITE_FAILED,
        ];

        yield 'generation-manifest-write' => [
            'generation-manifest-write',
            ArtifactGenerationPublishException::REASON_WRITE_FAILED,
        ];

        yield 'final-directory-rename' => [
            'final-directory-rename',
            ArtifactGenerationPublishException::REASON_GENERATION_CONFLICT,
        ];

        yield 'current-pointer-write' => [
            'current-pointer-write',
            ArtifactGenerationPublishException::REASON_POINTER_WRITE_FAILED,
        ];

        yield 'current-pointer-switch' => [
            'current-pointer-switch',
            ArtifactGenerationPublishException::REASON_POINTER_SWITCH_FAILED,
        ];

        yield 'lock-acquisition' => [
            'lock-acquisition',
            ArtifactGenerationPublishException::REASON_LOCK_FAILED,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function runFailureChild(
        string $root,
        string $artifactRoot,
        string $scenario,
        string $fingerprint,
        string $configValue,
    ): array {
        $autoloadPath = \dirname(__DIR__, 5) . '/vendor/autoload.php';

        self::assertFileExists($autoloadPath);

        $scriptPath = $root . '/artifact-generation-failure-child.php';
        $script = <<<'CHILD'
<?php

declare(strict_types=1);

require $argv[1];

$scenario = $argv[2];
$artifactRoot = $argv[3];
$fingerprint = $argv[4];
$configValue = $argv[5];

eval(<<<'FAKE_WRITER'
namespace Coretsia\Kernel\Artifacts;

final class ArtifactWriter
{
    private int $durableWriteCount = 0;

    public function __construct(
        private readonly string $scenario,
    ) {
    }

    public function writeDurableFile(
        string $targetPath,
        string $bytes,
    ): int {
        ++$this->durableWriteCount;

        $failureStage = match ($this->durableWriteCount) {
            1 => 'first-artifact-write',
            2 => 'second-artifact-write',
            3 => 'third-artifact-write',
            4 => 'generation-manifest-write',
            default => null,
        };

        if ($this->scenario === $failureStage) {
            throw \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::withReason(
                \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::REASON_DURABLE_FILE_WRITE_FAILED,
            );
        }

        return self::writeExact($targetPath, $bytes);
    }

    public function writeDurableTemporaryFile(
        string $targetPath,
        string $temporaryBasename,
        string $bytes,
    ): array {
        if ($this->scenario === 'current-pointer-write') {
            throw \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::withReason(
                \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::REASON_DURABLE_FILE_WRITE_FAILED,
            );
        }

        $temporaryPath = \dirname($targetPath)
            . '/'
            . $temporaryBasename;
        $byteCount = self::writeExact(
            $temporaryPath,
            $bytes,
        );

        return [
            'path' => $temporaryPath,
            'bytes' => $byteCount,
        ];
    }

    public function replaceDurableFile(
        string $temporaryPath,
        string $targetPath,
        string $backupBasename,
    ): void {
        if ($this->scenario === 'current-pointer-switch') {
            throw \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::withReason(
                \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::REASON_TARGET_REPLACE_FAILED,
            );
        }

        if (!@\rename($temporaryPath, $targetPath)) {
            throw \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::withReason(
                \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::REASON_TARGET_REPLACE_FAILED,
            );
        }
    }

    private static function writeExact(
        string $targetPath,
        string $bytes,
    ): int {
        $written = @\file_put_contents(
            $targetPath,
            $bytes,
            \LOCK_EX,
        );

        if (
            !\is_int($written)
            || $written !== \strlen($bytes)
        ) {
            throw \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::withReason(
                \Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException::REASON_DURABLE_FILE_WRITE_FAILED,
            );
        }

        return $written;
    }
}
FAKE_WRITER);

if ($scenario === 'lock-acquisition') {
    eval(<<<'FAKE_LOCK'
namespace Coretsia\Kernel\Artifacts\Generation;

final readonly class ArtifactGenerationLock
{
    public function __construct(
        private ?ArtifactGenerationPathResolver $pathResolver = null,
    ) {
    }

    public function shared(
        string $artifactRoot,
        \Closure $operation,
    ): mixed {
        return $operation();
    }

    public function exclusive(
        string $artifactRoot,
        \Closure $operation,
    ): mixed {
        throw \Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException::withReason(
            \Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException::REASON_LOCK_FAILED,
        );
    }
}
FAKE_LOCK);
}

if ($scenario === 'final-directory-rename') {
    eval(<<<'FAKE_PATH_RESOLVER'
namespace Coretsia\Kernel\Artifacts\Generation;

final readonly class ArtifactGenerationPathResolver
{
    public const string GENERATIONS_DIRECTORY = 'generations';
    public const string CURRENT_BASENAME = 'current';
    public const string GENERATION_LOCK_BASENAME = 'generation.lock';

    public function generationsDirectory(
        string $artifactRoot,
    ): string {
        return \rtrim($artifactRoot, '/\\')
            . '/generations';
    }

    public function newStagingDirectory(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): string {
        return $this->generationsDirectory($artifactRoot)
            . '/.staging-'
            . $generationId->value()
            . '-'
            . \bin2hex(\random_bytes(16));
    }

    public function generation(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): ArtifactGeneration {
        return new ArtifactGeneration(
            generationId: $generationId,
            generationDirectory: \rtrim(
                $artifactRoot,
                '/\\',
            )
                . '/rename-failure/generations/'
                . $generationId->value(),
        );
    }

    public function currentPath(
        string $artifactRoot,
    ): string {
        return \rtrim($artifactRoot, '/\\')
            . '/current';
    }

    public function generationLockPath(
        string $artifactRoot,
    ): string {
        return \rtrim($artifactRoot, '/\\')
            . '/generation.lock';
    }
}
FAKE_PATH_RESOLVER);
}

$normalizer = new \Coretsia\Kernel\Artifacts\PayloadNormalizer();
$envelopeFactory = new \Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory($normalizer);
$dumper = new \Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper($normalizer);
$modulePlan = new \Coretsia\Kernel\Module\ModulePlan(
    app: 'web',
    preset: 'default',
    enabled: [],
    disabled: [],
    optionalMissing: [],
    topologicalOrder: [],
    modules: [],
    warnings: [],
);
$compiledConfig = [
    'config' => [
        'custom' => [
            'feature' => [
                'value' => $configValue,
            ],
        ],
    ],
    'sources' => [],
    'owners' => [],
    'envOverlayMappings' => [],
    'configSourceFiles' => [],
    'validation' => \Coretsia\Contracts\Config\ConfigValidationResult::success(),
    'validationSubjects' => [
        'unvalidated' => [],
        'validated' => [],
    ],
];
$moduleManifestEnvelope = new \Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder($envelopeFactory)->build(
    modulePlan: $modulePlan,
    fingerprint: $fingerprint,
);
$configEnvelope = new \Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder($envelopeFactory)->build(
    compiledConfig: $compiledConfig,
    fingerprint: $fingerprint,
);
$containerEnvelope = new \Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder($envelopeFactory)->build(
    graph: \Coretsia\Kernel\Container\Definition\DefinitionGraph::empty(),
    fingerprint: $fingerprint,
);
$publicationSet = new \Coretsia\Kernel\Artifacts\Generation\ArtifactPublicationSet(
        moduleManifestEnvelope: $moduleManifestEnvelope,
        moduleManifestBytes: $dumper->dumpEnvelope($moduleManifestEnvelope),
        configEnvelope: $configEnvelope,
        configBytes: $dumper->dumpEnvelope($configEnvelope),
        containerEnvelope: $containerEnvelope,
        containerBytes: $dumper->dumpEnvelope($containerEnvelope),
    );
$pathResolver = new \Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver();
$schemaValidator = new \Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator();
$validator = new \Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator(
        artifactReader: new \Coretsia\Kernel\Artifacts\Php\PhpArtifactReader(),
        schemaValidator: $schemaValidator,
        manifestValidator: new \Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator($schemaValidator),
    );
$publisher = new \Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher(
        artifactWriter: new \Coretsia\Kernel\Artifacts\ArtifactWriter($scenario),
        phpArrayDumper: $dumper,
        manifestBuilder: new \Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestBuilder($envelopeFactory),
        validator: $validator,
        lock: new \Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock($pathResolver),
        pathResolver: $pathResolver,
    );

try {
    $publisher->publish(
        artifactRoot: $artifactRoot,
        publicationSet: $publicationSet,
    );

    echo \json_encode(
        [
            'status' => 'unexpected-success',
        ],
        \JSON_THROW_ON_ERROR,
    );

    exit(2);
} catch (\Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException $exception) {
    echo \json_encode(
        [
            'status' => 'failed',
            'reason' => $exception->reason(),
            'message' => $exception->getMessage(),
        ],
        \JSON_THROW_ON_ERROR,
    );

    exit(0);
} catch (\Throwable $exception) {
    echo \json_encode(
        [
            'status' => 'unexpected-exception',
            'type' => $exception::class,
            'message' => $exception->getMessage(),
        ],
        \JSON_THROW_ON_ERROR,
    );

    exit(3);
}
CHILD;

        self::assertIsInt(
            \file_put_contents(
                $scriptPath,
                $script,
            ),
        );

        $process = \proc_open(
            [
                \PHP_BINARY,
                $scriptPath,
                $autoloadPath,
                $scenario,
                $artifactRoot,
                $fingerprint,
                $configValue,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        self::assertIsResource($process);
        self::assertIsArray($pipes);

        \fclose($pipes[0]);

        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exitCode = \proc_close($process);

        self::assertSame(
            0,
            $exitCode,
            'Failure child exited unexpectedly: '
            . $stderr
            . $stdout,
        );
        self::assertSame('', $stderr);
        self::assertIsString($stdout);

        $decoded = \json_decode(
            $stdout,
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        return $decoded;
    }

    private static function publisher(
        TestCase $testCase,
    ): ArtifactGenerationPublisher {
        $normalizer = new PayloadNormalizer();
        $envelopeFactory = new ArtifactEnvelopeFactory($normalizer);
        $dumper = new StablePhpArrayDumper($normalizer);
        $pathResolver = new ArtifactGenerationPathResolver();

        return new ArtifactGenerationPublisher(
            artifactWriter: ArtifactPipelineTestSupport::artifactWriter($testCase),
            phpArrayDumper: $dumper,
            manifestBuilder: new ArtifactGenerationManifestBuilder($envelopeFactory),
            validator: self::validator(),
            lock: new ArtifactGenerationLock($pathResolver),
            pathResolver: $pathResolver,
        );
    }

    private static function locator(): ArtifactGenerationLocator
    {
        $pathResolver = new ArtifactGenerationPathResolver();

        return new ArtifactGenerationLocator(
            lock: new ArtifactGenerationLock($pathResolver),
            pathResolver: $pathResolver,
            validator: self::validator(),
        );
    }

    private static function validator(): ArtifactGenerationValidator
    {
        $schemaValidator = new ArtifactSchemaValidator();

        return new ArtifactGenerationValidator(
            artifactReader: new PhpArtifactReader(),
            schemaValidator: $schemaValidator,
            manifestValidator: new ArtifactGenerationManifestValidator($schemaValidator),
        );
    }

    private static function publicationSet(
        string $fingerprint,
        string $configValue,
    ): ArtifactPublicationSet {
        $normalizer = new PayloadNormalizer();
        $envelopeFactory = new ArtifactEnvelopeFactory($normalizer);
        $dumper = new StablePhpArrayDumper($normalizer);

        $moduleManifestEnvelope = new ModuleManifestBuilder($envelopeFactory)->build(
            modulePlan: self::modulePlan(),
            fingerprint: $fingerprint,
        );

        $configEnvelope = new CompiledConfigBuilder($envelopeFactory)->build(
            compiledConfig: self::compiledConfig($configValue),
            fingerprint: $fingerprint,
        );

        $containerEnvelope = new CompiledContainerBuilder($envelopeFactory)->build(
            graph: DefinitionGraph::empty(),
            fingerprint: $fingerprint,
        );

        return new ArtifactPublicationSet(
            moduleManifestEnvelope: $moduleManifestEnvelope,
            moduleManifestBytes: $dumper->dumpEnvelope($moduleManifestEnvelope),
            configEnvelope: $configEnvelope,
            configBytes: $dumper->dumpEnvelope($configEnvelope),
            containerEnvelope: $containerEnvelope,
            containerBytes: $dumper->dumpEnvelope($containerEnvelope),
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
     * @return array<string, mixed>
     */
    private static function compiledConfig(
        string $configValue,
    ): array {
        return [
            'config' => [
                'custom' => [
                    'feature' => [
                        'value' => $configValue,
                    ],
                ],
            ],
            'sources' => [],
            'owners' => [],
            'envOverlayMappings' => [],
            'configSourceFiles' => [],
            'validation' => ConfigValidationResult::success(),
            'validationSubjects' => [
                'unvalidated' => [],
                'validated' => [],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function generationBytes(
        ArtifactGeneration $generation,
    ): array {
        $paths = [
            ArtifactGeneration::CONFIG_BASENAME => $generation->configPath(),
            ArtifactGeneration::CONTAINER_BASENAME => $generation->containerPath(),
            ArtifactGeneration::GENERATION_MANIFEST_BASENAME => $generation->generationManifestPath(),
            ArtifactGeneration::MODULE_MANIFEST_BASENAME => $generation->moduleManifestPath(),
        ];

        $bytes = [];

        foreach ($paths as $basename => $path) {
            $content = \file_get_contents($path);

            self::assertIsString($content);

            $bytes[$basename] = $content;
        }

        return $bytes;
    }

    /**
     * @return list<ArtifactGeneration>
     */
    private static function finalizedGenerations(
        string $artifactRoot,
    ): array {
        $pathResolver = new ArtifactGenerationPathResolver();
        $directory = $pathResolver->generationsDirectory($artifactRoot);
        $entries = \scandir($directory);

        self::assertIsArray($entries);

        $generations = [];

        foreach ($entries as $entry) {
            if (
                \preg_match(
                    '/\A[a-f0-9]{64}\z/',
                    $entry,
                ) !== 1
            ) {
                continue;
            }

            $generations[] =
                $pathResolver->generation(
                    artifactRoot: $artifactRoot,
                    generationId: ArtifactGenerationId::fromString($entry),
                );
        }

        return $generations;
    }

    private static function assertNoTransientPublicationState(
        string $artifactRoot,
    ): void {
        $staging = \glob($artifactRoot . '/generations/.staging-*');
        $currentTemporary = \glob($artifactRoot . '/.current-*');
        $currentBackups = \glob($artifactRoot . '/.current-backup-*');

        self::assertSame(
            [],
            $staging === false ? [] : $staging,
        );
        self::assertSame(
            [],
            $currentTemporary === false
                ? []
                : $currentTemporary,
        );
        self::assertSame(
            [],
            $currentBackups === false
                ? []
                : $currentBackups,
        );
    }

    private static function fingerprint(
        string $character,
    ): string {
        return \str_repeat(
            $character,
            64,
        );
    }
}
