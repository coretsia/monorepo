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

use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use PHPUnit\Framework\TestCase;

final class ConcurrentArtifactCompilationPublishesCompleteGenerationTest extends TestCase
{
    private const int PROCESS_COUNT = 8;

    public function testConcurrentCompilersPublishOnlyCompleteImmutableGenerations(): void
    {
        if (!\function_exists('proc_open')) {
            self::markTestSkipped('proc_open() is unavailable in this environment.');
        }

        $root = ArtifactPipelineTestSupport::temporaryRoot('concurrent-artifact-compilation');
        $artifactRoot = $root . '/var/cache/web';

        try {
            ArtifactPipelineTestSupport::writeRootConfig(
                $root,
                ArtifactPipelineTestSupport::defaultConfig(),
            );

            $sourcePaths = self::writeDistinctFingerprintInputs($root);
            $childScript = self::writeChildScript($root);
            $autoloadPath = \dirname(__DIR__, 5) . '/vendor/autoload.php';
            $fixturePath = \dirname(__DIR__) . '/Fixtures/' . 'ContainerDefinitionProviderFixture.php';

            self::assertFileExists($autoloadPath);
            self::assertFileExists($fixturePath);

            $processes = [];

            foreach (
                $sourcePaths as $variant => $sourcePath
            ) {
                $processes[] = self::startCompilerProcess(
                    scriptPath: $childScript,
                    autoloadPath: $autoloadPath,
                    fixturePath: $fixturePath,
                    skeletonRoot: $root,
                    sourcePath: $sourcePath,
                    variant: $variant,
                );
            }

            $publishedGenerationIds = [];

            foreach (
                $processes as $index => $processState
            ) {
                $publishedGenerationIds[] = self::assertCompilerProcessSucceeded(
                    processState: $processState,
                    index: $index,
                );
            }

            \sort($publishedGenerationIds, \SORT_STRING);

            $generationIds = self::finalizedGenerationIds($artifactRoot);

            self::assertCount(
                self::PROCESS_COUNT,
                $generationIds,
            );
            self::assertSame(
                $generationIds,
                $publishedGenerationIds,
            );
            self::assertSame(
                [],
                self::transientFiles($artifactRoot),
            );

            self::assertFileExists($artifactRoot . '/generation.lock');
            self::assertFileDoesNotExist($artifactRoot . '/module-manifest.php');
            self::assertFileDoesNotExist($artifactRoot . '/config.php');
            self::assertFileDoesNotExist($artifactRoot . '/container.php');

            $pathResolver = new ArtifactGenerationPathResolver();
            $validator = self::validator();

            foreach ($generationIds as $generationId) {
                $generation = $pathResolver->generation(
                    artifactRoot: $artifactRoot,
                    generationId: ArtifactGenerationId::fromString($generationId),
                );

                $validator->validate($generation);

                self::assertSame(
                    [
                        'config.php',
                        'container.php',
                        'generation-manifest.php',
                        'module-manifest.php',
                    ],
                    self::generationBasenames($generation),
                );
            }

            $current = self::locator()->locate($artifactRoot);

            self::assertInstanceOf(
                ArtifactGeneration::class,
                $current,
            );
            self::assertContains(
                $current
                    ->generationId()
                    ->value(),
                $generationIds,
                "1",
            );

            $validator->validate($current);

            self::assertSame(
                $current
                    ->generationId()
                    ->value()
                . "\n",
                \file_get_contents(
                    $pathResolver->currentPath($artifactRoot),
                ),
            );
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @return array<int, non-empty-string>
     */
    private static function writeDistinctFingerprintInputs(
        string $root,
    ): array {
        $directory = $root . '/concurrent-inputs';

        self::assertTrue(
            \mkdir(
                $directory,
                0777,
                true,
            ),
        );

        $paths = [];

        for (
            $variant = 0;
            $variant < self::PROCESS_COUNT;
            ++$variant
        ) {
            $path = $directory . '/variant-' . $variant . '.php';
            $bytes = "<?php\n\nreturn ['variant' => " . $variant . "];\n";

            self::assertSame(
                \strlen($bytes),
                \file_put_contents(
                    $path,
                    $bytes,
                ),
            );

            $paths[$variant] = $path;
        }

        return $paths;
    }

    private static function writeChildScript(
        string $root,
    ): string {
        $path = $root . '/concurrent-artifact-compiler-child.php';

        $script = <<<'CHILD'
<?php

declare(strict_types=1);

require $argv[1];
require $argv[2];

$skeletonRoot = $argv[3];
$sourcePath = $argv[4];
$variant = (int)$argv[5];

$frameworkRoot = \dirname(
    $argv[1],
    2,
);

$foundationConfig = require $frameworkRoot
    . '/packages/core/foundation/config/foundation.php';

$kernelProviderConfig = require $frameworkRoot
    . '/packages/core/kernel/config/kernel.php';

$foundationConfig['container']
    ['autowire_concrete'] = true;
$foundationConfig['container']
    ['allow_reflection_for_concrete'] = true;
$foundationConfig['reset']['tag'] =
    \Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET;

$builder =
    new \Coretsia\Foundation\Container\ContainerBuilder(
        config: [
            'foundation' => $foundationConfig,
            'kernel' => $kernelProviderConfig,
        ],
    );

$builder->register(
    new \Coretsia\Foundation\Provider\FoundationServiceProvider(),
    new \Coretsia\Kernel\Provider\KernelServiceProvider(),
);

$container = $builder->build();

$compiler = $container->get(
    \Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler::class,
);

if (
    !$compiler instanceof
        \Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler
) {
    throw new \RuntimeException(
        'artifact-compiler-service-invalid',
    );
}

$moduleId =
    \Coretsia\Contracts\Module\ModuleId::fromString(
        'core.fixture',
    );
$composerName =
    'coretsia/core-kernel-test-fixture';

$moduleResolution =
    new \Coretsia\Kernel\Module\ModuleResolution(
        manifest:
            new \Coretsia\Contracts\Module\ModuleManifest(
                [
                    new \Coretsia\Contracts\Module\ModuleDescriptor(
                        id: $moduleId,
                        composerName:
                            $composerName,
                        packageKind:
                            'runtime',
                        moduleClass:
                            null,
                        capabilities:
                            [],
                        metadata: [
                            'providers' => [
                                \Coretsia\Kernel\Tests\Fixtures\ContainerDefinitionProviderFixture::class,
                            ],
                        ],
                    ),
                ],
            ),
        plan:
            new \Coretsia\Kernel\Module\ModulePlan(
                app: 'web',
                preset: 'default',
                enabled: [
                    $moduleId,
                ],
                disabled: [],
                optionalMissing: [],
                topologicalOrder: [
                    $moduleId,
                ],
                modules: [
                    new \Coretsia\Kernel\Module\ModulePlanEntry(
                        moduleId:
                            $moduleId,
                        composerName:
                            $composerName,
                    ),
                ],
                warnings: [],
            ),
    );

$env =
    new class() implements
        \Coretsia\Contracts\Env\EnvRepositoryInterface
    {
        public function has(
            string $name,
        ): bool {
            return false;
        }

        public function get(
            string $name,
        ): \Coretsia\Contracts\Env\EnvValue {
            throw new \LogicException(
                'concurrent-compiler-env-read-forbidden',
            );
        }

        public function all(): array
        {
            return [];
        }

        public function sourceOf(
            string $name,
        ): ?\Coretsia\Contracts\Config\ConfigValueSource {
            return null;
        }
    };

$bootstrapConfig =
    new \Coretsia\Kernel\Boot\BootstrapConfig(
        appEnv: 'prod',
        preset: 'default',
        debug: false,
        artifactsCacheDir: 'var/cache',
        envSourcePolicy:
            \Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy::StrictDotenv,
        appTarget:
            \Coretsia\Kernel\Boot\AppTarget::Web,
        skeletonRoot:
            $skeletonRoot,
    );

try {
    $result = $compiler->compile(
        bootstrapConfig:
            $bootstrapConfig,
        moduleResolution:
            $moduleResolution,
        env: $env,
        kernelConfig: [
            'env' => [
                'dotenv' => [
                    'files' => [],
                ],
            ],
            'fingerprint' => [
                'skeleton_ignore_prefixes' => [
                    'var/maintenance',
                ],
            ],
        ],
        packageDefaultSources: [],
        packageRuleSources: [],
        splitRoots: [],
        explicitRuleSources: [],
        explicitEnvOverlayMappings: [],
        modePresetSourceCandidates: [
            [
                'root' => 'kernel',
                'packageId' =>
                    'coretsia/core-kernel-test-fixture',
                'moduleId' =>
                    'core.fixture',
                'path' =>
                    'concurrent-inputs/variant-'
                    . $variant
                    . '.php',
                'filesystemPath' =>
                    $sourcePath,
                'sourceId' =>
                    'test.concurrent.'
                    . $variant,
                'precedence' =>
                    $variant,
            ],
        ],
    );

    echo \json_encode(
        [
            'status' => 'ok',
            'result' => $result,
        ],
        \JSON_THROW_ON_ERROR,
    );
} catch (\Throwable $exception) {
    echo \json_encode(
        [
            'status' => 'failed',
            'type' => $exception::class,
            'message' =>
                $exception->getMessage(),
        ],
        \JSON_THROW_ON_ERROR,
    );

    exit(1);
}
CHILD;

        self::assertSame(
            \strlen($script),
            \file_put_contents(
                $path,
                $script,
            ),
        );

        return $path;
    }

    /**
     * @return array{
     *     process: resource,
     *     stdout: resource,
     *     stderr: resource
     * }
     */
    private static function startCompilerProcess(
        string $scriptPath,
        string $autoloadPath,
        string $fixturePath,
        string $skeletonRoot,
        string $sourcePath,
        int $variant,
    ): array {
        $process = \proc_open(
            [
                \PHP_BINARY,
                $scriptPath,
                $autoloadPath,
                $fixturePath,
                $skeletonRoot,
                $sourcePath,
                (string)$variant,
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

        return [
            'process' => $process,
            'stdout' => $pipes[1],
            'stderr' => $pipes[2],
        ];
    }

    /**
     * @param array{
     *     process: resource,
     *     stdout: resource,
     *     stderr: resource
     * } $processState
     *
     * @return non-empty-string
     */
    private static function assertCompilerProcessSucceeded(
        array $processState,
        int $index,
    ): string {
        $stdout = \stream_get_contents($processState['stdout']);
        $stderr = \stream_get_contents($processState['stderr']);

        \fclose($processState['stdout']);
        \fclose($processState['stderr']);

        $exitCode = \proc_close($processState['process']);

        self::assertIsString($stdout);
        self::assertIsString($stderr);
        self::assertSame(
            0,
            $exitCode,
            'Compiler process '
            . $index
            . ' failed: '
            . $stderr
            . $stdout,
        );
        self::assertSame('', $stderr);

        $decoded = \json_decode(
            $stdout,
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);
        self::assertSame(
            'ok',
            $decoded['status'] ?? null,
        );
        $result = $decoded['result'] ?? null;

        self::assertIsArray($result);

        return self::assertCompileResult($result);
    }

    /**
     * @param array<string,mixed> $result
     *
     * @return non-empty-string
     */
    private static function assertCompileResult(array $result): string
    {
        self::assertSame(
            [
                'schemaVersion',
                'generationId',
                'artifacts',
            ],
            \array_keys($result),
        );
        self::assertSame(1, $result['schemaVersion']);

        $generationId = $result['generationId'] ?? null;

        self::assertIsString($generationId);
        self::assertMatchesRegularExpression(
            '/\A[a-f0-9]{64}\z/',
            $generationId,
        );
        self::assertSame(
            [
                [
                    'identity' => 'module-manifest@1',
                    'basename' => 'module-manifest.php',
                ],
                [
                    'identity' => 'config@1',
                    'basename' => 'config.php',
                ],
                [
                    'identity' => 'container@1',
                    'basename' => 'container.php',
                ],
                [
                    'identity' => 'artifact-generation@1',
                    'basename' => 'generation-manifest.php',
                ],
            ],
            $result['artifacts'],
        );

        /** @var non-empty-string $generationId */
        return $generationId;
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

    /**
     * @return list<string>
     */
    private static function finalizedGenerationIds(
        string $artifactRoot,
    ): array {
        $entries = \scandir($artifactRoot . '/generations');

        self::assertIsArray($entries);

        $generationIds = \array_values(
            \array_filter(
                $entries,
                static fn (
                    string $entry,
                ): bool => \preg_match(
                    '/\A[a-f0-9]{64}\z/',
                    $entry,
                ) === 1,
            ),
        );

        \sort(
            $generationIds,
            \SORT_STRING,
        );

        return $generationIds;
    }

    /**
     * @return list<string>
     */
    private static function generationBasenames(
        ArtifactGeneration $generation,
    ): array {
        $entries = \scandir(
            $generation->generationDirectory(),
        );

        self::assertIsArray($entries);

        $basenames = \array_values(
            \array_filter(
                $entries,
                static fn (
                    string $entry,
                ): bool => $entry !== '.'
                    && $entry !== '..',
            ),
        );

        \sort(
            $basenames,
            \SORT_STRING,
        );

        return $basenames;
    }

    /**
     * @return list<string>
     */
    private static function transientFiles(
        string $artifactRoot,
    ): array {
        $patterns = [
            $artifactRoot . '/generations/.staging-*',
            $artifactRoot . '/.current-*',
            $artifactRoot . '/.current-backup-*',
        ];
        $files = [];

        foreach ($patterns as $pattern) {
            $matches = \glob($pattern);

            if ($matches !== false) {
                $files = [
                    ...$files,
                    ...$matches,
                ];
            }
        }

        \sort($files, \SORT_STRING);

        return \array_values($files);
    }
}
