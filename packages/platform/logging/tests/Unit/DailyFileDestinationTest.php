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

namespace Coretsia\Platform\Logging\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Provider\LoggingServiceFactory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class DailyFileDestinationTest extends TestCase
{
    public function testMissingDirectoryCanRecoverUsingTheIdenticalLoggerAndConfig(): void
    {
        $root = self::tempDirectory();
        $directory = $root . '/logs';
        try {
            [$logger, $config, $redactor] = self::logger($directory);
            self::assertInstanceOf(StreamLogger::class, $logger);
            $reads = [$config->hasReads, $config->getReads];
            $logger->info('kernel.boot');
            self::assertDirectoryDoesNotExist($directory);
            self::assertTrue(\mkdir($directory, 0700));
            $logger->info('kernel.boot');
            $file = $directory . '/web-app-2026-10-31.jsonl';
            self::assertFileExists($file);
            self::assertSame(1, \substr_count((string) \file_get_contents($file), "\n"));
            \unlink($file);
            \rmdir($directory);
            $logger->info('kernel.boot');
            self::assertDirectoryDoesNotExist($directory);
            self::assertTrue(\mkdir($directory, 0700));
            $logger->info('kernel.boot');
            self::assertFileExists($file);
            self::assertSame(4, $redactor->calls);
            self::assertSame($reads, [$config->hasReads, $config->getReads]);
        } finally {
            self::clean($root);
        }
    }

    public function testFilenameUsesCanonicalAppTargetUnchangedStemUtcDayAndFormat(): void
    {
        $root = self::tempDirectory();
        try {
            foreach (['web', 'api', 'console', 'worker'] as $app) {
                foreach (['jsonl', 'logfmt'] as $format) {
                    [$logger] = self::logger($root, $format, $app, 'audit', 'web-2026-10-31');
                    $logger->info('kernel.boot');
                    $file = $root . '/' . $app . '-web-2026-10-31-2026-10-31.' . $format;
                    self::assertFileExists($file);
                    $contents = (string) \file_get_contents($file);
                    self::assertStringContainsString(
                        $format === 'jsonl' ? '"app_target":"' . $app . '"' : 'app_target="' . $app . '"',
                        $contents,
                    );
                    self::assertStringContainsString(
                        $format === 'jsonl' ? '"service_name":"audit"' : 'service_name="audit"',
                        $contents,
                    );
                    self::assertSame(1, \substr_count($contents, "\n"));
                }
            }
            self::assertCount(8, \glob($root . '/*'));
        } finally {
            self::clean($root);
        }
    }

    public function testDailyAppendAndUtcRolloverNeverTruncateOrRotatePriorFile(): void
    {
        $root = self::tempDirectory();
        try {
            foreach (['jsonl', 'logfmt'] as $format) {
                [$logger] = self::logger($root, $format, dates: [
                    '2026-10-31T20:00:00Z',
                    '2026-10-31T20:00:00Z',
                    '2026-11-01T00:00:00Z',
                ]);
                $first = $root . '/web-app-2026-10-31.' . $format;
                $second = $root . '/web-app-2026-11-01.' . $format;
                $logger->info('kernel.boot');
                $original = (string) \file_get_contents($first);
                $logger->info('kernel.boot');
                self::assertSame($original . $original, \file_get_contents($first));
                $logger->info('kernel.boot');
                self::assertFileExists($second);
                self::assertSame($original . $original, \file_get_contents($first));
                self::assertSame(1, \substr_count((string) \file_get_contents($second), "\n"));
            }
        } finally {
            self::clean($root);
        }
    }

