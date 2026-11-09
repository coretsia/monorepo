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

    public function testReadsApplicationConfigAppPhpWhenPresentAndAllowsOnlyBootOverrides(): void
    {
        $applicationRoot = self::createApplicationRoot('reads-app-php');

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', [
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
                $applicationRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            $overrides = self::loader()->load(
                new BootstrapInput(
                    applicationRoot: $applicationRoot,
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
            self::removeDirectory($applicationRoot);
        }
    }

    public function testAcceptsPartialPresetsMap(): void
    {
        $applicationRoot = self::createApplicationRoot('partial-presets');

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                'presets' => [
                    'worker' => 'enterprise',
                    'api' => 'micro',
                ],
            ]);

            $overrides = self::loader()->load(
                new BootstrapInput(
                    applicationRoot: $applicationRoot,
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
            self::removeDirectory($applicationRoot);
        }
    }

    public function testEmptyPresetsMapBehavesAsAbsent(): void
    {
        $applicationRoot = self::createApplicationRoot('empty-presets');

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                'presets' => [],
            ]);

            $overrides = self::loader()->load(
                new BootstrapInput(
                    applicationRoot: $applicationRoot,
                    appTarget: AppTarget::Console,
                ),
            );

            self::assertSame([], $overrides);
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testDoesNotReadApplicationConfigModulesPhp(): void
    {
        $applicationRoot = self::createApplicationRoot('does-not-read-modules-php');

        try {
            self::writeThrowingPhpFile(
                $applicationRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            $overrides = self::loader()->load(
                new BootstrapInput(
                    applicationRoot: $applicationRoot,
                    appTarget: AppTarget::Web,
                ),
            );

            self::assertSame([], $overrides);
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testRejectsNonArrayAppPhpReturnValueDeterministically(): void
    {
        $applicationRoot = self::createApplicationRoot('non-array-return');

        try {
            self::writePhpReturnFile(
                $applicationRoot . '/config/app.php',
                "'unsafe-token Authorization Cookie session_id password SELECT * FROM users /tmp/coretsia-secret'",
            );

            self::assertInvalidOverridesWithoutLeaking(
                applicationRoot: $applicationRoot,
                appTarget: AppTarget::Api,
                forbiddenFragments: [
                    'unsafe-token',
                    'Authorization',
                    'Cookie',
                    'session_id',
                    'password',
                    'SELECT * FROM users',
                    '/tmp/coretsia-secret',
                    $applicationRoot,
                ],
            );
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testUnknownOverrideKeysFailDeterministicallyAndDoNotLeakRawOverrideValues(): void
    {
        $applicationRoot = self::createApplicationRoot('unknown-keys');

        $unsafeValue = 'unsafe-token Authorization Cookie session_id password SELECT * FROM users /tmp/coretsia-secret';

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                'appEnv' => 'local',
                'unknownKey' => $unsafeValue,
            ]);

            self::writeThrowingPhpFile(
                $applicationRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            self::assertInvalidOverridesWithoutLeaking(
                applicationRoot: $applicationRoot,
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
                    $applicationRoot,
                ],
            );
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testUnknownPresetsAppTargetKeysFailDeterministicallyAndDoNotLeakRawValues(): void
    {
        $applicationRoot = self::createApplicationRoot('unknown-presets-app-target');

        $unsafeValue = 'unsafe-preset Authorization Cookie SECRET /tmp/coretsia-secret';

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                'presets' => [
                    'admin' => $unsafeValue,
                ],
            ]);

            self::writeThrowingPhpFile(
                $applicationRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            self::assertInvalidOverridesWithoutLeaking(
                applicationRoot: $applicationRoot,
                appTarget: AppTarget::Api,
                forbiddenFragments: [
                    'admin',
                    $unsafeValue,
                    'unsafe-preset',
                    'Authorization',
                    'Cookie',
                    'SECRET',
                    '/tmp/coretsia-secret',
                    $applicationRoot,
                ],
            );
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testRejectsPresetsListSequenceWithoutLeakingRawValues(): void
    {
        $applicationRoot = self::createApplicationRoot('presets-list');

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                'presets' => [
                    'micro',
                    'express',
                    'unsafe-preset Authorization /tmp/coretsia-secret',
                ],
            ]);

            self::assertInvalidOverridesWithoutLeaking(
                applicationRoot: $applicationRoot,
                appTarget: AppTarget::Worker,
                forbiddenFragments: [
                    'unsafe-preset',
                    'Authorization',
                    '/tmp/coretsia-secret',
                    $applicationRoot,
                ],
            );
        } finally {
            self::removeDirectory($applicationRoot);
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
            $applicationRoot = self::createApplicationRoot($case);

            try {
                self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                    'presets' => [
                        'api' => $fixture['value'],
                    ],
                ]);

                self::assertInvalidOverridesWithoutLeaking(
                    applicationRoot: $applicationRoot,
                    appTarget: AppTarget::Api,
                    forbiddenFragments: [
                        ...$fixture['forbiddenFragments'],
                        $applicationRoot,
                    ],
                );
            } finally {
                self::removeDirectory($applicationRoot);
            }
        }
    }

    public function testInvalidAllowedOverrideValueFailsWithoutLeakingRawValue(): void
    {
        $applicationRoot = self::createApplicationRoot('invalid-allowed-value');

        $unsafeValue = "invalid-env\nAuthorization Bearer SECRET /tmp/coretsia-secret";

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                'appEnv' => $unsafeValue,
            ]);

            self::assertInvalidOverridesWithoutLeaking(
                applicationRoot: $applicationRoot,
                appTarget: AppTarget::Worker,
                forbiddenFragments: [
                    $unsafeValue,
                    'invalid-env',
                    'Authorization',
                    'Bearer',
                    'SECRET',
                    '/tmp/coretsia-secret',
                    $applicationRoot,
                ],
            );
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testProjectWideActiveTargetsAreCanonicalWithoutSelectingARuntimeTarget(): void
    {
        $applicationRoot = self::createApplicationRoot('active-targets');

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                'activeTargets' => ['worker', 'web'],
                'presets' => ['worker' => 'enterprise'],
            ]);
            self::writeThrowingPhpFile(
                $applicationRoot . '/config/modules.php',
                'modules-php-must-not-be-read',
            );

            $expected = [
                'activeTargets' => ['web', 'worker'],
                'presets' => ['worker' => 'enterprise'],
            ];

            self::assertSame($expected, self::loader()->loadPartialFromApplicationRoot($applicationRoot));
            self::assertSame(
                $expected,
                self::loader()->load(new BootstrapInput($applicationRoot, AppTarget::Api)),
            );
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testRejectsMalformedActiveTargetsWithoutLeakingValues(): void
    {
        foreach (
            [
                [],
                ['web', 'web'],
                ['admin'],
                ['web' => 'worker'],
                ['web', 42],
            ] as $index => $targets
        ) {
            $applicationRoot = self::createApplicationRoot('invalid-targets-' . $index);

            try {
                self::writePhpArrayFile($applicationRoot . '/config/app.php', [
                    'activeTargets' => $targets,
                ]);

                self::assertInvalidOverridesWithoutLeaking(
                    applicationRoot: $applicationRoot,
                    appTarget: AppTarget::Web,
                    forbiddenFragments: [$applicationRoot, 'admin'],
                );
            } finally {
                self::removeDirectory($applicationRoot);
            }
        }
    }

    public function testRejectsEchoPrintAndFlushedOutputWithoutLeakingOrDamagingCallerBuffers(): void
    {
        $sources = [
            "echo 'secret-echo';",
            "print 'secret-print';",
            "echo 'secret-flush'; ob_flush();",
        ];

        foreach ($sources as $index => $source) {
            $applicationRoot = self::createApplicationRoot('source-output-' . $index);
            $initialLevel = \ob_get_level();
            \ob_start();

            try {
                self::writePhpSourceFile($applicationRoot . '/config/app.php', $source . "\nreturn [];\n");
                self::assertInvalidOverridesWithoutLeaking(
                    applicationRoot: $applicationRoot,
                    appTarget: AppTarget::Web,
                    forbiddenFragments: ['secret-echo', 'secret-print', 'secret-flush', $applicationRoot],
                );
                self::assertSame('', \ob_get_contents());
                self::assertSame($initialLevel + 1, \ob_get_level());
            } finally {
                while (\ob_get_level() > $initialLevel) {
                    \ob_end_clean();
                }

                self::removeDirectory($applicationRoot);
            }
        }
    }

    public function testRestoresOutputBufferAndErrorHandlerAfterSuccessfulAndFailedIncludes(): void
    {
        $applicationRoot = self::createApplicationRoot('buffer-handler-discipline');
        $initialLevel = \ob_get_level();
        $sentinel = static fn (): bool => false;
        $previous = \set_error_handler($sentinel);

        try {
            self::writePhpArrayFile($applicationRoot . '/config/app.php', []);
            self::assertSame([], self::loader()->loadPartialFromApplicationRoot($applicationRoot));
            self::assertSame($initialLevel, \ob_get_level());
            self::assertSame($sentinel, self::currentErrorHandler());

            self::writePhpSourceFile(
                $applicationRoot . '/config/app.php',
                "trigger_error('secret-warning', E_USER_WARNING);\nreturn [];\n",
            );
            self::assertInvalidOverridesWithoutLeaking(
                applicationRoot: $applicationRoot,
                appTarget: AppTarget::Web,
                forbiddenFragments: ['secret-warning', $applicationRoot],
            );
            self::assertSame($initialLevel, \ob_get_level());
            self::assertSame($sentinel, self::currentErrorHandler());
        } finally {
            \restore_error_handler();
            self::assertSame($previous, self::currentErrorHandler());
            self::removeDirectory($applicationRoot);
        }
    }

    public function testRestoresCallerBuffersAfterNestedOutputHandlerFailure(): void
    {
        $applicationRoot = self::createApplicationRoot('nested-output-handler-failure');
        $initialLevel = \ob_get_level();
        \ob_start();

        try {
            self::writePhpSourceFile(
                $applicationRoot . '/config/app.php',
                "ob_start(static function (string \$chunk): string {\n"
                . "    throw new \\RuntimeException('secret-output-handler');\n"
                . "});\n"
                . "echo 'secret-output';\n"
                . "return [];\n",
            );

            self::assertInvalidOverridesWithoutLeaking(
                applicationRoot: $applicationRoot,
                appTarget: AppTarget::Web,
                forbiddenFragments: ['secret-output-handler', 'secret-output', $applicationRoot],
            );
            self::assertSame($initialLevel + 1, \ob_get_level());
            self::assertSame('', \ob_get_contents());
        } finally {
            while (\ob_get_level() > $initialLevel) {
                \ob_end_clean();
            }

            self::removeDirectory($applicationRoot);
        }
    }

    public function testRestoresCallerErrorHandlerStackAfterOverrideHandlerChanges(): void
    {
        $applicationRoot = self::createApplicationRoot('handler-stack-restoration');
        $outerHandler = static fn (): bool => false;
        $innerHandler = static fn (): bool => false;
        $previous = \set_error_handler($outerHandler);

        try {
            \set_error_handler($innerHandler);

            try {
                foreach (
                    [
                        "restore_error_handler();\nreturn [];\n",
                        "for (\$i = 0; \$i < 40; \$i++) {\n"
                        . "    set_error_handler(static fn (): bool => true);\n"
                        . "}\nreturn [];\n",
                    ] as $body
                ) {
                    self::writePhpSourceFile($applicationRoot . '/config/app.php', $body);
                    self::assertInvalidOverridesWithoutLeaking(
                        applicationRoot: $applicationRoot,
                        appTarget: AppTarget::Web,
                        forbiddenFragments: [$applicationRoot],
                    );
                    self::assertSame($innerHandler, self::currentErrorHandler());
                }
            } finally {
                \restore_error_handler();
            }

            self::assertSame($outerHandler, self::currentErrorHandler());
        } finally {
            \restore_error_handler();
            self::assertSame($previous, self::currentErrorHandler());
            self::removeDirectory($applicationRoot);
        }
    }

    private static function writePhpSourceFile(string $file, string $body): void
    {
        self::ensureDirectory(\dirname($file));
        self::assertIsInt(\file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n" . $body));
    }

    private static function currentErrorHandler(): ?callable
    {
        $previous = \set_error_handler(static fn (): bool => false);
        \restore_error_handler();

        return $previous;
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    private static function assertInvalidOverridesWithoutLeaking(
        string $applicationRoot,
        AppTarget $appTarget,
        array $forbiddenFragments,
    ): void {
        try {
            self::loader()->load(
                new BootstrapInput(
                    applicationRoot: $applicationRoot,
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

    private static function createApplicationRoot(string $case): string
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
