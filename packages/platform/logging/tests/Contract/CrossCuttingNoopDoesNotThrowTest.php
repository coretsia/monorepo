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

namespace Coretsia\Platform\Logging\Tests\Contract;

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
use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Processor\ContextBagProcessor;
use Coretsia\Platform\Logging\Provider\LoggingServiceFactory;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\InvalidArgumentException as InvalidPsrLevel;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testFoundationNoopIgnoresAllSeveritiesArbitraryContextAndStringable(): void
    {
        $evaluations = 0;
        $message = new class($evaluations) implements \Stringable {
            public function __construct(private int &$evaluations)
            {
            }

            public function __toString(): string
            {
                ++$this->evaluations;
                throw new \LogicException('must-not-be-called');
            }
        };
        $context = [
            'authorization' => 'Bearer synthetic-private',
            'payload' => new \RuntimeException('synthetic-private'),
            'object' => new \stdClass(),
            'float' => 1.5,
            'callback' => static fn (): string => 'not-called',
            'nested' => ['raw' => $message],
        ];
        $noop = new NoopLogger();
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $method) {
            $noop->{$method}($message, $context);
        }
        foreach ([null, false, 'unsupported', new \stdClass()] as $level) {
            $noop->log($level, $message, $context);
        }
        self::assertSame(0, $evaluations);
        self::assertInstanceOf(NoopLogger::class, $noop);
    }

    public function testNullDriverReturnsExistingFoundationNoopWithoutClockRedactionOrFilesystemIO(): void
    {
        $redactor = self::redactor();
        $clock = self::clock();
        $config = new class() implements ConfigRepositoryInterface {
            public array $reads = [];
            private array $values = [
                'logging.driver' => 'null',
                'logging.file' => ['path' => '', 'name' => 'app'],
                'logging.file.path' => '',
                'logging.file.name' => 'app',
                'logging.level' => 'info',
                'logging.max_record_bytes' => 65536,
                'logging.service_name' => 'auto',
                'logging.format' => 'jsonl',
            ];

            public function has(string $keyPath): bool
            {
                $this->reads[] = $keyPath;
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
        $logger = LoggingServiceFactory::createLogger(
            $config,
            self::context(),
            self::correlations(),
            $redactor,
            self::classifier(),
            $clock,
            new class() implements AppTargetProviderInterface {
                public function app(): string
                {
                    return 'web';
                }
            },
        );
        self::assertInstanceOf(NoopLogger::class, $logger);
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $method) {
            $logger->{$method}('kernel.boot', ['raw' => 'synthetic-secret']);
        }
        self::assertSame(0, $redactor->calls);
        self::assertSame(0, $clock->calls);
        self::assertNotContains('logging.stream', $config->reads);
    }

    public function testNullDriverNeverInitializesTheFixedStderrDestination(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $script = <<<'PHP_CODE'
require $argv[1];
require $argv[2];
class NullDriverStreamProbe {
    public $context;
    public static array $opens = [];

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        self::$opens[] = [$path, $mode];
        return true;
    }

    public function stream_close(): void
    {
    }
}
stream_wrapper_unregister('php');
stream_wrapper_register('php', NullDriverStreamProbe::class);
try {
    $test = new \Coretsia\Platform\Logging\Tests\Contract\CrossCuttingNoopDoesNotThrowTest(
        'testNullDriverReturnsExistingFoundationNoopWithoutClockRedactionOrFilesystemIO',
    );
    $test->testNullDriverReturnsExistingFoundationNoopWithoutClockRedactionOrFilesystemIO();
} finally {
    stream_wrapper_restore('php');
}
echo json_encode(NullDriverStreamProbe::$opens, JSON_THROW_ON_ERROR);
PHP_CODE;
        $process = \proc_open(
            [\PHP_BINARY, '-d', 'display_errors=1', '-r', $script, $autoload, __FILE__],
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
        self::assertSame([], \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testFailedFixedStderrWriteIsIsolatedWithoutRawFallback(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $script = <<<'PHP_CODE'
require $argv[1];
require $argv[2];
class FailedStderrWrapper {
    public $context;
    public static array $opens = [];
    public static int $writes = 0;
    public function stream_open($path, $mode, $options, &$opened_path): bool {
        self::$opens[] = [$path, $mode];
        return $path === 'php://stderr';
    }
    public function stream_write($bytes): int {
        ++self::$writes;
        return 0;
    }
    public function stream_flush(): bool { return true; }
    public function stream_close(): void {}
}
stream_wrapper_unregister('php');
stream_wrapper_register('php', FailedStderrWrapper::class);
$method = new \ReflectionMethod(\Coretsia\Platform\Logging\Tests\Contract\CrossCuttingNoopDoesNotThrowTest::class, 'failingLogger');
$logger = $method->invoke(null, false, 'stream');
$logger->info('kernel.boot', ['operation' => 'boot']);
stream_wrapper_restore('php');
echo json_encode(['opens' => FailedStderrWrapper::$opens, 'writes' => FailedStderrWrapper::$writes], JSON_THROW_ON_ERROR);
PHP_CODE;
        $process = \proc_open(
            [\PHP_BINARY, '-d', 'display_errors=1', '-r', $script, $autoload, __FILE__],
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
        $outcome = \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([['php://stderr', 'ab']], $outcome['opens']);
        self::assertGreaterThanOrEqual(1, $outcome['writes']);
    }

    public function testFormatterExceptionsNeverEscapeOrTriggerRawFallback(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $script = <<<'PHP_CODE'
require $argv[1];
require $argv[2];
$name = $argv[3] === 'jsonl' ? 'JsonLineFormatter' : 'LogfmtLineFormatter';
eval('namespace Coretsia\\Platform\\Logging\\Formatter; final class ' . $name . ' {
    public static int $calls = 0;
    public function format(array $record): string {
        ++self::$calls;
        throw new \\RuntimeException("SYNTHETIC_PRIVATE_SERIALIZATION");
    }
}');
$formatter = 'Coretsia\\Platform\\Logging\\Formatter\\' . $name;
$ref = new \ReflectionMethod(\Coretsia\Platform\Logging\Tests\Contract\CrossCuttingNoopDoesNotThrowTest::class, 'failingLogger');
$logger = $ref->invoke(null, false);
// Construct a fresh logger rather than mutating a readonly production object.
$policy = new \ReflectionProperty(\Coretsia\Platform\Logging\Logger\StreamLogger::class, 'policy');
$processor = new \ReflectionProperty(\Coretsia\Platform\Logging\Logger\StreamLogger::class, 'processor');
$clock = new \ReflectionProperty(\Coretsia\Platform\Logging\Logger\StreamLogger::class, 'clock');
$logger = new \Coretsia\Platform\Logging\Logger\StreamLogger(
    $policy->getValue($logger), $processor->getValue($logger), new $formatter(), $clock->getValue($logger),
    'debug', 65536, 'web', 'web', 'file', $argv[4], 'app',
);
$logger->info('kernel.boot');
echo json_encode(['complete' => true, 'formatter_calls' => $formatter::$calls], JSON_THROW_ON_ERROR);
PHP_CODE;
        foreach (['jsonl', 'logfmt'] as $format) {
            $directory = \rtrim(\sys_get_temp_dir(), '/\\') . \DIRECTORY_SEPARATOR
                . 'coretsia-formatter-unavailable-' . \bin2hex(\random_bytes(8));
            $process = \proc_open(
                [\PHP_BINARY, '-d', 'display_errors=1', '-r', $script, $autoload, __FILE__, $format, $directory],
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
            self::assertSame(
                ['complete' => true, 'formatter_calls' => 1],
                \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR),
            );
            self::assertSame('', $stderr);
            self::assertDirectoryDoesNotExist($directory);
        }
    }

    public function testStreamLoggerInvalidLevelRemainsAnApiErrorNotAnInternalFailure(): void
    {
        $logger = self::failingLogger(false);
        foreach ([null, false, 'verbose', 'INFO', 1] as $level) {
            try {
                $logger->log($level, 'kernel.boot');
                self::fail('Invalid PSR-3 level must throw.');
            } catch (InvalidPsrLevel $exception) {
                self::assertSame('logging-level-invalid', $exception->getMessage());
            }
        }
    }

    public function testInternalClassifierRedactorAndFileWriteFailuresRemainNoThrow(): void
    {
        // All three failures occur inside the ordinary logger boundary.
        foreach (['classifier', 'redactor', 'write'] as $failure) {
            $directory = \sys_get_temp_dir() . '/coretsia-noop-unavailable-' . \bin2hex(\random_bytes(8));
            $redactor = self::redactor($failure === 'redactor');
            $classifier = self::classifier($failure === 'classifier');
            $logger = new StreamLogger(
                new LogRecordPolicy($redactor, $classifier),
                new ContextBagProcessor(self::context(), self::correlations()),
                new JsonLineFormatter(),
                self::clock(),
                'debug',
                65536,
                'web',
                'web',
                'file',
                $directory,
                'app',
            );
            $logger->info('kernel.boot', ['key_paths' => ['db.table']]);
            self::assertDirectoryDoesNotExist($directory);
            self::assertSame($failure === 'classifier' ? 0 : 1, $redactor->calls);
        }
    }

    private static function failingLogger(bool $throwingClassifier, string $driver = 'file'): StreamLogger
    {
        return new StreamLogger(
            new LogRecordPolicy(self::redactor(), self::classifier($throwingClassifier)),
            new ContextBagProcessor(self::context(), self::correlations()),
            new JsonLineFormatter(),
            self::clock(),
            'debug',
            65536,
            'web',
            'web',
            $driver,
            $driver === 'file' ? \sys_get_temp_dir() . '/coretsia-ignored-' . \bin2hex(\random_bytes(8)) : null,
            $driver === 'file' ? 'app' : null,
        );
    }

    private static function context(): ContextAccessorInterface
    {
        return new class() implements ContextAccessorInterface {
            public function has(string $key): bool
            {
                return false;
            }

            public function get(string $key): mixed
            {
                throw new \LogicException('no-context');
            }
        };
    }

    private static function correlations(): CorrelationIdProviderInterface
    {
        return new class() implements CorrelationIdProviderInterface {
            public function correlationId(): ?string
            {
                return null;
            }
        };
    }

    private static function clock(): ClockInterface
    {
        return new class() implements ClockInterface {
            public int $calls = 0;

            public function now(): \DateTimeImmutable
            {
                ++$this->calls;
                return new \DateTimeImmutable('2026-10-31T12:00:00Z');
            }
        };
    }

    private static function classifier(bool $throws = false): SensitiveKeyClassifierInterface
    {
        return new class($throws) implements SensitiveKeyClassifierInterface {
            public function __construct(private bool $throws)
            {
            }

            public function classify(string $key): ?RedactionKind
            {
                if ($this->throws) {
                    throw new \RuntimeException('synthetic-sensitive');
                }
                return null;
            }
        };
    }

    private static function redactor(bool $throws = false): SensitiveDataRedactorInterface
    {
        return new class($throws) implements SensitiveDataRedactorInterface {
            public int $calls = 0;

            public function __construct(private bool $throws)
            {
            }

            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('unused');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                ++$this->calls;
                if ($this->throws) {
                    throw new \RuntimeException('synthetic-private-redaction');
                }
                return $value;
            }
        };
    }
}
