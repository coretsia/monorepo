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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Foundation\Module\FoundationModule;
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Ops\KernelOpsHostSeedConfigLoader;
use PHPUnit\Framework\TestCase;

final class KernelOpsHostSeedConfigLoaderIsWarningSafeTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-kernel-ops-seed-config-loader-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testMissingFoundationSeedConfigFailsDeterministicallyWithoutWarningOutput(): void
    {
        $foundationRoot = $this->packageRoot('foundation');
        $kernelRoot = $this->packageRoot('kernel');

        $this->writeConfig(
            $kernelRoot . '/config/kernel.php',
            <<<'PHP'
<?php

declare(strict_types=1);

return [
    'fixture' => true,
];
PHP,
        );

        $loader = $this->loader(
            foundationRoot: $foundationRoot,
            kernelRoot: $kernelRoot,
        );

        $outerWarnings = [];

        \set_error_handler(
            static function (
                int $severity,
                string $message,
                string $_file,
                int $_line,
            ) use (&$outerWarnings): bool {
                $outerWarnings[] = [$severity, $message];

                return true;
            },
        );

        \ob_start();

        try {
            $exception = $this->assertSafeFailure(
                loader: $loader,
                forbiddenNeedles: [
                    $foundationRoot,
                    $kernelRoot,
                    'foundation.php',
                ],
            );
        } finally {
            $output = (string) \ob_get_clean();
            \restore_error_handler();
        }

        self::assertSame('', $output);
        self::assertSame([], $outerWarnings);
        self::assertNull($exception->getPrevious());
    }

    public function testWarningFromInjectedKernelSeedConfigFailsDeterministicallyWithoutWarningOutput(): void
    {
        $foundationRoot = $this->packageRoot('foundation');
        $kernelRoot = $this->packageRoot('kernel');

        $this->writeValidFoundationConfig($foundationRoot);
        $this->writeConfig(
            $kernelRoot . '/config/kernel.php',
            <<<'PHP'
<?php

declare(strict_types=1);

\trigger_error('raw-kernel-seed-warning-value', E_USER_WARNING);

return [
    'fixture' => true,
];
PHP,
        );

        $loader = $this->loader(
            foundationRoot: $foundationRoot,
            kernelRoot: $kernelRoot,
        );

        $outerWarnings = [];

        \set_error_handler(
            static function (
                int $severity,
                string $message,
                string $_file,
                int $_line,
            ) use (&$outerWarnings): bool {
                $outerWarnings[] = [$severity, $message];

                return true;
            },
        );

        \ob_start();

        try {
            $exception = $this->assertSafeFailure(
                loader: $loader,
                forbiddenNeedles: [
                    'raw-kernel-seed-warning-value',
                    $foundationRoot,
                    $kernelRoot,
                    'config/kernel.php',
                ],
            );
        } finally {
            $output = (string) \ob_get_clean();
            \restore_error_handler();
        }

        self::assertSame('', $output);
        self::assertSame([], $outerWarnings);
        self::assertNull($exception->getPrevious());
    }

    public function testThrowableFromSeedConfigIsNotExposedVerbatim(): void
    {
        $foundationRoot = $this->packageRoot('foundation');
        $kernelRoot = $this->packageRoot('kernel');
        $rawSecret = 'raw-seed-config-secret-value';
        $absolutePath = $kernelRoot . '/private/credential.txt';

        $this->writeValidFoundationConfig($foundationRoot);
        $this->writeConfig(
            $kernelRoot . '/config/kernel.php',
            '<?php declare(strict_types=1); throw new \RuntimeException('
            . \var_export($rawSecret . ' ' . $absolutePath, true)
            . ');',
        );

        $exception = $this->assertSafeFailure(
            loader: $this->loader(
                foundationRoot: $foundationRoot,
                kernelRoot: $kernelRoot,
            ),
            forbiddenNeedles: [
                $rawSecret,
                $absolutePath,
                $foundationRoot,
                $kernelRoot,
            ],
        );

        self::assertNull($exception->getPrevious());
    }

    public function testPreviousPhpErrorHandlerIsRestoredAfterSeedConfigFailure(): void
    {
        $foundationRoot = $this->packageRoot('foundation');
        $kernelRoot = $this->packageRoot('kernel');

        $this->writeValidFoundationConfig($foundationRoot);
        $this->writeConfig(
            $kernelRoot . '/config/kernel.php',
            <<<'PHP'
<?php

declare(strict_types=1);

\trigger_error('inner-kernel-seed-warning', E_USER_WARNING);

return [];
PHP,
        );

        $captured = [];

        \set_error_handler(
            static function (
                int $severity,
                string $message,
                string $_file,
                int $_line,
            ) use (&$captured): bool {
                $captured[] = [$severity, $message];

                return true;
            },
        );

        try {
            $this->assertSafeFailure(
                loader: $this->loader(
                    foundationRoot: $foundationRoot,
                    kernelRoot: $kernelRoot,
                ),
                forbiddenNeedles: [
                    'inner-kernel-seed-warning',
                    $foundationRoot,
                    $kernelRoot,
                ],
            );

            \trigger_error('outer-handler-restored-probe', E_USER_WARNING);
        } finally {
            \restore_error_handler();
        }

        self::assertSame(
            [
                [\E_USER_WARNING, 'outer-handler-restored-probe'],
            ],
            $captured,
        );
    }

    private function loader(
        string $foundationRoot,
        string $kernelRoot,
    ): KernelOpsHostSeedConfigLoader {
        return new KernelOpsHostSeedConfigLoader(
            new ComposerPackageInstallPathResolver(
                installRoots: [
                    FoundationModule::COMPOSER_PACKAGE => $foundationRoot,
                    KernelModule::COMPOSER_PACKAGE => $kernelRoot,
                ],
            ),
        );
    }

    private function packageRoot(string $name): string
    {
        $root = $this->temporaryDirectory . '/' . $name;

        \mkdir($root, 0777, true);

        return $root;
    }

    private function writeValidFoundationConfig(string $foundationRoot): void
    {
        $this->writeConfig(
            $foundationRoot . '/config/foundation.php',
            <<<'PHP'
<?php

declare(strict_types=1);

return [
    'fixture' => true,
];
PHP,
        );
    }

    private function writeConfig(string $path, string $source): void
    {
        $directory = \dirname($path);

        if (!\is_dir($directory)) {
            \mkdir($directory, 0777, true);
        }

        self::assertNotFalse(\file_put_contents($path, $source));
    }

    /**
     * @param list<string> $forbiddenNeedles
     */
    private function assertSafeFailure(
        KernelOpsHostSeedConfigLoader $loader,
        array $forbiddenNeedles,
    ): \RuntimeException {
        try {
            $loader->load();

            self::fail('Expected Kernel Ops seed config loading to fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'kernel-ops-host-seed-config-load-failed',
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());

            foreach ($forbiddenNeedles as $needle) {
                self::assertStringNotContainsString(
                    $needle,
                    $exception->getMessage(),
                );
            }

            return $exception;
        }
    }

    private static function removeTree(string $path): void
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

            if (\is_dir($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            @\unlink($itemPath);
        }

        @\rmdir($path);
    }
}
