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

namespace Coretsia\Platform\Logging\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Platform\Logging\Provider\LoggingServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class FoundationNoopResolvesWhenDisabledTest extends TestCase
{
    public function testFoundationAloneResolvesItsOwnNoopWithoutSelectingLoggingModule(): void
    {
        $builder = new ContainerBuilder([
            'foundation' => [],
            'logging' => require \dirname(__DIR__, 2) . '/config/logging.php',
        ]);
        $builder->register(new FoundationServiceProvider());
        $container = $builder->build();

        self::assertInstanceOf(NoopLogger::class, $container->get(LoggerInterface::class));
        self::assertSame($container->get(LoggerInterface::class), $container->get(LoggerInterface::class));
        self::assertFalse($container->has('logging.logger'));

        $message = new class() implements \Stringable {
            public function __toString(): string
            {
                throw new \LogicException('noop-must-not-evaluate-message');
            }
        };
        $resource = \fopen('php://memory', 'rb');
        self::assertIsResource($resource);

        try {
            $container->get(LoggerInterface::class)->log(null, $message, [
                'exception' => new \RuntimeException('opaque'),
                'resource' => $resource,
            ]);
        } finally {
            \fclose($resource);
        }
    }

    public function testSelectedNullDriverKeepsFoundationNoopAndDoesNotRequireFileDirectory(): void
    {
        $missingDirectory = \rtrim(\sys_get_temp_dir(), '/\\') . \DIRECTORY_SEPARATOR
            . 'coretsia-disabled-' . \bin2hex(\random_bytes(8));
        $builder = self::nullDriverBuilder($missingDirectory);
        $container = $builder->build();

        self::assertDirectoryDoesNotExist($missingDirectory);
        self::assertInstanceOf(NoopLogger::class, $container->get(LoggerInterface::class));
        self::assertSame($container->get(LoggerInterface::class), $container->get(LoggerInterface::class));
        $container->get(LoggerInterface::class)->info('kernel.boot', ['path' => $missingDirectory]);
        self::assertDirectoryDoesNotExist($missingDirectory);
    }

    public function testNullDriverNeverOpensFixedStderrOrFilesystemInChildProcess(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $script = <<<'PHP_CODE'
require $argv[1];
require $argv[2];
class DisabledLoggingStreamProbe {
    public $context;
    public static array $opens = [];
    public function stream_open($path, $mode, $options, &$opened_path): bool {
        self::$opens[] = [$path, $mode];
        return false;
    }
    public function stream_close(): void
    {
    }
}
$directory = $argv[3];
stream_wrapper_unregister('php');
stream_wrapper_register('php', DisabledLoggingStreamProbe::class);
try {
    $method = new \ReflectionMethod(\Coretsia\Platform\Logging\Tests\Integration\FoundationNoopResolvesWhenDisabledTest::class, 'nullDriverBuilder');
    $builder = $method->invoke(null, $directory);
    $logger = $builder->build()->get(\Psr\Log\LoggerInterface::class);
    $logger->warning('kernel.boot');
    $isNoop = $logger instanceof \Coretsia\Foundation\Logging\NoopLogger;
} finally {
    stream_wrapper_restore('php');
}
echo json_encode(['noop' => $isNoop, 'opens' => DisabledLoggingStreamProbe::$opens, 'exists' => is_dir($directory)], JSON_THROW_ON_ERROR);
PHP_CODE;
        $directory = \rtrim(\sys_get_temp_dir(), '/\\') . \DIRECTORY_SEPARATOR
            . 'coretsia-disabled-probe-' . \bin2hex(\random_bytes(8));
        $process = \proc_open(
            [\PHP_BINARY, '-d', 'display_errors=1', '-r', $script, $autoload, __FILE__, $directory],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        \fclose($pipes[0]);
        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        self::assertSame(0, \proc_close($process), $stderr);
        self::assertSame('', $stderr);
        self::assertSame(
            ['noop' => true, 'opens' => [], 'exists' => false],
            \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR),
        );
        self::assertDirectoryDoesNotExist($directory);
    }

    public function testNullDriverLeavesAnExistingInactiveFileDirectoryUntouched(): void
    {
        $directory = \rtrim(\sys_get_temp_dir(), '/\\') . \DIRECTORY_SEPARATOR
            . 'coretsia-disabled-existing-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($directory, 0700));
        $existingFile = $directory . \DIRECTORY_SEPARATOR . 'web-app-2026-10-31.jsonl';

        try {
            self::assertSame(9, \file_put_contents($existingFile, 'unchanged'));
            $container = self::nullDriverBuilder($directory)->build();
            $logger = $container->get(LoggerInterface::class);
            self::assertInstanceOf(NoopLogger::class, $logger);
            self::assertSame(
                [\realpath($existingFile)],
                \array_map('realpath', \glob($directory . '/*') ?: []),
            );

            $logger->info('kernel.boot', ['operation' => 'boot']);
            $logger->error('kernel.failed', ['path' => $directory]);

            self::assertSame(
                [\realpath($existingFile)],
                \array_map('realpath', \glob($directory . '/*') ?: []),
            );
            self::assertSame('unchanged', \file_get_contents($existingFile));
        } finally {
            foreach (\glob($directory . '/*') ?: [] as $file) {
                \unlink($file);
            }
            \rmdir($directory);
        }
    }

    private static function nullDriverBuilder(string $fileDirectory): ContainerBuilder
    {
        $builder = new ContainerBuilder(['foundation' => []]);
        $config = new class($fileDirectory) implements ConfigRepositoryInterface {
            private array $values;

            public function __construct(string $directory)
            {
                $this->values = [
                    'logging.driver' => 'null',
                    'logging.file' => ['path' => $directory, 'name' => 'app'],
                    'logging.file.path' => $directory,
                    'logging.file.name' => 'app',
                    'logging.level' => 'info',
                    'logging.max_record_bytes' => 65536,
                    'logging.service_name' => 'auto',
                    'logging.format' => 'jsonl',
                ];
            }

            public function has(string $keyPath): bool
            {
                return \array_key_exists($keyPath, $this->values);
            }

            public function get(string $keyPath, mixed $default = null): mixed
            {
                return $this->values[$keyPath] ?? $default;
            }

            public function all(): array
            {
                return [];
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                return null;
            }

            public function explain(): array
            {
                return [];
            }
        };
        $builder->register(new FoundationServiceProvider(), new LoggingServiceProvider());
        $builder->instance(ConfigRepositoryInterface::class, $config);
        $builder->instance(
            AppTargetProviderInterface::class,
            new class() implements AppTargetProviderInterface {
                public function app(): string
                {
                    return 'web';
                }
            },
        );
        $builder->instance(
            SensitiveKeyClassifierInterface::class,
            new class() implements SensitiveKeyClassifierInterface {
                public function classify(string $key): ?RedactionKind
                {
                    return null;
                }
            },
        );
        $builder->instance(
            SensitiveDataRedactorInterface::class,
            new class() implements SensitiveDataRedactorInterface {
                public function redactValue(
                    string $value,
                    RedactionKind $kind,
                    RedactionContext $context,
                ): RedactedValue {
                    throw new \LogicException('noop-must-not-redact');
                }

                public function redactJsonLike(mixed $value, RedactionContext $context): mixed
                {
                    throw new \LogicException('noop-must-not-redact');
                }
            },
        );

        return $builder;
    }
}
