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

use Composer\InstalledVersions;
use Coretsia\Kernel\DependencySync\Process\DependencySyncProcessRunner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('slow')]
final class DependencySyncRealComposerMetadataTest extends TestCase
{
    public function testIsolatedComposerInstallProvidesRuntimeMetadataBeforeGraphResolution(): void
    {
        $root = self::consumer();

        try {
            $script = $root . '/bin/read-installed-metadata.php';

            \file_put_contents(
                $script,
                <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ComposerInstalledMetadataProvider;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;

$manifest = new ComposerManifestReader(
    new ComposerInstalledMetadataProvider(),
)->read();

$expected = [
    'core.foundation' => 'coretsia/core-foundation',
    'core.kernel' => 'coretsia/core-kernel',
    'platform.worker' => 'coretsia/platform-worker',
];

$beforeResolve = [];

foreach (
    $expected as $moduleId => $composerName
) {
    $descriptor = $manifest->get($moduleId);

    if (
        $descriptor === null
        || $descriptor->composerName() !== $composerName
    ) {
        exit(2);
    }

    $beforeResolve[$moduleId] = $descriptor->composerName();
}

$plan = new ModuleGraphResolver(
    new TopologicalSorter(),
)->resolve(
    'worker',
    $manifest,
    new ModuleSelection(
        [
            ModuleId::fromString('platform.worker'),
        ],
        [],
    ),
);

$enabled = array_map(
    static fn (ModuleId $moduleId): string =>
        $moduleId->value(),
    $plan->enabled(),
);

sort($enabled, SORT_STRING);

echo json_encode(
    [
        'beforeResolve' => $beforeResolve,
        'enabled' => $enabled,
    ],
    JSON_THROW_ON_ERROR
    | JSON_UNESCAPED_SLASHES,
);
PHP,
            );

            $result = new DependencySyncProcessRunner()
                ->runPhpScript(
                    $root,
                    $script,
                    [],
                    60,
                );

            self::assertSame(0, $result['exitCode'], $result['stderr']);
            self::assertFalse($result['timedOut']);

            $decoded = \json_decode(
                $result['stdout'],
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            self::assertSame(
                [
                    'core.foundation' => 'coretsia/core-foundation',
                    'core.kernel' => 'coretsia/core-kernel',
                    'platform.worker' => 'coretsia/platform-worker',
                ],
                $decoded['beforeResolve'] ?? null,
            );

            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.worker',
                ],
                $decoded['enabled'] ?? null,
            );
        } finally {
            self::remove($root);
        }
    }

    private static function consumer(): string
    {
        self::requireComposer();

        $root = \sys_get_temp_dir()
            . '/coretsia-real-metadata-'
            . \bin2hex(\random_bytes(8));

        $repoRoot = \dirname(__DIR__, 5);
        $repositoryRoot = $root . '/repository';

        \mkdir($repositoryRoot, 0777, true);
        \mkdir($root . '/bin', 0777, true);

        $repositories = [];

        foreach (
            [
                [
                    'coretsia/core-contracts',
                    $repoRoot . '/packages/core/contracts',
                    '0.7.0',
                ],
                [
                    'coretsia/core-foundation',
                    $repoRoot . '/packages/core/foundation',
                    '0.7.0',
                ],
                [
                    'coretsia/core-kernel',
                    $repoRoot . '/packages/core/kernel',
                    '0.7.0',
                ],
                [
                    'coretsia/platform-worker',
                    $repoRoot . '/packages/platform/worker',
                    '0.7.0',
                ],
            ] as [$name, $source, $version]
        ) {
            $repositories[] = self::copyPackageRepository(
                $repositoryRoot,
                $name,
                $source,
                $version,
            );
        }

        foreach (
            [
                'psr/clock',
                'psr/container',
                'psr/log',
            ] as $name
        ) {
            $source = InstalledVersions::getInstallPath($name);
            $version = InstalledVersions::getPrettyVersion($name);

            self::assertIsString($source);
            self::assertIsString($version);

            $repositories[] = self::copyPackageRepository(
                $repositoryRoot,
                $name,
                $source,
                $version,
            );
        }

        \file_put_contents(
            $root . '/composer.json',
            \json_encode(
                [
                    'name' => 'coretsia/test-real-metadata',
                    'type' => 'project',
                    'minimum-stability' => 'stable',
                    'prefer-stable' => true,
                    'repositories' => $repositories,
                    'require' => [
                        'php' => '^8.4',
                        'coretsia/platform-worker' => '^0.7.0',
                    ],
                    'config' => [
                        'sort-packages' => true,
                    ],
                ],
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        $install = new DependencySyncProcessRunner()
            ->runComposer(
                $root,
                [
                    'install',
                    '--no-interaction',
                    '--no-plugins',
                    '--no-scripts',
                    '--no-ansi',
                    '--no-progress',
                ],
                300,
            );

        self::assertSame(
            0,
            $install['exitCode'],
            $install['stdout'] . $install['stderr'],
        );
        self::assertFalse($install['timedOut']);
        self::assertFileExists($root . '/vendor/composer/installed.json');

        return $root;
    }

    /** @return array<string, mixed> */
    private static function copyPackageRepository(
        string $repositoryRoot,
        string $name,
        string $source,
        string $version,
    ): array {
        $destination = $repositoryRoot . '/' . \str_replace('/', '-', $name);

        self::copyTree($source, $destination);

        return [
            'type' => 'path',
            'url' => \str_replace('\\', '/', $destination),
            'options' => [
                'symlink' => false,
                'versions' => [
                    $name => $version,
                ],
            ],
        ];
    }

    private static function copyTree(
        string $source,
        string $destination,
    ): void {
        self::assertDirectoryExists($source);

        \mkdir($destination, 0777, true);

        $iterator =
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $source,
                    \FilesystemIterator::SKIP_DOTS,
                ),
                \RecursiveIteratorIterator::SELF_FIRST,
            );

        foreach ($iterator as $item) {
            $relative = \substr(
                $item->getPathname(),
                \strlen(
                    \rtrim($source, '/\\'),
                ) + 1,
            );
            $target = $destination . \DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                if (!\is_dir($target)) {
                    \mkdir($target, 0777, true);
                }

                continue;
            }

            \copy($item->getPathname(), $target);
        }
    }

    private static function requireComposer(): void
    {
        $root = \sys_get_temp_dir()
            . '/coretsia-composer-probe-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root, 0777, true);

        try {
            $result = new DependencySyncProcessRunner()
                ->runComposer(
                    $root,
                    [
                        '--version',
                        '--no-ansi',
                    ],
                    30,
                );

            self::assertFalse(
                $result['timedOut'],
                'Bare Composer executable timed out during real metadata acceptance probe.',
            );
            self::assertSame(
                0,
                $result['exitCode'],
                $result['stdout'] . $result['stderr'],
            );
        } finally {
            @\rmdir($root);
        }
    }

    private static function remove(string $path): void
    {
        if (\is_link($path) || \is_file($path)) {
            @\unlink($path);

            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }

        @\rmdir($path);
    }
}
