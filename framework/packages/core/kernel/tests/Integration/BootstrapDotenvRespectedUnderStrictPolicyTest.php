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

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use PHPUnit\Framework\TestCase;

final class BootstrapDotenvRespectedUnderStrictPolicyTest extends TestCase
{
    private static int $sequence = 0;

    public function testStrictDotenvUsesDotenvValuesAndForbidsSystemFallback(): void
    {
        $skeletonRoot = self::createSkeletonRoot('strict-dotenv-precedence');

        try {
            self::writeDotenvFile($skeletonRoot . '/.env', [
                'CORETSIA_CONFLICT=dotenv-base',
                'CORETSIA_DOTENV_ONLY=from-dotenv',
                'CORETSIA_EMPTY=',
                'CORETSIA_SHARED=base',
            ]);

            self::writeDotenvFile($skeletonRoot . '/.env.local', [
                'CORETSIA_SHARED=local',
            ]);

            self::writeDotenvFile($skeletonRoot . '/.env.test', [
                'CORETSIA_SHARED=test',
            ]);

            self::writeDotenvFile($skeletonRoot . '/.env.test.local', [
                'CORETSIA_SHARED=test-local',
                'CORETSIA_FINAL=final-dotenv',
            ]);

            self::withSystemEnv(
                [
                    'CORETSIA_CONFLICT' => 'system-conflict-must-not-win',
                    'CORETSIA_EMPTY' => 'system-empty-must-not-win',
                    'CORETSIA_SYSTEM_ONLY' => 'system-only-must-remain-missing',
                ],
                static function () use ($skeletonRoot): void {
                    $repository = self::buildStrictDotenvRepository($skeletonRoot);

                    self::assertSame(
                        [
                            'CORETSIA_CONFLICT' => 'dotenv-base',
                            'CORETSIA_DOTENV_ONLY' => 'from-dotenv',
                            'CORETSIA_EMPTY' => '',
                            'CORETSIA_FINAL' => 'final-dotenv',
                            'CORETSIA_SHARED' => 'test-local',
                        ],
                        $repository->all(),
                    );

                    self::assertTrue($repository->has('CORETSIA_CONFLICT'));
                    self::assertSame('dotenv-base', $repository->get('CORETSIA_CONFLICT')->value());

                    self::assertTrue($repository->has('CORETSIA_SHARED'));
                    self::assertSame('test-local', $repository->get('CORETSIA_SHARED')->value());

                    self::assertFalse($repository->has('CORETSIA_SYSTEM_ONLY'));
                    self::assertTrue($repository->get('CORETSIA_SYSTEM_ONLY')->isMissing());
                    self::assertNull($repository->sourceOf('CORETSIA_SYSTEM_ONLY'));
                },
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testStrictDotenvPreservesPresentEmptyStringAndMissingAsDistinctStates(): void
    {
        $skeletonRoot = self::createSkeletonRoot('present-empty-string');

        try {
            self::writeDotenvFile($skeletonRoot . '/.env.test', [
                'CORETSIA_EMPTY=',
                'CORETSIA_PRESENT=value',
            ]);

            $repository = self::buildStrictDotenvRepository($skeletonRoot);

            $empty = $repository->get('CORETSIA_EMPTY');

            self::assertTrue($repository->has('CORETSIA_EMPTY'));
            self::assertTrue($empty->isPresent());
            self::assertFalse($empty->isMissing());
            self::assertTrue($empty->isEmptyString());
            self::assertSame('', $empty->value());

            $present = $repository->get('CORETSIA_PRESENT');

            self::assertTrue($repository->has('CORETSIA_PRESENT'));
            self::assertTrue($present->isPresent());
            self::assertFalse($present->isEmptyString());
            self::assertSame('value', $present->value());

            $missing = $repository->get('CORETSIA_MISSING');

            self::assertFalse($repository->has('CORETSIA_MISSING'));
            self::assertTrue($missing->isMissing());
            self::assertFalse($missing->isPresent());
            self::assertFalse($missing->isEmptyString());
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testStrictDotenvAttachesSafeDotenvSourceMetadataOnly(): void
    {
        $skeletonRoot = self::createSkeletonRoot('safe-source-metadata');

        try {
            self::writeDotenvFile($skeletonRoot . '/.env', [
                'CORETSIA_SECRET=base-secret-value',
            ]);

            self::writeDotenvFile($skeletonRoot . '/.env.test.local', [
                'CORETSIA_SECRET=final-secret-value',
            ]);

            $repository = self::buildStrictDotenvRepository($skeletonRoot);

            self::assertSame('final-secret-value', $repository->get('CORETSIA_SECRET')->value());

            $source = $repository->sourceOf('CORETSIA_SECRET');

            self::assertNotNull($source);
            self::assertSame(ConfigSourceType::Dotenv, $source->type());
            self::assertSame('env', $source->root());
            self::assertSame('dotenv/.env.test.local', $source->sourceId());
            self::assertSame('.env.test.local', $source->path());
            self::assertSame('CORETSIA_SECRET', $source->keyPath());
            self::assertSame(0, $source->precedence());
            self::assertTrue($source->isRedacted());
            self::assertSame(
                [
                    'name' => '.env.test.local',
                ],
                $source->meta(),
            );

            $sourcePayload = \json_encode($source->toArray(), \JSON_THROW_ON_ERROR);

            self::assertStringNotContainsString('base-secret-value', $sourcePayload);
            self::assertStringNotContainsString('final-secret-value', $sourcePayload);
            self::assertStringNotContainsString($skeletonRoot, $sourcePayload);
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    private static function buildStrictDotenvRepository(string $skeletonRoot): EnvRepositoryInterface
    {
        $config = new BootstrapConfig(
            appEnv: 'test',
            preset: 'micro',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            skeletonRoot: $skeletonRoot,
        );

        return new EnvRepositoryBuilder(
            dotenvLoader: new DotenvLoader(),
        )->build(
            config: $config,
            kernelConfig: self::kernelConfig(),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelConfig(): array
    {
        $config = require self::kernelRoot() . '/config/kernel.php';

        self::assertIsArray($config);

        /**
         * @var array<string,mixed> $config
         */
        return $config;
    }

    /**
     * @param array<string,string> $values
     * @param callable(): void $callback
     */
    private static function withSystemEnv(array $values, callable $callback): void
    {
        $previousGetenv = [];
        $previousEnv = [];
        $previousServer = [];

        foreach ($values as $name => $value) {
            $previousGetenv[$name] = \getenv($name);
            $previousEnv[$name] = [
                'exists' => \array_key_exists($name, $_ENV),
                'value' => $_ENV[$name] ?? null,
            ];
            $previousServer[$name] = [
                'exists' => \array_key_exists($name, $_SERVER),
                'value' => $_SERVER[$name] ?? null,
            ];

            \putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        try {
            $callback();
        } finally {
            foreach (\array_keys($values) as $name) {
                $previous = $previousGetenv[$name];

                if ($previous === false) {
                    \putenv($name);
                } else {
                    \putenv($name . '=' . $previous);
                }

                if ($previousEnv[$name]['exists']) {
                    $_ENV[$name] = $previousEnv[$name]['value'];
                } else {
                    unset($_ENV[$name]);
                }

                if ($previousServer[$name]['exists']) {
                    $_SERVER[$name] = $previousServer[$name]['value'];
                } else {
                    unset($_SERVER[$name]);
                }
            }
        }
    }

    /**
     * @param list<string> $lines
     */
    private static function writeDotenvFile(string $file, array $lines): void
    {
        self::ensureDirectory(\dirname($file));

        $written = \file_put_contents($file, \implode("\n", $lines) . "\n");

        self::assertIsInt($written);
    }

    private static function createSkeletonRoot(string $case): string
    {
        $root = self::normalizePath(
            \sys_get_temp_dir()
            . '/coretsia-kernel-bootstrap-strict-dotenv/'
            . \getmypid()
            . '/'
            . (++self::$sequence)
            . '-'
            . $case,
        );

        self::removeDirectory($root);
        self::ensureDirectory($root);

        return $root;
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

    private static function kernelRoot(): string
    {
        $root = \realpath(__DIR__ . '/../..');

        self::assertIsString($root);

        return self::normalizePath($root);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