    public function testStreamAndNullDriversNeverCreateDailyFiles(): void
    {
        $root = self::tempDirectory();
        try {
            [$noop] = self::logger($root, driver: 'null');
            self::assertInstanceOf(NoopLogger::class, $noop);
            $noop->info('kernel.boot');
            [$stream] = self::logger($root, driver: 'stream');
            self::assertInstanceOf(StreamLogger::class, $stream);
            self::assertSame([], \glob($root . '/*'));

            $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
            self::assertFileExists($autoload);
            $script = <<<'PHP_CODE'
require $argv[1];
require $argv[2];
$method = new \ReflectionMethod(
    \Coretsia\Platform\Logging\Tests\Unit\DailyFileDestinationTest::class,
    'logger',
);
[$logger] = $method->invoke(null, $argv[3], 'jsonl', 'web', 'auto', 'app', ['2026-10-31T12:00:00Z'], 'stream');
$logger->info('kernel.boot');
PHP_CODE;
            $process = \proc_open(
                [\PHP_BINARY, '-r', $script, $autoload, __FILE__, $root],
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
            self::assertSame('', $stdout);
            self::assertSame(1, \substr_count($stderr, "\n"));
            self::assertStringContainsString('"app_target":"web"', $stderr);
            self::assertSame([], \glob($root . '/*'));
        } finally {
            self::clean($root);
        }
    }

    public function testSymlinkDirectoryAndNonRegularDestinationsAreRejectedWithoutFallback(): void
    {
        self::assertTrue(
            \function_exists('symlink'),
            'Symbolic link support is required for this filesystem security test.',
        );
        $root = self::tempDirectory();
        try {
            $target = $root . '/original';
            \file_put_contents($target, 'untouched');
            $destination = $root . '/web-app-2026-10-31.jsonl';
            self::assertTrue(
                @\symlink($target, $destination) && \is_link($destination),
                'Creating a file symbolic link failed. On Windows, enable Developer Mode or grant symlink privileges.',
            );
            [$logger] = self::logger($root);
            $logger->info('kernel.boot');
            self::assertSame('untouched', \file_get_contents($target));
            self::assertTrue(\is_link($destination));
            \unlink($destination);
            self::assertTrue(\mkdir($destination, 0700));
            $logger->info('kernel.boot');
            self::assertSame([], \glob($destination . '/*'));
            \rmdir($destination);
            $link = $root . '/linked-directory';
            self::assertTrue(
                @\symlink($root, $link) && \is_link($link),
                'Creating a directory symbolic link failed. On Windows, enable Developer Mode or grant symlink privileges.',
            );
            [$logger] = self::logger($link);
            $logger->info('kernel.boot');
            self::assertFileDoesNotExist($destination);
        } finally {
            self::clean($root);
        }
    }

    public function testNonRegularDestinationIsRejectedWithoutFallback(): void
    {
        $root = self::tempDirectory();

        try {
            $path = $root . '/web-app-2026-10-31.jsonl';

            if (\PHP_OS_FAMILY !== 'Windows' && \function_exists('posix_mkfifo')) {
                self::assertTrue(
                    @\posix_mkfifo($path, 0600),
                    'POSIX FIFO creation failed; named-pipe rejection must be exercised when supported.',
                );
                self::assertSame('fifo', \filetype($path));
            } else {
                self::assertTrue(\mkdir($path, 0700));
                self::assertSame('dir', \filetype($path));
            }

            [$logger] = self::logger($root);
            $logger->info('kernel.boot');
            self::assertSame(
                ['web-app-2026-10-31.jsonl'],
                \array_map('basename', \glob($root . '/*') ?: []),
            );
            self::assertFalse(\is_file($path));
        } finally {
            self::clean($root);
        }
    }

    public function testLockContentionIsNonblockingAndLaterWritesCanRecover(): void
    {
        $root = self::tempDirectory();
        try {
            $path = $root . '/web-app-2026-10-31.jsonl';
            $handle = \fopen($path, 'c+b');
            self::assertIsResource($handle);
            try {
                self::assertTrue(\flock($handle, \LOCK_EX | \LOCK_NB));
                [$logger] = self::logger($root);
                $logger->info('kernel.boot');
                self::assertTrue(\flock($handle, \LOCK_UN));
                self::assertSame('', \file_get_contents($path));
                self::assertSame(
                    ['web-app-2026-10-31.jsonl'],
                    \array_map('basename', \glob($root . '/*') ?: []),
                );
                $logger->info('kernel.boot');
                self::assertSame(1, \substr_count((string) \file_get_contents($path), "\n"));
            } finally {
                \flock($handle, \LOCK_UN);
                \fclose($handle);
            }
        } finally {
            self::clean($root);
        }
    }

    public function testOneFwriteNonblockingFlockAndRegularFileChecksUseActualHandle(): void
    {
        $source = (string) \file_get_contents(new \ReflectionClass(StreamLogger::class)->getFileName());
        $method = \substr($source, \strpos($source, 'private function writeDailyFile('));
        self::assertStringContainsString('@\\fwrite($handle, $serialized)', $method);
        $writeCalls = 0;

        foreach (\token_get_all('<?php ' . $method) as $token) {
            if (
                \is_array($token)
                && \in_array($token[0], [\T_STRING, \T_NAME_FULLY_QUALIFIED], true)
                && \strtolower(\ltrim($token[1], '\\')) === 'fwrite'
            ) {
                ++$writeCalls;
            }
        }

        self::assertSame(1, $writeCalls);
        self::assertSame(1, \substr_count($method, '@\\flock($handle, \\LOCK_EX | \\LOCK_NB)'));
        self::assertStringContainsString('@\\fstat($handle)', $method);
        self::assertStringContainsString('@\\flock($handle, \\LOCK_UN)', $method);
        self::assertStringContainsString('@\\fclose($handle)', $method);
        self::assertStringNotContainsString('ScopedFileLock', $method);
        self::assertStringNotContainsString('mkdir(', $method);
    }

    public function testBothFormatsUseSharedAdmissionAndRedactionWithNoRawFileEmission(): void
    {
        $root = self::tempDirectory();
        try {
            foreach (['jsonl', 'logfmt'] as $format) {
                [$logger, , $redactor, $classifier] = self::logger($root, $format);
                $logger->info(
                    'kernel.boot',
                    ['operation' => 'boot', 'raw_path' => '/customer/private', 'key_paths' => ['db.password']],
                );
                $file = $root . '/web-app-2026-10-31.' . $format;
                $bytes = (string) \file_get_contents($file);
                self::assertStringContainsString('kernel.boot', $bytes);
                self::assertStringNotContainsString('/customer/private', $bytes);
                self::assertStringNotContainsString('db.password', $bytes);
                self::assertContains('password', $classifier->keys);
                self::assertSame(1, $redactor->calls);
                \unlink($file);
            }
        } finally {
            self::clean($root);
        }
    }

    public function testValidDirectoryMayBecomeAvailableOnlyAtEmissionTime(): void
    {
        $root = self::tempDirectory();
        try {
            foreach (['jsonl', 'logfmt'] as $format) {
                $missing = $root . '/missing';
                [$logger] = self::logger($missing, $format);
                self::assertInstanceOf(StreamLogger::class, $logger);
                $logger->info('kernel.boot');
                self::assertDirectoryDoesNotExist($missing);
                self::assertTrue(\mkdir($missing, 0700));
                $logger->info('kernel.boot');
                self::assertFileExists($missing . '/web-app-2026-10-31.' . $format);
                self::clean($missing);
            }
        } finally {
            self::clean($root);
        }
    }

    public function testPathGrammarRejectsRemoteTraversalAndStreamWrappers(): void
    {
        foreach (
            [
                './logs',
                '../logs',
                '/tmp/../logs',
                '/tmp//logs',
                '//server/share',
                'php://stderr',
                'https://host/log',
                'file:///tmp/log',
                'C:relative',
            ] as $invalid
        ) {
            try {
                self::logger($invalid);
                self::fail('Invalid destination must fail before constructing logger.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('logging-file-path-invalid', $exception->getMessage());
            }
        }
        if (\PHP_OS_FAMILY === 'Windows') {
            [$logger] = self::logger('C:\\logs');
            self::assertInstanceOf(StreamLogger::class, $logger);
        }
    }

    /** @return array{0: StreamLogger|NoopLogger, 1: object, 2: object, 3: object} */
    private static function logger(
        string $directory,
        string $format = 'jsonl',
        string $app = 'web',
        string $service = 'auto',
        string $stem = 'app',
        array $dates = ['2026-10-31T12:00:00Z'],
        string $driver = 'file',
    ): array {
        $config = new class($directory, $format, $service, $stem, $driver) implements ConfigRepositoryInterface {
            public array $hasReads = [];
            public array $getReads = [];
            private array $values;

            public function __construct(
                string $directory,
                string $format,
                string $service,
                string $stem,
                string $driver,
            ) {
                $this->values = [
                    'logging.driver' => $driver,
                    'logging.file' => ['path' => $directory, 'name' => $stem],
                    'logging.file.path' => $directory,
                    'logging.file.name' => $stem,
                    'logging.level' => 'debug',
                    'logging.max_record_bytes' => 65536,
                    'logging.service_name' => $service,
                    'logging.format' => $format,
                ];
            }

            public function has(string $keyPath): bool
            {
                $this->hasReads[] = $keyPath;
                return \array_key_exists($keyPath, $this->values);
            }

            public function get(string $keyPath, mixed $default = null): mixed
            {
                $this->getReads[] = $keyPath;
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
        $redactor = new class() implements SensitiveDataRedactorInterface {
            public int $calls = 0;

            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('unused');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                ++$this->calls;
                return $value;
            }
        };
        $classifier = new class() implements SensitiveKeyClassifierInterface {
            public array $keys = [];

            public function classify(string $key): ?RedactionKind
            {
                $this->keys[] = $key;
                return $key === 'password' ? RedactionKind::Secret : null;
            }
        };
        $clock = new class($dates) implements ClockInterface {
            private int $index = 0;

            public function __construct(private array $dates)
            {
            }

            public function now(): \DateTimeImmutable
            {
                $date = $this->dates[\min($this->index, \count($this->dates) - 1)];
                ++$this->index;
                return new \DateTimeImmutable($date);
            }
        };
        $logger = LoggingServiceFactory::createLogger(
            $config,
            new class() implements ContextAccessorInterface {
                public function has(string $key): bool
                {
                    return false;
                }

                public function get(string $key): mixed
                {
                    throw new \LogicException('no-uow');
                }
            },
            new class() implements CorrelationIdProviderInterface {
                public function correlationId(): ?string
                {
                    return null;
                }
            },
            $redactor,
            $classifier,
            $clock,
            new class($app) implements AppTargetProviderInterface {
                public function __construct(private string $value)
                {
                }

                public function app(): string
                {
                    return $this->value;
                }
            },
        );
        return [$logger, $config, $redactor, $classifier];
    }

    private static function tempDirectory(): string
    {
        $directory = \sys_get_temp_dir() . '/coretsia-daily-logging-' . \bin2hex(\random_bytes(10));
        self::assertTrue(\mkdir($directory, 0700));
        return $directory;
    }

    private static function clean(string $directory): void
    {
        if (!\is_dir($directory) || \is_link($directory)) {
            return;
        }
        foreach (\glob($directory . '/*') ?: [] as $path) {
            if (\is_link($path)) {
                if (\PHP_OS_FAMILY === 'Windows' && \is_dir($path)) {
                    self::assertTrue(\rmdir($path));
                } else {
                    self::assertTrue(\unlink($path));
                }
            } elseif (\is_dir($path)) {
                self::clean($path);
            } else {
                \unlink($path);
            }
        }
        \rmdir($directory);
    }
}
