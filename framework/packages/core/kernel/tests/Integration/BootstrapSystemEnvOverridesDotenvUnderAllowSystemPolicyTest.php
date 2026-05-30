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

final class BootstrapSystemEnvOverridesDotenvUnderAllowSystemPolicyTest extends TestCase
{
    private static int $sequence = 0;

    public function testAllowSystemPolicyLetsSystemEnvOverrideDotenvAndUseDotenvAsFallback(): void
    {
        $skeletonRoot = self::createSkeletonRoot('allow-system-precedence');
        $suffix = self::uniqueSuffix();

        $conflict = self::envName($suffix, 'CONFLICT');
        $dotenvOnly = self::envName($suffix, 'DOTENV_ONLY');
        $systemOnly = self::envName($suffix, 'SYSTEM_ONLY');
        $systemEmpty = self::envName($suffix, 'SYSTEM_EMPTY');
        $missing = self::envName($suffix, 'MISSING');

        try {
            self::writeDotenvFile($skeletonRoot . '/.env', [
                $conflict . '=dotenv-base',
                $dotenvOnly . '=dotenv-base',
                $systemEmpty . '=dotenv-non-empty',
            ]);

            self::writeDotenvFile($skeletonRoot . '/.env.test.local', [
                $conflict . '=dotenv-final-must-not-win',
                $dotenvOnly . '=dotenv-final-fallback',
            ]);

            self::withSystemEnv(
                values: [
                    $conflict => 'system-wins',
                    $systemOnly => 'system-only-value',
                    $systemEmpty => '',
                ],
                unsetNames: [
                    $dotenvOnly,
                    $missing,
                ],
                callback: static function () use (
                    $skeletonRoot,
                    $conflict,
                    $dotenvOnly,
                    $systemOnly,
                    $systemEmpty,
                    $missing,
                ): void {
                    $repository = self::buildAllowSystemRepository($skeletonRoot);

                    self::assertTrue($repository->has($conflict));
                    self::assertSame('system-wins', $repository->get($conflict)->value());

                    self::assertTrue($repository->has($dotenvOnly));
                    self::assertSame('dotenv-final-fallback', $repository->get($dotenvOnly)->value());

                    self::assertTrue($repository->has($systemOnly));
                    self::assertSame('system-only-value', $repository->get($systemOnly)->value());

                    $empty = $repository->get($systemEmpty);

                    self::assertTrue($repository->has($systemEmpty));
                    self::assertTrue($empty->isPresent());
                    self::assertTrue($empty->isEmptyString());
                    self::assertSame('', $empty->value());

                    $missingValue = $repository->get($missing);

                    self::assertFalse($repository->has($missing));
                    self::assertTrue($missingValue->isMissing());
                    self::assertNull($repository->sourceOf($missing));
                },
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testAllowSystemPolicyUsesSystemSourceMetadataForOverriddenAndSystemOnlyKeys(): void
    {
        $skeletonRoot = self::createSkeletonRoot('system-source-metadata');
        $suffix = self::uniqueSuffix();

        $conflict = self::envName($suffix, 'CONFLICT_SECRET');
        $systemOnly = self::envName($suffix, 'SYSTEM_ONLY_SECRET');

        try {
            self::writeDotenvFile($skeletonRoot . '/.env.test.local', [
                $conflict . '=dotenv-secret-must-not-win',
            ]);

            self::withSystemEnv(
                values: [
                    $conflict => 'system-secret-wins',
                    $systemOnly => 'system-only-secret',
                ],
                unsetNames: [],
                callback: static function () use (
                    $skeletonRoot,
                    $conflict,
                    $systemOnly,
                ): void {
                    $repository = self::buildAllowSystemRepository($skeletonRoot);

                    self::assertSame('system-secret-wins', $repository->get($conflict)->value());
                    self::assertSame('system-only-secret', $repository->get($systemOnly)->value());

                    foreach ([$conflict, $systemOnly] as $name) {
                        $source = $repository->sourceOf($name);

                        self::assertNotNull($source);
                        self::assertSame(ConfigSourceType::Env, $source->type());
                        self::assertSame('env', $source->root());
                        self::assertSame('system_env', $source->sourceId());
                        self::assertNull($source->path());
                        self::assertSame($name, $source->keyPath());
                        self::assertSame(10, $source->precedence());
                        self::assertTrue($source->isRedacted());
                        self::assertSame(
                            [
                                'source' => 'system_env',
                            ],
                            $source->meta(),
                        );

                        $sourcePayload = \json_encode($source->toArray(), \JSON_THROW_ON_ERROR);

                        self::assertStringNotContainsString('system-secret-wins', $sourcePayload);
                        self::assertStringNotContainsString('system-only-secret', $sourcePayload);
                        self::assertStringNotContainsString('dotenv-secret-must-not-win', $sourcePayload);
                        self::assertStringNotContainsString($skeletonRoot, $sourcePayload);
                    }
                },
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    public function testAllowSystemPolicyKeepsDotenvSourceMetadataForDotenvFallbackKeys(): void
    {
        $skeletonRoot = self::createSkeletonRoot('dotenv-fallback-source-metadata');
        $suffix = self::uniqueSuffix();

        $dotenvOnly = self::envName($suffix, 'DOTENV_ONLY_SECRET');

        try {
            self::writeDotenvFile($skeletonRoot . '/.env', [
                $dotenvOnly . '=base-dotenv-secret',
            ]);

            self::writeDotenvFile($skeletonRoot . '/.env.test.local', [
                $dotenvOnly . '=final-dotenv-secret',
            ]);

            self::withSystemEnv(
                values: [],
                unsetNames: [
                    $dotenvOnly,
                ],
                callback: static function () use ($skeletonRoot, $dotenvOnly): void {
                    $repository = self::buildAllowSystemRepository($skeletonRoot);

                    self::assertSame('final-dotenv-secret', $repository->get($dotenvOnly)->value());

                    $source = $repository->sourceOf($dotenvOnly);

                    self::assertNotNull($source);
                    self::assertSame(ConfigSourceType::Dotenv, $source->type());
                    self::assertSame('env', $source->root());
                    self::assertSame('dotenv/.env.test.local', $source->sourceId());
                    self::assertSame('.env.test.local', $source->path());
                    self::assertSame($dotenvOnly, $source->keyPath());
                    self::assertSame(0, $source->precedence());
                    self::assertTrue($source->isRedacted());
                    self::assertSame(
                        [
                            'name' => '.env.test.local',
                        ],
                        $source->meta(),
                    );

                    $sourcePayload = \json_encode($source->toArray(), \JSON_THROW_ON_ERROR);

                    self::assertStringNotContainsString('base-dotenv-secret', $sourcePayload);
                    self::assertStringNotContainsString('final-dotenv-secret', $sourcePayload);
                    self::assertStringNotContainsString($skeletonRoot, $sourcePayload);
                },
            );
        } finally {
            self::removeDirectory($skeletonRoot);
        }
    }

    private static function buildAllowSystemRepository(string $skeletonRoot): EnvRepositoryInterface
    {
        $config = new BootstrapConfig(
            appEnv: 'test',
            preset: 'micro',
            debug: false,
            envSourcePolicy: BootstrapEnvSourcePolicy::AllowSystem,
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
     * @param list<string> $unsetNames
     * @param callable(): void $callback
     */
    private static function withSystemEnv(
        array $values,
        array $unsetNames,
        callable $callback,
    ): void {
        $names = \array_values(\array_unique([
            ...\array_keys($values),
            ...$unsetNames,
        ]));

        $previousGetenv = [];
        $previousEnv = [];
        $previousServer = [];

        foreach ($names as $name) {
            $previousGetenv[$name] = \getenv($name);
            $previousEnv[$name] = [
                'exists' => \array_key_exists($name, $_ENV),
                'value' => $_ENV[$name] ?? null,
            ];
            $previousServer[$name] = [
                'exists' => \array_key_exists($name, $_SERVER),
                'value' => $_SERVER[$name] ?? null,
            ];
        }

        foreach ($unsetNames as $name) {
            \putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        foreach ($values as $name => $value) {
            \putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        try {
            $callback();
        } finally {
            foreach ($names as $name) {
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
            . '/coretsia-kernel-bootstrap-allow-system/'
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

    private static function uniqueSuffix(): string
    {
        return 'P' . \getmypid() . '_' . (++self::$sequence);
    }

    private static function envName(string $suffix, string $name): string
    {
        return 'CORETSIA_ALLOW_SYSTEM_' . $suffix . '_' . $name;
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
