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
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use PHPUnit\Framework\TestCase;

final class BootstrapOverridesLoaderReadsOnlyAppPhpTest extends TestCase
{
    private static int $sequence = 0;

    public function testReadsSkeletonConfigAppPhpWhenPresentAndAllowsOnlyBootOverrides(): void
    {
        $skeletonRoot = self::createSkeletonRoot('reads-app-php');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'appEnv' => 'staging',
                'preset' => 'micro',
                'debug' => true,
            ]);

            self::writeThrowingPhpFile(
                $skeletonRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            $overrides = self::loader()->load(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Api,
                ),
            );

            self::assertSame(
                [
                    'appEnv' => 'staging',
                    'preset' => 'micro',
                    'debug' => true,
                ],
                $overrides,
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testDoesNotReadSkeletonConfigModulesPhp(): void
    {
        $skeletonRoot = self::createSkeletonRoot('does-not-read-modules-php');

        try {
            self::writeThrowingPhpFile(
                $skeletonRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            $overrides = self::loader()->load(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Web,
                ),
            );

            self::assertSame([], $overrides);
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testUnknownOverrideKeysFailDeterministicallyAndDoNotLeakRawOverrideValues(): void
    {
        $skeletonRoot = self::createSkeletonRoot('unknown-keys');

        $unsafeValue = 'unsafe-token Authorization Cookie session_id password SELECT * FROM users /tmp/coretsia-secret';

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'appEnv' => 'local',
                'unknownKey' => $unsafeValue,
            ]);

            self::writeThrowingPhpFile(
                $skeletonRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            try {
                self::loader()->load(
                    new BootstrapInput(
                        skeletonRoot: $skeletonRoot,
                        appTarget: AppTarget::Console,
                    ),
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
                self::assertSame(0, $exception->getCode());

                self::assertStringNotContainsString('unknownKey', $exception->getMessage());
                self::assertStringNotContainsString($unsafeValue, $exception->getMessage());
                self::assertStringNotContainsString('unsafe-token', $exception->getMessage());
                self::assertStringNotContainsString('Authorization', $exception->getMessage());
                self::assertStringNotContainsString('Cookie', $exception->getMessage());
                self::assertStringNotContainsString('session_id', $exception->getMessage());
                self::assertStringNotContainsString('password', $exception->getMessage());
                self::assertStringNotContainsString('SELECT * FROM users', $exception->getMessage());
                self::assertStringNotContainsString('/tmp/coretsia-secret', $exception->getMessage());
                self::assertStringNotContainsString($skeletonRoot, $exception->getMessage());

                return;
            }

            self::fail('Expected unknown bootstrap override key to fail with BootstrapException.');
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testInvalidAllowedOverrideValueFailsWithoutLeakingRawValue(): void
    {
        $skeletonRoot = self::createSkeletonRoot('invalid-allowed-value');

        $unsafeValue = "invalid-env\nAuthorization Bearer SECRET /tmp/coretsia-secret";

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'appEnv' => $unsafeValue,
            ]);

            try {
                self::loader()->load(
                    new BootstrapInput(
                        skeletonRoot: $skeletonRoot,
                        appTarget: AppTarget::Worker,
                    ),
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

                self::assertStringNotContainsString($unsafeValue, $exception->getMessage());
                self::assertStringNotContainsString('invalid-env', $exception->getMessage());
                self::assertStringNotContainsString('Authorization', $exception->getMessage());
                self::assertStringNotContainsString('Bearer', $exception->getMessage());
                self::assertStringNotContainsString('SECRET', $exception->getMessage());
                self::assertStringNotContainsString('/tmp/coretsia-secret', $exception->getMessage());
                self::assertStringNotContainsString($skeletonRoot, $exception->getMessage());

                return;
            }

            self::fail('Expected invalid allowed bootstrap override value to fail with BootstrapException.');
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    private static function loader(): BootstrapOverridesLoader
    {
        return new BootstrapOverridesLoader();
    }

    private static function createSkeletonRoot(string $case): string
    {
        $root = self::normalizePath(
            \sys_get_temp_dir()
            . '/coretsia-kernel-bootstrap-overrides-loader/'
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
     * @param array<string,mixed> $payload
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
