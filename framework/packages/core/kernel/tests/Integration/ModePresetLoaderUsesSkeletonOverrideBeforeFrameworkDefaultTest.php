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
use Coretsia\Kernel\Module\FilesystemModePresetLoader;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
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
