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
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\BootstrapSourceResolver;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\Module\ResolvedModuleOverrides;
use PHPUnit\Framework\TestCase;

final class BootstrapDotenvRespectedUnderStrictPolicyTest extends TestCase
{
    private static int $sequence = 0;

    public function testStrictDotenvUsesDotenvValuesAndForbidsSystemFallback(): void
    {
        $applicationRoot = self::createApplicationRoot('strict-dotenv-precedence');

        try {
            self::writeDotenvFile($applicationRoot . '/.env', [
                'CORETSIA_CONFLICT=dotenv-base',
                'CORETSIA_DOTENV_ONLY=from-dotenv',
                'CORETSIA_EMPTY=',
                'CORETSIA_SHARED=base',
            ]);

            self::writeDotenvFile($applicationRoot . '/.env.local', [
                'CORETSIA_SHARED=local',
            ]);

            self::writeDotenvFile($applicationRoot . '/.env.test', [
                'CORETSIA_SHARED=test',
            ]);

            self::writeDotenvFile($applicationRoot . '/.env.test.local', [
                'CORETSIA_SHARED=test-local',
                'CORETSIA_FINAL=final-dotenv',
            ]);

            self::withSystemEnv(
                [
                    'CORETSIA_CONFLICT' => 'system-conflict-must-not-win',
                    'CORETSIA_EMPTY' => 'system-empty-must-not-win',
                    'CORETSIA_SYSTEM_ONLY' => 'system-only-must-remain-missing',
                ],
                static function () use ($applicationRoot): void {
                    $repository = self::buildStrictDotenvRepository($applicationRoot);

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
            self::removeDirectory($applicationRoot);
        }
    }

    public function testStrictDotenvPreservesPresentEmptyStringAndMissingAsDistinctStates(): void
    {
        $applicationRoot = self::createApplicationRoot('present-empty-string');

        try {
            self::writeDotenvFile($applicationRoot . '/.env.test', [
                'CORETSIA_EMPTY=',
                'CORETSIA_PRESENT=value',
            ]);

            $repository = self::buildStrictDotenvRepository($applicationRoot);

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
            self::removeDirectory($applicationRoot);
        }
    }

    public function testStrictDotenvAttachesSafeDotenvSourceMetadataOnly(): void
    {
        $applicationRoot = self::createApplicationRoot('safe-source-metadata');

        try {
            self::writeDotenvFile($applicationRoot . '/.env', [
                'CORETSIA_SECRET=base-secret-value',
            ]);

            self::writeDotenvFile($applicationRoot . '/.env.test.local', [
                'CORETSIA_SECRET=final-secret-value',
            ]);

            $repository = self::buildStrictDotenvRepository($applicationRoot);

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
            self::assertStringNotContainsString($applicationRoot, $sourcePayload);
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testStrictPhaseAFreezesAppEnvAndIgnoresSystemBootstrapOverrides(): void
    {
        $applicationRoot = self::createApplicationRoot('strict-phase-a');

        try {
            self::writeDotenvFile($applicationRoot . '/.env', [
                'CORETSIA_BOOT_APP_ENV=test',
                'CORETSIA_BOOT_PRESET=micro',
                'CORETSIA_BOOT_ACTIVE_TARGETS=["web","worker"]',
            ]);
            self::writeDotenvFile($applicationRoot . '/.env.test.local', [
                'CORETSIA_BOOT_PRESETS={"worker":"enterprise"}',
                'CORETSIA_BOOT_DEBUG=false',
            ]);

            self::withSystemEnv(
                [
                    'CORETSIA_BOOT_APP_ENV' => 'production',
                    'CORETSIA_BOOT_PRESET' => 'hybrid',
                    'CORETSIA_BOOT_UNKNOWN_SECRET' => 'sensitive-system-value',
                ],
                static function () use ($applicationRoot): void {
                    $snapshot = self::sourceResolver()->resolveApplicationRoot(
                        $applicationRoot,
                        self::kernelConfig(),
                    );

                    self::assertSame('test', $snapshot->appEnv());
                    self::assertSame(BootstrapEnvSourcePolicy::StrictDotenv, $snapshot->envSourcePolicy());
                    self::assertSame(['web', 'worker'], $snapshot->activeTargets());
                    self::assertSame('micro', $snapshot->overrides()['preset']);
                    self::assertSame('enterprise', $snapshot->overrides()['presets']['worker']);
                    self::assertFalse($snapshot->envRepository()->has('CORETSIA_BOOT_UNKNOWN_SECRET'));
                },
            );
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testStrictPhaseASelectsAppEnvFromHighestPriorityBaseDotenvLayer(): void
    {
        $applicationRoot = self::createApplicationRoot('strict-base-layer-selection');

        try {
            self::writeDotenvFile($applicationRoot . '/.env', [
                'CORETSIA_BOOT_APP_ENV=staging',
                'CORETSIA_BOOT_PRESET=micro',
            ]);
            self::writeDotenvFile($applicationRoot . '/.env.local', [
                'CORETSIA_BOOT_APP_ENV=test',
            ]);
            self::writeDotenvFile($applicationRoot . '/.env.test', [
                'CORETSIA_BOOT_PRESET=hybrid',
            ]);
            self::writeDotenvFile($applicationRoot . '/.env.test.local', [
                'CORETSIA_BOOT_PRESET=enterprise',
            ]);

            $snapshot = self::sourceResolver()->resolveApplicationRoot(
                $applicationRoot,
                self::kernelConfig(),
            );

            self::assertSame('test', $snapshot->appEnv());
            self::assertSame(BootstrapEnvSourcePolicy::StrictDotenv, $snapshot->envSourcePolicy());
            self::assertSame('enterprise', $snapshot->overrides()['preset']);
            self::assertSame('dotenv/.env.local', $snapshot->sourceProvenance()['appEnv']->sourceId());
            self::assertSame('dotenv/.env.test.local', $snapshot->sourceProvenance()['preset']->sourceId());
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    public function testStrictPhaseARejectsEnvironmentSpecificAppEnvMismatch(): void
    {
        $applicationRoot = self::createApplicationRoot('strict-app-env-mismatch');

        try {
            self::writeDotenvFile($applicationRoot . '/.env', ['CORETSIA_BOOT_APP_ENV=test']);
            self::writeDotenvFile($applicationRoot . '/.env.test', ['CORETSIA_BOOT_APP_ENV=production']);

            try {
                self::sourceResolver()->resolveApplicationRoot($applicationRoot, self::kernelConfig());
                self::fail('Environment-specific dotenv must not reselect the frozen appEnv.');
            } catch (BootstrapException $exception) {
                self::assertSame(BootstrapException::REASON_OVERRIDES_INVALID, $exception->reason());
                self::assertStringNotContainsString($applicationRoot, $exception->getMessage());
            }
        } finally {
            self::removeDirectory($applicationRoot);
        }
    }

    private static function sourceResolver(): BootstrapSourceResolver
    {
        $dotenvLoader = new DotenvLoader();

        return new BootstrapSourceResolver(
            new BootstrapOverridesLoader(),
            $dotenvLoader,
            new EnvRepositoryBuilder($dotenvLoader),
        );
    }

    private static function buildStrictDotenvRepository(string $applicationRoot): EnvRepositoryInterface
    {
        $config = new BootstrapConfig(
            appEnv: 'test',
            preset: 'micro',
            debug: false,
            artifactsCacheDir: 'var/cache',
            envSourcePolicy: BootstrapEnvSourcePolicy::StrictDotenv,
            appTarget: AppTarget::Web,
            applicationRoot: $applicationRoot,
            moduleOverrides: new ResolvedModuleOverrides([], []),
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

    private static function createApplicationRoot(string $case): string
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
