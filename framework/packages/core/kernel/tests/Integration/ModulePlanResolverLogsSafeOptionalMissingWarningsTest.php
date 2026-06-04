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

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ModulePlanResolverLogsSafeOptionalMissingWarningsTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = self::createTempDirectory();
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->tempRoot);
    }

    public function testLogsSafeOptionalMissingWarningContext(): void
    {
        $packageRoot = $this->tempRoot . '/package';
        $skeletonRoot = $this->tempRoot . '/skeleton';
        $logger = self::recordingLogger();

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: [
                'schemaVersion' => 1,
                'name' => 'micro',
                'description' => 'Micro test mode.',
                'required' => [
                    'core.kernel',
                ],
                'optional' => [
                    'platform.logging',
                ],
                'disabled' => [],
                'featureBundles' => [],
                'metadata' => [],
            ],
        );

        $resolver = new ModulePlanResolver(
            presetLoaderFactory: new ModePresetLoaderFactory(
                packageRoot: $packageRoot,
                modesConfig: [
                    'schema_version' => 1,
                    'defaults_path' => 'resources/modes',
                    'overrides_path' => 'config/modes',
                ],
                schemaValidator: new ModePresetSchemaValidator(),
            ),
            manifestReader: self::manifestReader(
                new ModuleManifest([
                    new ModuleDescriptor(
                        id: ModuleId::fromString('core.kernel'),
                        composerName: 'coretsia/core-kernel',
                        packageKind: 'runtime',
                        moduleClass: null,
                        capabilities: [],
                        metadata: [
                            'conflicts' => [],
                            'requires' => [],
                        ],
                    ),
                ]),
            ),
            graphResolver: new ModuleGraphResolver(new TopologicalSorter()),
            meter: self::nullMeter(),
            stopwatch: new Stopwatch(),
            modulesConfig: [
                'discovery' => [
                    'source' => 'composer',
                    'allowed_sources' => [
                        'composer',
                    ],
                ],
            ],
            logger: $logger,
        );

        $plan = $resolver->resolve(
            new BootstrapConfig(
                appEnv: 'local',
                preset: 'micro',
                debug: false,
                envSourcePolicy: BootstrapEnvSourcePolicy::from('strict_dotenv'),
                appTarget: AppTarget::from('api'),
                skeletonRoot: $skeletonRoot,
            ),
        );

        self::assertCount(1, $plan->warnings());
        $warningPayload = $plan->warnings()[0]->toArray();

        self::assertSame(
            [
                [
                    'level' => 'warning',
                    'message' => 'coretsia.kernel.modules.optional_missing',
                    'context' => [
                        'code' => $warningPayload['code'],
                        'reason' => $warningPayload['reason'],
                        'presetName' => 'micro',
                        'moduleIds' => [
                            'platform.logging',
                        ],
                    ],
                ],
            ],
            $logger->records,
        );

        self::assertLogContextIsSafe($logger->records[0]['context']);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function assertLogContextIsSafe(array $context): void
    {
        self::assertSame(
            [
                'code',
                'reason',
                'presetName',
                'moduleIds',
            ],
            \array_keys($context),
        );

        self::assertSame(['platform.logging'], $context['moduleIds']);

        $encoded = \json_encode($context, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('/tmp', $encoded);
        self::assertStringNotContainsString('/var', $encoded);
        self::assertStringNotContainsString('\\', $encoded);
        self::assertStringNotContainsString('resources/modes', $encoded);
        self::assertStringNotContainsString('config/modes', $encoded);
        self::assertStringNotContainsString('coretsia/core-kernel', $encoded);
        self::assertStringNotContainsString('exception', \strtolower($encoded));
        self::assertStringNotContainsString('trace', \strtolower($encoded));
        self::assertStringNotContainsString('stack', \strtolower($encoded));
        self::assertStringNotContainsString('payload', \strtolower($encoded));
        self::assertStringNotContainsString('secret', \strtolower($encoded));
        self::assertStringNotContainsString('token', \strtolower($encoded));
    }

    private static function manifestReader(ModuleManifest $manifest): ManifestReaderInterface
    {
        return new class($manifest) implements ManifestReaderInterface {
            public function __construct(
                private ModuleManifest $manifest,
            ) {
            }

            public function read(): ModuleManifest
            {
                return $this->manifest;
            }
        };
    }

    private static function nullMeter(): MeterPortInterface
    {
        return new class() implements MeterPortInterface {
            public function increment(string $name, int $delta = 1, array $labels = []): void
            {
            }

            public function observe(string $name, int $value, array $labels = []): void
            {
            }
        };
    }

    private static function recordingLogger(): object
    {
        return new class() extends AbstractLogger {
            /**
             * @var list<array{level: string, message: string|\Stringable, context: array<string, mixed>}>
             */
            public array $records = [];

            /**
             * @param array<string, mixed> $context
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => (string)$level,
                    'message' => $message,
                    'context' => $context,
                ];
            }
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function writePresetFile(string $directory, string $name, array $payload): void
    {
        self::writeFile(
            $directory . '/' . $name . '.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . \var_export($payload, true) . ";\n",
        );
    }

    private static function writeFile(string $file, string $contents): void
    {
        $directory = \dirname($file);

        if (!\is_dir($directory) && !\mkdir($directory, 0777, true) && !\is_dir($directory)) {
            throw new \RuntimeException('test-directory-create-failed');
        }

        if (\file_put_contents($file, $contents) === false) {
            throw new \RuntimeException('test-file-write-failed');
        }
    }

    private static function createTempDirectory(): string
    {
        $directory = \sys_get_temp_dir()
            . '/coretsia-module-plan-safe-optional-warning-logs-'
            . \bin2hex(\random_bytes(8));

        if (!\mkdir($directory, 0777, true) && !\is_dir($directory)) {
            throw new \RuntimeException('test-temp-directory-create-failed');
        }

        return $directory;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!\is_dir($directory)) {
            return;
        }

        $entries = \scandir($directory);

        if (!\is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (\is_dir($path)) {
                self::removeDirectory($path);

                continue;
            }

            @\unlink($path);
        }

        @\rmdir($directory);
    }
}
