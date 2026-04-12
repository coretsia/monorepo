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
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Tests\Fake\FakeWorkspaceSyncApplyCommand;
use Coretsia\Platform\Cli\Tests\Fake\FakeWorkspaceSyncDryRunCommand;
use PHPUnit\Framework\TestCase;

final class ApplicationPresetDispatchIntegrationTest extends TestCase
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
        [$autoloadFile, $launcherFile] = $this->createLayout(withPreset: true);

        $app = new Application($autoloadFile, $launcherFile);

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
        [$autoloadFile, $launcherFile] = $this->createLayout(withPreset: true);

        $app = new Application($autoloadFile, $launcherFile);

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

    public function testMissingCliSpikesPresetBreaksCommandRegistration(): void
    {
        [$autoloadFile, $launcherFile] = $this->createLayout(withPreset: false);

        $app = new Application($autoloadFile, $launcherFile);

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

        try {
            $loadCliConfigSubtree($app);
            self::fail('CliConfigInvalidException with reason "cli-spikes-preset-missing" was expected.');
        } catch (CliConfigInvalidException $e) {
            self::assertSame('CORETSIA_CLI_CONFIG_INVALID', $e->code());
            self::assertSame('cli-spikes-preset-missing', $e->reason());
        }

        self::assertSame(0, FakeWorkspaceSyncDryRunCommand::$calls);
        self::assertSame(0, FakeWorkspaceSyncApplyCommand::$calls);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function createLayout(bool $withPreset): array
    {
        $repoRoot = $this->tmpDir . '/repo';
        $vendorRoot = $this->tmpDir . '/vendor';

        $frameworkBinDir = $repoRoot . '/framework/bin';
        $skeletonDir = $repoRoot . '/skeleton';
        $packageRoot = $vendorRoot . '/coretsia/devtools-cli-spikes';

        self::assertTrue(@mkdir($frameworkBinDir, 0777, true) || is_dir($frameworkBinDir));
        self::assertTrue(@mkdir($skeletonDir, 0777, true) || is_dir($skeletonDir));
        self::assertTrue(@mkdir($vendorRoot, 0777, true) || is_dir($vendorRoot));
        self::assertTrue(@mkdir($packageRoot, 0777, true) || is_dir($packageRoot));

        $launcherFile = $frameworkBinDir . '/coretsia';
        $autoloadFile = $vendorRoot . '/autoload.php';

        file_put_contents($launcherFile, "#!/usr/bin/env php\n<?php\n");
        file_put_contents($autoloadFile, "<?php\n");

        if ($withPreset) {
            $configDir = $packageRoot . '/config';
            self::assertTrue(@mkdir($configDir, 0777, true) || is_dir($configDir));

            file_put_contents(
                $configDir . '/cli.php',
                $this->buildPresetConfigPhp()
            );
        }

        return [$autoloadFile, $launcherFile];
    }

    private function buildPresetConfigPhp(): string
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
