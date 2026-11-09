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

use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class SkeletonDependencySyncLauncherRoutingTest extends CliIntegrationTestCase
{
    private const string FAILURE = 'CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable';

    public function testExplicitDependencySyncOperationsDelegateToAdapterWithOriginalArgv(): void
    {
        $root = self::fixture('dependency-sync-routes');

        try {
            foreach (
                [
                    ['plan'],
                    ['review', '--preset=worker=hybrid'],
                    ['apply', '--allow-repair'],
                    ['plan', '--target=web'],
                    ['apply', '--preset=web=hybrid'],
                ] as $arguments
            ) {
                $result = self::execute($root . '/coretsia', $arguments);

                self::assertRouted($result, 'adapter', $root . '/coretsia', $arguments);
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testNormalCommandsDelegateToComposerBinary(): void
    {
        $root = self::fixture('normal-cli-routes');

        try {
            foreach (['list', 'help', 'doctor'] as $command) {
                $arguments = [$command];
                $result = self::execute($root . '/coretsia', $arguments);

                self::assertRouted($result, 'composer', $root . '/coretsia', $arguments);
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testMissingDependencySyncAdapterFailsClosed(): void
    {
        $root = self::fixture('missing-dependency-sync-adapter');

        try {
            self::assertTrue(\unlink($root . '/bin/dependency-sync.php'));

            $result = self::execute($root . '/coretsia', ['plan', '--target=web']);

            self::assertSafeFailure($result, self::FAILURE);
        } finally {
            self::removeTree($root);
        }
    }

    public function testSymlinkDependencySyncAdapterFailsClosed(): void
    {
        $root = self::fixture('symlink-dependency-sync-adapter');

        try {
            $entrypoint = $root . '/bin/dependency-sync.php';
            $target = $root . '/dependency-sync-target.php';

            self::assertTrue(\rename($entrypoint, $target));

            self::assertTrue(
                \function_exists('symlink') && @\symlink($target, $entrypoint) && \is_link($entrypoint),
                'A real symbolic link is required. On Windows, enable Developer Mode or grant symlink privileges.',
            );

            $result = self::execute($root . '/coretsia', ['review', '--target=web']);

            self::assertSafeFailure($result, self::FAILURE);
        } finally {
            self::removeTree($root);
        }
    }

    public function testAdapterExitCodeIsPreserved(): void
    {
        $root = self::fixture('dependency-sync-exit-code');

        try {
            foreach ([0, 1] as $exitCode) {
                self::write(
                    $root . '/bin/dependency-sync.php',
                    self::adapter($exitCode),
                );

                $result = self::execute(
                    $root . '/coretsia',
                    ['apply', '--allow-repair'],
                );

                self::assertSame($exitCode, $result['exit']);
                self::assertSame('', $result['stderr']);
                self::assertSame(
                    [
                        'source' => 'adapter',
                        'argv' => [$root . '/coretsia', 'apply', '--allow-repair'],
                    ],
                    \json_decode($result['stdout'], true, flags: \JSON_THROW_ON_ERROR),
                );
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testDependencySyncOperationsAreNotExposedInPackagedCliList(): void
    {
        $root = self::consumer();

        try {
            $result = self::execute(
                $root . '/vendor/bin/coretsia',
                ['list', '--format=json'],
            );

            self::assertSame(0, $result['exit'], $result['stderr']);

            $record = self::jsonRecord($result['stdout']);
            $groups = $record['data']['records'][0]['payload']['groups'];

            self::assertIsArray($groups);

            $commandNames = [];

            foreach ($groups as $commands) {
                self::assertIsArray($commands);

                foreach ($commands as $command) {
                    self::assertIsArray($command);
                    self::assertIsString($command['name']);
                    $commandNames[] = $command['name'];
                }
            }

            self::assertContains('list', $commandNames);
            self::assertContains('help', $commandNames);
            self::assertContains('doctor', $commandNames);

            foreach (['plan', 'review', 'apply'] as $operation) {
                self::assertNotContains($operation, $commandNames);
            }

            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    private static function fixture(string $name): string
    {
        $root = self::temporaryRoot($name);

        try {
            $launcher = self::repositoryRoot() . '/packages/applications/skeleton/coretsia';

            self::assertFileExists($launcher);
            self::assertTrue(\is_readable($launcher));

            $source = \file_get_contents($launcher);
            self::assertIsString($source);

            self::write(
                $root . '/coretsia',
                $source,
            );
            self::write(
                $root . '/bin/dependency-sync.php',
                self::adapter(0),
            );
            self::write(
                $root . '/vendor/bin/coretsia',
                self::composerProxy(),
            );

            return $root;
        } catch (\Throwable $exception) {
            self::removeTree($root);

            throw $exception;
        }
    }

    private static function adapter(int $exitCode): string
    {
        $source = <<<'PHP'
<?php

echo \json_encode(
    ['source' => 'adapter', 'argv' => $argv],
    \JSON_THROW_ON_ERROR,
);
exit(EXIT_CODE);
PHP;

        return \str_replace('EXIT_CODE', (string) $exitCode, $source) . "\n";
    }

    private static function composerProxy(): string
    {
        return <<<'PHP'
<?php

echo \json_encode(
    ['source' => 'composer', 'argv' => $argv],
    \JSON_THROW_ON_ERROR,
);
return 0;
PHP;
    }

    /**
     * @param array{exit:int,stdout:string,stderr:string} $result
     * @param list<string> $arguments
     */
    private static function assertRouted(
        array $result,
        string $source,
        string $launcher,
        array $arguments,
    ): void {
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame(
            [
                'source' => $source,
                'argv' => [$launcher, ...$arguments],
            ],
            \json_decode($result['stdout'], true, flags: \JSON_THROW_ON_ERROR),
        );
    }
}
