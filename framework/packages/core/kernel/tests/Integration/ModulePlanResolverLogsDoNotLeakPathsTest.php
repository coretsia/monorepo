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
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ModulePlanResolverLogsDoNotLeakPathsTest extends TestCase
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

    public function testFailureLogsDoNotLeakPathsRawPayloadsExceptionMessagesOrStackTraces(): void
    {
        $packageRoot = $this->tempRoot . '/package-root-secret-token';
        $skeletonRoot = $this->tempRoot . '/skeleton-root-secret-token';
        $logger = self::recordingLogger();

        self::writePresetFile(
            directory: $packageRoot . '/resources/modes',
            name: 'micro',
            payload: [
                'schemaVersion' => 1,
                'name' => 'micro',
                'description' => 'Micro test mode.',
                'required' => [
                    'platform.http',
                ],
                'optional' => [],
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

        try {
            $resolver->resolve(
                new BootstrapConfig(
                    appEnv: 'local',
                    preset: 'micro',
                    debug: false,
                    envSourcePolicy: BootstrapEnvSourcePolicy::from('strict_dotenv'),
                    appTarget: AppTarget::from('api'),
                    skeletonRoot: $skeletonRoot,
                ),
            );

            self::fail('Expected required missing failure.');
        } catch (ModuleRequiredMissingException $exception) {
            self::assertSame(
                ModuleRequiredMissingException::REASON_PRESET_REQUIRED_MODULE_MISSING,
                $exception->reason(),
            );
        }

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('coretsia.kernel.modules.resolve_failed', $logger->records[0]['message']);

        $context = $logger->records[0]['context'];

        self::assertSame(
            [
                'code',
                'reason',
                'presetName',
                'moduleIds',
            ],
            \array_keys($context),
        );

        self::assertSame('micro', $context['presetName']);
        self::assertSame(['platform.http'], $context['moduleIds']);

        self::assertSafeLogContext(
            context: $context,
            forbiddenFragments: [
                $this->tempRoot,
                $packageRoot,
                $skeletonRoot,
                'package-root-secret-token',
                'skeleton-root-secret-token',
                'resources/modes',
                'config/modes',
                'coretsia/core-kernel',
                'schemaVersion',
                'featureBundles',
                'metadata',
                'optional',
                'disabled',
                'RuntimeException',
                'Exception',
                'trace',
                'stack',
                'payload',
                'secret',
                'token',
                '/',
                '\\',
                '://',
                '..',
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $forbiddenFragments
     */
    private static function assertSafeLogContext(array $context, array $forbiddenFragments): void
    {
        $encoded = \json_encode($context, \JSON_THROW_ON_ERROR);

        foreach ($forbiddenFragments as $fragment) {
            self::assertStringNotContainsString($fragment, $encoded);
        }
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
            . '/coretsia-module-plan-no-path-logs-'
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
