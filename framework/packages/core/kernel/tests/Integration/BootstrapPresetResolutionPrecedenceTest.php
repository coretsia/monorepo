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

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use PHPUnit\Framework\TestCase;

final class BootstrapPresetResolutionPrecedenceTest extends TestCase
{
    private static int $sequence = 0;

    public function testExplicitBootstrapInputPresetWinsOverPerAppAndGlobalPresetOverrides(): void
    {
        $skeletonRoot = self::createSkeletonRoot('explicit-preset-wins');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'preset' => 'express',
                'presets' => [
                    'api' => 'micro',
                    'web' => 'hybrid',
                ],
            ]);

            $config = self::resolver()->resolve(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Api,
                    preset: 'enterprise',
                ),
                self::kernelConfig(),
            );

            self::assertSame('enterprise', $config->preset());
            self::assertSame(AppTarget::Api, $config->appTarget());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testPerAppPresetOverrideWinsOverGlobalPresetOverride(): void
    {
        $skeletonRoot = self::createSkeletonRoot('per-app-wins');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'preset' => 'enterprise',
                'presets' => [
                    'api' => 'micro',
                    'web' => 'express',
                ],
            ]);

            $config = self::resolver()->resolve(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Api,
                ),
                self::kernelConfig(),
            );

            self::assertSame('micro', $config->preset());
            self::assertSame(AppTarget::Api, $config->appTarget());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testGlobalPresetOverrideIsUsedWhenPresetsDoesNotContainSelectedAppTarget(): void
    {
        $skeletonRoot = self::createSkeletonRoot('global-used-when-app-missing');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'preset' => 'hybrid',
                'presets' => [
                    'web' => 'express',
                    'worker' => 'enterprise',
                ],
            ]);

            $config = self::resolver()->resolve(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Api,
                ),
                self::kernelConfig(),
            );

            self::assertSame('hybrid', $config->preset());
            self::assertSame(AppTarget::Api, $config->appTarget());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testKernelDefaultPresetIsUsedWhenNoExplicitOrAppPhpPresetExists(): void
    {
        $skeletonRoot = self::createSkeletonRoot('default-preset');

        try {
            $config = self::resolver()->resolve(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Console,
                ),
                self::kernelConfig(),
            );

            self::assertSame('micro', $config->preset());
            self::assertSame(AppTarget::Console, $config->appTarget());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testEmptyPresetsMapBehavesAsAbsentAndFallsBackToGlobalPreset(): void
    {
        $skeletonRoot = self::createSkeletonRoot('empty-presets-global');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'preset' => 'express',
                'presets' => [],
            ]);

            $config = self::resolver()->resolve(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Worker,
                ),
                self::kernelConfig(),
            );

            self::assertSame('express', $config->preset());
            self::assertSame(AppTarget::Worker, $config->appTarget());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testEmptyPresetsMapBehavesAsAbsentAndFallsBackToKernelDefaultPreset(): void
    {
        $skeletonRoot = self::createSkeletonRoot('empty-presets-default');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'presets' => [],
            ]);

            $config = self::resolver()->resolve(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Web,
                ),
                self::kernelConfig(),
            );

            self::assertSame('micro', $config->preset());
            self::assertSame(AppTarget::Web, $config->appTarget());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testPresetsDoesNotSelectOrModifyAppTarget(): void
    {
        $skeletonRoot = self::createSkeletonRoot('presets-does-not-select-app');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'preset' => 'enterprise',
                'presets' => [
                    'web' => 'express',
                    'console' => 'hybrid',
                    'worker' => 'enterprise',
                ],
            ]);

            $config = self::resolver()->resolve(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Api,
                ),
                self::kernelConfig(),
            );

            self::assertSame(AppTarget::Api, $config->appTarget());
            self::assertSame('enterprise', $config->preset());
            self::assertSame(
                self::normalizePath($skeletonRoot . '/apps/api'),
                self::normalizePath($config->appRoot()),
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testPhaseADoesNotRequireSelectedPresetFileToExist(): void
    {
        $skeletonRoot = self::createSkeletonRoot('preset-file-not-required');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'presets' => [
                    'api' => 'future-mode',
                ],
            ]);

            self::writeThrowingPhpFile(
                $skeletonRoot . '/config/modes/future-mode.php',
                'mode-preset-file-must-not-be-loaded-in-phase-a',
            );

            $config = self::resolver()->resolve(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Api,
                ),
                self::kernelConfig(),
            );

            self::assertSame('future-mode', $config->preset());
            self::assertSame(AppTarget::Api, $config->appTarget());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testDiagnosticsDoNotLeakRawPresetValues(): void
    {
        $skeletonRoot = self::createSkeletonRoot('diagnostics-safe');

        $unsafePreset = "unsafe-preset\nAuthorization Bearer SECRET /tmp/coretsia-secret";

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'presets' => [
                    'api' => $unsafePreset,
                ],
            ]);

            try {
                self::resolver()->resolve(
                    new BootstrapInput(
                        skeletonRoot: $skeletonRoot,
                        appTarget: AppTarget::Api,
                    ),
                    self::kernelConfig(),
                );
            } catch (BootstrapException $exception) {
                self::assertSame(BootstrapException::ERROR_CODE, $exception->errorCode());
                self::assertSame(
                    BootstrapException::REASON_OVERRIDES_INVALID,
                    $exception->reason(),
                );
                self::assertSame(
                    'CORETSIA_BOOTSTRAP_FAILED: bootstrap-overrides-invalid',
                    $exception->getMessage(),
                );

                self::assertStringNotContainsString($unsafePreset, $exception->getMessage());
                self::assertStringNotContainsString('unsafe-preset', $exception->getMessage());
                self::assertStringNotContainsString('Authorization', $exception->getMessage());
                self::assertStringNotContainsString('Bearer', $exception->getMessage());
                self::assertStringNotContainsString('SECRET', $exception->getMessage());
                self::assertStringNotContainsString('/tmp/coretsia-secret', $exception->getMessage());
                self::assertStringNotContainsString($skeletonRoot, $exception->getMessage());

                return;
            }

            self::fail('Expected invalid per-app preset override to fail without leaking raw values.');
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    private static function resolver(): BootstrapConfigResolver
    {
        return new BootstrapConfigResolver(
            overridesLoader: new BootstrapOverridesLoader(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function kernelConfig(): array
    {
        return [
            'boot' => [
                'default_env' => 'local',
                'default_preset' => 'micro',
                'default_debug' => false,
            ],
            'env' => [
                'source_policy' => [
                    'default_local' => 'strict_dotenv',
                    'default_production' => 'allow_system',
                ],
            ],
        ];
    }

    private static function createSkeletonRoot(string $case): string
    {
        $root = self::normalizePath(
            \sys_get_temp_dir()
            . '/coretsia-kernel-bootstrap-preset-resolution/'
            . \getmypid()
            . '/'
            . (++self::$sequence)
            . '-'
            . $case,
        );

        self::removeDirectory($root);
        self::ensureDirectory($root . '/config');

        return $root;
    }

    /**
     * @param array<mixed> $payload
     */
    private static function writePhpArrayFile(string $file, array $payload): void
    {
        self::ensureDirectory(\dirname($file));

        $written = \file_put_contents(
            $file,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($payload, true) . ";\n",
        );

        self::assertIsInt($written);
    }

    private static function writeThrowingPhpFile(string $file, string $message): void
    {
        self::ensureDirectory(\dirname($file));

        $written = \file_put_contents(
            $file,
            "<?php\n\ndeclare(strict_types=1);\n\nthrow new \\RuntimeException('" . $message . "');\n",
        );

        self::assertIsInt($written);
    }

    private static function ensureDirectory(string $directory): void
    {
        if (\is_dir($directory)) {
            return;
        }

        self::assertTrue(
            \mkdir($directory, 0777, true),
            \sprintf('Failed to create test directory "%s".', $directory),
        );
    }

    private static function removeDirectory(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }

        if (\is_file($path) || \is_link($path)) {
            self::assertTrue(
                \unlink($path),
                \sprintf('Failed to remove test file "%s".', $path),
            );

            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $path,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            $filePath = $fileInfo->getPathname();

            if ($fileInfo->isDir() && !$fileInfo->isLink()) {
                self::assertTrue(
                    \rmdir($filePath),
                    \sprintf('Failed to remove test directory "%s".', $filePath),
                );

                continue;
            }

            self::assertTrue(
                \unlink($filePath),
                \sprintf('Failed to remove test file "%s".', $filePath),
            );
        }

        self::assertTrue(
            \rmdir($path),
            \sprintf('Failed to remove test directory "%s".', $path),
        );
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
