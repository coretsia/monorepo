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

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\FilesystemModePresetLoader;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModePresetLoaderUsesSkeletonOverrideBeforeFrameworkDefaultTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = self::createTempDirectory();
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->tempRoot);
    }

    public function testSkeletonOverrideWinsBeforeFrameworkDefault(): void
    {
        $frameworkDefaultsPath = $this->tempRoot . '/package/resources/modes';
        $skeletonOverridesPath = $this->tempRoot . '/skeleton/config/modes';

        self::writePresetFile(
            directory: $frameworkDefaultsPath,
            name: 'micro',
            payload: [
                'schemaVersion' => 1,
                'name' => 'micro',
                'description' => 'Framework default micro mode.',
                'required' => [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                ],
                'optional' => [
                    'platform.logging',
                    'platform.metrics',
                ],
                'disabled' => [],
                'featureBundles' => [
                    'observability' => 'framework-default',
                ],
                'metadata' => [
                    'source' => 'framework-default',
                ],
            ],
        );

        self::writePresetFile(
            directory: $skeletonOverridesPath,
            name: 'micro',
            payload: [
                'schemaVersion' => 1,
                'name' => 'micro',
                'description' => 'Skeleton override micro mode.',
                'required' => [
                    'core.kernel',
                ],
                'optional' => [
                    'platform.http',
                ],
                'disabled' => [
                    'platform.logging',
                ],
                'featureBundles' => [
                    'observability' => 'skeleton-override',
                ],
                'metadata' => [
                    'source' => 'skeleton-override',
                ],
            ],
        );

        $loader = new FilesystemModePresetLoader(
            frameworkDefaultsPath: $frameworkDefaultsPath,
            skeletonOverridesPath: $skeletonOverridesPath,
            schemaValidator: new ModePresetSchemaValidator(),
        );

        $preset = $loader->load('micro');

        self::assertSame('micro', $preset->name());
        self::assertSame('Skeleton override micro mode.', $preset->description());

        self::assertSame(
            [
                'core.kernel',
            ],
            self::moduleIdValues($preset->required()),
        );

        self::assertSame(
            [
                'platform.http',
            ],
            self::moduleIdValues($preset->optional()),
        );

        self::assertSame(
            [
                'platform.logging',
            ],
            self::moduleIdValues($preset->disabled()),
        );

        self::assertSame(
            [
                'observability' => 'skeleton-override',
            ],
            $preset->featureBundles(),
        );

        self::assertSame(
            [
                'source' => 'skeleton-override',
            ],
            $preset->metadata(),
        );

        self::assertSame(
            [
                'micro',
            ],
            $loader->listNames(),
        );
    }

    public function testFactoryReturnsSkeletonAndFrameworkFingerprintCandidates(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        \mkdir($packageRoot, 0777, true);
        \mkdir($skeletonRoot, 0777, true);

        $factory = self::factory($packageRoot);
        $candidates = $factory->sourceCandidatesFor(
            self::bootstrapConfig($skeletonRoot, 'micro'),
        );

        self::assertSame(
            [
                [
                    'path' => 'config/modes/micro.php',
                    'filesystemPath' => $skeletonRoot
                        . \DIRECTORY_SEPARATOR
                        . 'config'
                        . \DIRECTORY_SEPARATOR
                        . 'modes'
                        . \DIRECTORY_SEPARATOR
                        . 'micro.php',
                    'sourceId' => 'skeleton:config/modes/micro.php',
                    'precedence' => 20,
                ],
                [
                    'path' => 'resources/modes/micro.php',
                    'filesystemPath' => $packageRoot
                        . \DIRECTORY_SEPARATOR
                        . 'resources'
                        . \DIRECTORY_SEPARATOR
                        . 'modes'
                        . \DIRECTORY_SEPARATOR
                        . 'micro.php',
                    'sourceId' => 'core/kernel:resources/modes/micro.php',
                    'precedence' => 10,
                ],
            ],
            $candidates,
        );
    }

    public function testFactoryReturnsMissingSkeletonOverrideCandidate(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        \mkdir($packageRoot, 0777, true);
        \mkdir($skeletonRoot, 0777, true);

        $candidates = self::factory($packageRoot)->sourceCandidatesFor(
            self::bootstrapConfig($skeletonRoot, 'micro'),
        );

        self::assertCount(2, $candidates);
        self::assertSame('skeleton:config/modes/micro.php', $candidates[0]['sourceId']);
        self::assertFileDoesNotExist($candidates[0]['filesystemPath']);
    }

    public function testFactoryCandidateConstructionDoesNotChangeLoaderPrecedence(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        $frameworkDefaultsPath = $packageRoot . '/resources/modes';
        $skeletonOverridesPath = $skeletonRoot . '/config/modes';

        self::writePresetFile(
            directory: $frameworkDefaultsPath,
            name: 'micro',
            payload: self::presetPayload('framework-default'),
        );
        self::writePresetFile(
            directory: $skeletonOverridesPath,
            name: 'micro',
            payload: self::presetPayload('skeleton-override'),
        );

        $factory = self::factory($packageRoot);
        $bootstrapConfig = self::bootstrapConfig($skeletonRoot, 'micro');

        self::assertCount(2, $factory->sourceCandidatesFor($bootstrapConfig));

        $preset = $factory->createFor($bootstrapConfig)->load('micro');

        self::assertSame('skeleton-override', $preset->metadata()['source'] ?? null);
    }

    #[DataProvider('invalidPresetNames')]
    public function testFactoryRejectsInvalidPresetNamesWhenBuildingFingerprintCandidates(
        string $preset,
    ): void {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        \mkdir($packageRoot, 0777, true);
        \mkdir($skeletonRoot, 0777, true);

        try {
            self::factory($packageRoot)->sourceCandidatesFor(
                self::bootstrapConfig($skeletonRoot, $preset),
            );

            self::fail('Expected invalid mode preset name to fail.');
        } catch (ModePresetNotFoundException $exception) {
            self::assertSame(
                ModePresetNotFoundException::REASON_PRESET_NAME_INVALID,
                $exception->reason(),
            );
            self::assertSame(['preset' => 'invalid'], $exception->context());
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidPresetNames(): iterable
    {
        yield 'leading digit' => ['1micro'];
        yield 'leading hyphen' => ['-micro'];
        yield 'uppercase' => ['Micro'];
        yield 'underscore' => ['micro_mode'];
        yield 'traversal' => ['../micro'];
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdValues(array $moduleIds): array
    {
        return \array_map(
            static fn (ModuleId $moduleId): string => $moduleId->value(),
            $moduleIds,
        );
    }

    private static function factory(string $packageRoot): ModePresetLoaderFactory
    {
        return new ModePresetLoaderFactory(
            packageRoot: $packageRoot,
            modesConfig: [
                'schema_version' => 1,
                'defaults_path' => 'resources/modes',
                'overrides_path' => 'config/modes',
            ],
            schemaValidator: new ModePresetSchemaValidator(),
        );
    }

    private static function bootstrapConfig(string $skeletonRoot, string $preset): BootstrapConfig
    {
        return new BootstrapConfig(
            appEnv: 'local',
            preset: $preset,
            debug: false,
            artifactsCacheDir: 'var/cache',
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: $skeletonRoot,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function presetPayload(string $source): array
    {
        return [
            'schemaVersion' => 1,
            'name' => 'micro',
            'description' => 'Factory precedence fixture.',
            'required' => [
                'core.kernel',
            ],
            'optional' => [],
            'disabled' => [],
            'featureBundles' => [],
            'metadata' => [
                'source' => $source,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function writePresetFile(string $directory, string $name, array $payload): void
    {
        if (!\is_dir($directory) && !\mkdir($directory, 0777, true) && !\is_dir($directory)) {
            throw new \RuntimeException('test-directory-create-failed');
        }

        $file = $directory . '/' . $name . '.php';
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($payload, true) . ";\n";

        if (\file_put_contents($file, $contents) === false) {
            throw new \RuntimeException('test-preset-write-failed');
        }
    }

    private static function createTempDirectory(): string
    {
        $directory = \sys_get_temp_dir()
            . '/coretsia-mode-preset-loader-'
            . \str_replace('\\', '_', self::class)
            . '-'
            . \bin2hex(\random_bytes(8));

        if (!\mkdir($directory, 0777, true) && !\is_dir($directory)) {
            throw new \RuntimeException('test-temp-directory-create-failed');
        }

        return $directory;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!\is_dir($directory)) {
            return;
        }

        $entries = \scandir($directory);

        if (!\is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (\is_dir($path)) {
                self::removeDirectory($path);

                continue;
            }

            @\unlink($path);
        }

        @\rmdir($directory);
    }
}
