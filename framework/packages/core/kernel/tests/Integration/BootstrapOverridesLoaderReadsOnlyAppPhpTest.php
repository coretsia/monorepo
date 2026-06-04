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
                'presets' => [
                    'worker' => 'enterprise',
                    'web' => 'express',
                    'console' => 'hybrid',
                    'api' => 'micro',
                ],
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
                    'presets' => [
                        'api' => 'micro',
                        'console' => 'hybrid',
                        'web' => 'express',
                        'worker' => 'enterprise',
                    ],
                    'debug' => true,
                ],
                $overrides,
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testAcceptsPartialPresetsMap(): void
    {
        $skeletonRoot = self::createSkeletonRoot('partial-presets');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'presets' => [
                    'worker' => 'enterprise',
                    'api' => 'micro',
                ],
            ]);

            $overrides = self::loader()->load(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Worker,
                ),
            );

            self::assertSame(
                [
                    'presets' => [
                        'api' => 'micro',
                        'worker' => 'enterprise',
                    ],
                ],
                $overrides,
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testEmptyPresetsMapBehavesAsAbsent(): void
    {
        $skeletonRoot = self::createSkeletonRoot('empty-presets');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'presets' => [],
            ]);

            $overrides = self::loader()->load(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Console,
                ),
            );

            self::assertSame([], $overrides);
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

    public function testRejectsNonArrayAppPhpReturnValueDeterministically(): void
    {
        $skeletonRoot = self::createSkeletonRoot('non-array-return');

        try {
            self::writePhpReturnFile(
                $skeletonRoot . '/config/app.php',
                "'unsafe-token Authorization Cookie session_id password SELECT * FROM users /tmp/coretsia-secret'",
            );

            self::assertInvalidOverridesWithoutLeaking(
                skeletonRoot: $skeletonRoot,
                appTarget: AppTarget::Api,
                forbiddenFragments: [
                    'unsafe-token',
                    'Authorization',
                    'Cookie',
                    'session_id',
                    'password',
                    'SELECT * FROM users',
                    '/tmp/coretsia-secret',
                    $skeletonRoot,
                ],
            );
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

            self::assertInvalidOverridesWithoutLeaking(
                skeletonRoot: $skeletonRoot,
                appTarget: AppTarget::Console,
                forbiddenFragments: [
                    'unknownKey',
                    $unsafeValue,
                    'unsafe-token',
                    'Authorization',
                    'Cookie',
                    'session_id',
                    'password',
                    'SELECT * FROM users',
                    '/tmp/coretsia-secret',
                    $skeletonRoot,
                ],
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testUnknownPresetsAppTargetKeysFailDeterministicallyAndDoNotLeakRawValues(): void
    {
        $skeletonRoot = self::createSkeletonRoot('unknown-presets-app-target');

        $unsafeValue = 'unsafe-preset Authorization Cookie SECRET /tmp/coretsia-secret';

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'presets' => [
                    'admin' => $unsafeValue,
                ],
            ]);

            self::writeThrowingPhpFile(
                $skeletonRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            self::assertInvalidOverridesWithoutLeaking(
                skeletonRoot: $skeletonRoot,
                appTarget: AppTarget::Api,
                forbiddenFragments: [
                    'admin',
                    $unsafeValue,
                    'unsafe-preset',
                    'Authorization',
                    'Cookie',
                    'SECRET',
                    '/tmp/coretsia-secret',
                    $skeletonRoot,
                ],
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testRejectsPresetsListSequenceWithoutLeakingRawValues(): void
    {
        $skeletonRoot = self::createSkeletonRoot('presets-list');

        try {
            self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                'presets' => [
                    'micro',
                    'express',
                    'unsafe-preset Authorization /tmp/coretsia-secret',
                ],
            ]);

            self::assertInvalidOverridesWithoutLeaking(
                skeletonRoot: $skeletonRoot,
                appTarget: AppTarget::Worker,
                forbiddenFragments: [
                    'unsafe-preset',
                    'Authorization',
                    '/tmp/coretsia-secret',
                    $skeletonRoot,
                ],
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testRejectsInvalidPresetsValuesWithoutLeakingRawValues(): void
    {
        $cases = [
            'non-string-preset-value' => [
                'value' => 123,
                'forbiddenFragments' => [
                    $this->name(),
                ],
            ],
            'empty-preset-value' => [
                'value' => '',
                'forbiddenFragments' => [
                    $this->name(),
                ],
            ],
            'unsafe-preset-value' => [
                'value' => "invalid-preset\nAuthorization Bearer SECRET /tmp/coretsia-secret",
                'forbiddenFragments' => [
                    'invalid-preset',
                    'Authorization',
                    'Bearer',
                    'SECRET',
                    '/tmp/coretsia-secret',
                ],
            ],
            'trimmed-preset-value' => [
                'value' => ' micro ',
                'forbiddenFragments' => [
                    ' micro ',
                ],
            ],
        ];

        foreach ($cases as $case => $fixture) {
            $skeletonRoot = self::createSkeletonRoot($case);

            try {
                self::writePhpArrayFile($skeletonRoot . '/config/app.php', [
                    'presets' => [
                        'api' => $fixture['value'],
                    ],
                ]);

                self::assertInvalidOverridesWithoutLeaking(
                    skeletonRoot: $skeletonRoot,
                    appTarget: AppTarget::Api,
                    forbiddenFragments: [
                        ...$fixture['forbiddenFragments'],
                        $skeletonRoot,
                    ],
                );
            } finally {
                self::removeDirectory($skeletonRoot);
            }
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

            self::assertInvalidOverridesWithoutLeaking(
                skeletonRoot: $skeletonRoot,
                appTarget: AppTarget::Worker,
                forbiddenFragments: [
                    $unsafeValue,
                    'invalid-env',
                    'Authorization',
                    'Bearer',
                    'SECRET',
                    '/tmp/coretsia-secret',
                    $skeletonRoot,
                ],
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    private static function assertInvalidOverridesWithoutLeaking(
        string $skeletonRoot,
        AppTarget $appTarget,
        array $forbiddenFragments,
    ): void {
        try {
            self::loader()->load(
                new BootstrapInput(
                    skeletonRoot: $skeletonRoot,
                    appTarget: $appTarget,
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

            foreach ($forbiddenFragments as $fragment) {
                if ($fragment === '') {
                    continue;
                }

                self::assertStringNotContainsString($fragment, $exception->getMessage());
            }

            return;
        }

        self::fail('Expected invalid bootstrap overrides to fail with BootstrapException.');
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

    private static function writePhpReturnFile(string $file, string $returnExpression): void
    {
        self::ensureDirectory(\dirname($file));

        $written = \file_put_contents(
            $file,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $returnExpression . ";\n",
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
