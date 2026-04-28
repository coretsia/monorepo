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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Platform\Cli\Application;
use Coretsia\Platform\Cli\Tests\Fake\FakeWorkspaceSyncApplyCommand;
use Coretsia\Platform\Cli\Tests\Fake\FakeWorkspaceSyncDryRunCommand;
use PHPUnit\Framework\TestCase;

final class ApplicationSkeletonDispatchIntegrationTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        require_once \dirname(__DIR__) . '/Fake/FakeWorkspaceSyncDryRunCommand.php';
        require_once \dirname(__DIR__) . '/Fake/FakeWorkspaceSyncApplyCommand.php';

        FakeWorkspaceSyncDryRunCommand::reset();
        FakeWorkspaceSyncApplyCommand::reset();

        $this->tmpDir = $this->createTempDir();
    }

    protected function tearDown(): void
    {
        FakeWorkspaceSyncDryRunCommand::reset();
        FakeWorkspaceSyncApplyCommand::reset();

        $this->removeTree($this->tmpDir);

        parent::tearDown();
    }

    public function testRunDispatchesRegisteredMultiTokenDryRunCommand(): void
    {
        $launcherFile = $this->createLayout(withSkeletonConfig: true);

        $app = new Application($launcherFile);

        $result = $this->runApplicationAndCaptureOutput($app, [
            'coretsia',
            'workspace:sync',
            '--dry-run',
            '--format=text',
        ]);

        self::assertSame(0, $result['exitCode']);
        self::assertSame('', $result['output']);
        self::assertSame(1, FakeWorkspaceSyncDryRunCommand::$calls);
        self::assertSame(0, FakeWorkspaceSyncApplyCommand::$calls);
    }

    public function testRunDispatchesRegisteredMultiTokenApplyCommand(): void
    {
        $launcherFile = $this->createLayout(withSkeletonConfig: true);

        $app = new Application($launcherFile);

        $result = $this->runApplicationAndCaptureOutput($app, [
            'coretsia',
            'workspace:sync',
            '--apply',
        ]);

        self::assertSame(0, $result['exitCode']);
        self::assertSame('', $result['output']);
        self::assertSame(0, FakeWorkspaceSyncDryRunCommand::$calls);
        self::assertSame(1, FakeWorkspaceSyncApplyCommand::$calls);
    }

    public function testMissingSkeletonCliConfigIsEmptyOverlayAndDoesNotRegisterCommands(): void
    {
        $launcherFile = $this->createLayout(withSkeletonConfig: false);

        $app = new Application($launcherFile);

        $loadCliConfigSubtree = \Closure::bind(
            static function (Application $app): array {
                /** @var array<string, mixed> $cli */
                $cli = $app->loadCliConfigSubtree();

                return $cli;
            },
            null,
            Application::class
        );

        self::assertInstanceOf(\Closure::class, $loadCliConfigSubtree);

        $cli = $loadCliConfigSubtree($app);

        self::assertArrayHasKey('commands', $cli);
        self::assertSame([], $cli['commands']);

        self::assertSame(0, FakeWorkspaceSyncDryRunCommand::$calls);
        self::assertSame(0, FakeWorkspaceSyncApplyCommand::$calls);
    }

    private function createLayout(bool $withSkeletonConfig): string
    {
        $repoRoot = $this->tmpDir . '/repo';

        $frameworkBinDir = $repoRoot . '/framework/bin';
        $skeletonConfigDir = $repoRoot . '/skeleton/config';

        self::assertTrue(@mkdir($frameworkBinDir, 0777, true) || is_dir($frameworkBinDir));

        $launcherFile = $frameworkBinDir . '/coretsia';

        file_put_contents($launcherFile, "#!/usr/bin/env php\n<?php\n");

        if ($withSkeletonConfig) {
            self::assertTrue(@mkdir($skeletonConfigDir, 0777, true) || is_dir($skeletonConfigDir));

            file_put_contents(
                $skeletonConfigDir . '/cli.php',
                $this->buildSkeletonConfigPhp()
            );
        }

        return $launcherFile;
    }

    private function buildSkeletonConfigPhp(): string
    {
        $dryRunClassFile = \dirname(__DIR__) . '/Fake/FakeWorkspaceSyncDryRunCommand.php';
        $applyClassFile = \dirname(__DIR__) . '/Fake/FakeWorkspaceSyncApplyCommand.php';

        return <<<PHP
<?php

declare(strict_types=1);

require_once {$this->exportPhpString($dryRunClassFile)};
require_once {$this->exportPhpString($applyClassFile)};

return [
    'commands' => [
        \\Coretsia\\Platform\\Cli\\Tests\\Fake\\FakeWorkspaceSyncDryRunCommand::class,
        \\Coretsia\\Platform\\Cli\\Tests\\Fake\\FakeWorkspaceSyncApplyCommand::class,
    ],
];
PHP;
    }

    private function exportPhpString(string $value): string
    {
        return var_export($value, true);
    }

    /**
     * @param list<string> $argv
     * @return array{exitCode:int,output:string}
     */
    private function runApplicationAndCaptureOutput(Application $app, array $argv): array
    {
        \ob_start();

        try {
            $exitCode = $app->run($argv);
            $output = \ob_get_clean();
        } catch (\Throwable $e) {
            $output = \ob_get_clean();

            if (!\is_string($output)) {
                $output = '';
            }

            throw $e;
        }

        if (!\is_string($output)) {
            $output = '';
        }

        return [
            'exitCode' => $exitCode,
            'output' => $this->normalizeEol($output),
        ];
    }

    private function normalizeEol(string $value): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $value);
    }

    private function createTempDir(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'coretsia_cli_');
        self::assertNotFalse($tmp);

        if (is_file($tmp)) {
            unlink($tmp);
        }

        self::assertTrue(@mkdir($tmp, 0777, true) || is_dir($tmp));

        return $tmp;
    }

    private function removeTree(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            @rmdir($path);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->removeTree($path . '/' . $item);
        }

        @rmdir($path);
    }
}
