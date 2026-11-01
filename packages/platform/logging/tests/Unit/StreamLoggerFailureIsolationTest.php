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

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Processor\ContextBagProcessor;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\InvalidArgumentException as InvalidPsrLevel;

final class StreamLoggerFailureIsolationTest extends TestCase
{
    public function testInvalidPsrLevelAndSeverityFilteringPrecedeAllWork(): void
    {
        [$policy, $redactor, $classifier] = self::policy();
        $clock = self::clock(['2026-10-31T12:00:00Z']);
        $directory = self::temporaryPath();
        $logger = self::logger($policy, $clock, new JsonLineFormatter(), $directory, 65536, 'error');
        foreach ([null, false, 'verbose', 'INFO'] as $level) {
            try {
                $logger->log($level, 'kernel.boot');
                self::fail('Invalid PSR-3 level must throw before filtering.');
            } catch (InvalidPsrLevel $exception) {
                self::assertSame('logging-level-invalid', $exception->getMessage());
            }
        }
        $logger->debug('kernel.boot', ['key_paths' => ['db.value']]);
        $logger->info('kernel.boot');
        $logger->warning('kernel.boot');
        self::assertSame(0, $clock->calls);
        self::assertSame(0, $redactor->calls);
        self::assertSame([], $classifier->keys);
        self::assertDirectoryDoesNotExist($directory);
        $logger->error('kernel.boot', ['key_paths' => ['db.value']]);
        self::assertSame(1, $clock->calls);
        self::assertSame(1, $redactor->calls);
        self::assertGreaterThan(0, \count($classifier->keys));
    }

    public function testClockAndWholeRecordRedactionAreOrderedBeforeFileSelection(): void
    {
        $directory = self::temporaryDirectory();
        try {
            [$policy, $redactor] = self::policy();
            $clock = self::clock(['2026-11-01T00:15:20+09:00']);
            $logger = self::logger($policy, $clock, new JsonLineFormatter(), $directory);
            $logger->info('kernel.boot', ['operation' => 'boot']);
            self::assertSame(1, $clock->calls);
            self::assertSame(1, $redactor->calls);
            self::assertSame('2026-10-31T15:15:20.000000Z', $redactor->inputs[0]['ts_utc']);
            self::assertSame('info', $redactor->inputs[0]['level']);
            self::assertSame('web', $redactor->inputs[0]['service_name']);
            self::assertSame('web', $redactor->inputs[0]['app_target']);
            self::assertSame(['operation' => 'boot'], $redactor->inputs[0]['context']);
            $canonical = $redactor->inputs[0];
            \ksort($canonical, \SORT_STRING);
            self::assertSame(
                $canonical,
                \json_decode(
                    (string) \file_get_contents($directory . '/web-app-2026-10-31.jsonl'),
                    true,
                    512,
                    \JSON_THROW_ON_ERROR,
                ),
            );
        } finally {
            self::clean($directory);
        }
    }

    public function testClockFailureRetainsNullTimestampAndUsesSystemDateOnlyForFilename(): void
    {
        $directory = self::temporaryDirectory();
        try {
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                [$policy, $redactor] = self::policy();
                $clock = self::clock([new \RuntimeException('private-clock-failure')]);
                $before = \gmdate('Y-m-d');
                self::logger($policy, $clock, $formatter, $directory)->info('kernel.boot');
                $after = \gmdate('Y-m-d');
                self::assertSame(1, $clock->calls);
                self::assertSame(1, $redactor->calls);
                self::assertNull($redactor->inputs[0]['ts_utc']);
                $ext = $formatter instanceof JsonLineFormatter ? 'jsonl' : 'logfmt';
                $paths = \glob($directory . '/web-app-*.' . $ext);
                self::assertCount(1, $paths);
                self::assertContains($paths[0], [
                    $directory . '/web-app-' . $before . '.' . $ext,
                    $directory . '/web-app-' . $after . '.' . $ext,
                ]);
                self::assertStringContainsString(
                    $ext === 'jsonl' ? '"ts_utc":null' : 'ts_utc=null',
                    (string) \file_get_contents($paths[0]),
                );
            }
        } finally {
            self::clean($directory);
        }
    }

    public function testRolloverCannotReuseAnObsoleteDailyFilenameOrHandle(): void
    {
        $directory = self::temporaryDirectory();
        try {
            [$policy] = self::policy();
            $clock = self::clock(['2026-10-31T23:59:59Z', '2026-11-01T00:00:00Z']);
            $logger = self::logger($policy, $clock, new JsonLineFormatter(), $directory);
            $logger->info('kernel.boot');
            $logger->info('kernel.boot');
            self::assertSame(2, $clock->calls);
            self::assertSame([
                $directory . '/web-app-2026-10-31.jsonl',
                $directory . '/web-app-2026-11-01.jsonl',
            ], \glob($directory . '/*'));
        } finally {
            self::clean($directory);
        }
    }

    public function testBothFormatsSerializeOnlyTheCompletePostRedactionRecord(): void
    {
        $directory = self::temporaryDirectory();
        try {
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                [$policy, $redactor] = self::policy(static function (array $record): array {
                    $record['message'] = 'logging.message_rejected';
                    return $record;
                });
                $logger = self::logger($policy, self::clock(['2026-10-31T12:00:00Z']), $formatter, $directory);
                $logger->info('kernel.boot', ['operation' => 'config:compile']);
                $extension = $formatter instanceof JsonLineFormatter ? 'jsonl' : 'logfmt';
                $frame = (string) \file_get_contents($directory . '/web-app-2026-10-31.' . $extension);
                self::assertSame(1, $redactor->calls);
                self::assertSame('kernel.boot', $redactor->inputs[0]['message']);
                self::assertStringContainsString('logging.message_rejected', $frame);
                self::assertStringNotContainsString('kernel.boot', $frame);
                self::assertStringContainsString('config:compile', $frame);
                self::assertSame(1, \substr_count($frame, "\n"));
            }
        } finally {
            self::clean($directory);
        }
    }

    public function testPostRedactionInvalidityAndRedactorFailureNeverOpenFile(): void
    {
        $directory = self::temporaryDirectory();
        try {
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                foreach (
                    [
                        static fn (array $record): array => \array_replace($record, ['app_target' => 'invalid']),
                        static function (): never {
                            throw new \RuntimeException('private-secret');
                        },
                    ] as $transform
                ) {
                    [$policy, $redactor] = self::policy($transform);
                    self::logger($policy, self::clock([]), $formatter, $directory)->info('kernel.boot');
                    self::assertSame(1, $redactor->calls);
                    self::assertSame([], \glob($directory . '/*'));
                }
            }
        } finally {
            self::clean($directory);
        }
    }

    public function testInclusive16384AndExclusive16385SerializedByteBoundsForBothFormats(): void
    {
        self::assertSame(65536, StreamLogger::MAX_RECORD_BYTES);
        foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
            $directory = self::temporaryDirectory();
            try {
                foreach ([16384, 16385] as $length) {
                    [$policy, $redactor] = self::policy();
                    $context = self::contextForLength($policy, $formatter, $length);
                    $frame = $formatter->format(self::record($policy->admitContext($context)));
                    self::assertSame($length, \strlen($frame));
                    self::assertTrue(\str_ends_with($frame, "\n"));
                    $before = \gmdate('Y-m-d');
                    self::logger(
                        $policy,
                        self::clock([new \RuntimeException('unavailable')]),
                        $formatter,
                        $directory,
                        16384,
                    )
                        ->info('kernel.boot', $context);
                    $after = \gmdate('Y-m-d');
                    self::assertSame(1, $redactor->calls);
                    $ext = $formatter instanceof JsonLineFormatter ? 'jsonl' : 'logfmt';
                    $paths = \glob($directory . '/web-app-*.' . $ext);
                    if ($length === 16384) {
                        self::assertCount(1, $paths);
                        self::assertContains($paths[0], [
                            $directory . '/web-app-' . $before . '.' . $ext,
                            $directory . '/web-app-' . $after . '.' . $ext,
                        ]);
                        self::assertSame($frame, \file_get_contents($paths[0]));
                        \unlink($paths[0]);
                    } else {
                        self::assertSame([], $paths);
                    }
                }
                [$policy] = self::policy();
                $context = self::contextForLength($policy, $formatter, 16384);
                self::logger($policy, self::clock([]), $formatter, $directory, 16383)->info('kernel.boot', $context);
                self::assertSame([], \glob($directory . '/*'), 'Final LF counts toward the byte limit.');
            } finally {
                self::clean($directory);
            }
        }
    }

    public function testInvalidLimitsAndPreEmissionRejectionsCreateNoFiles(): void
    {
        [$policy] = self::policy();
        foreach ([1023, 65537] as $limit) {
            try {
                self::logger($policy, self::clock([]), new JsonLineFormatter(), self::temporaryPath(), $limit);
                self::fail('Invalid configuration must not bypass immutable hard ceiling.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('logging-runtime-settings-invalid', $exception->getMessage());
            }
        }
        $directory = self::temporaryDirectory();
        try {
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                self::logger($policy, self::clock([]), $formatter, $directory, 1024, 'emergency')->info('kernel.boot');
                [$rejectedPolicy] = self::policy(static function (): never {
                    throw new \RuntimeException('redaction-failure');
                });
                self::logger($rejectedPolicy, self::clock([]), $formatter, $directory, 1024)->info('kernel.boot');
                $context = self::contextForLength($policy, $formatter, 16385);
                self::logger($policy, self::clock([]), $formatter, $directory, 1024)->info('kernel.boot', $context);
                self::assertSame([], \glob($directory . '/*'));
            }
        } finally {
            self::clean($directory);
        }
    }

    public function testFixedStderrWriterMakesExactlyOneAttemptEvenOnShortWrites(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $script = <<<'PHP_CODE'
require $argv[1];
class ShortWritePhpWrapper {
    public $context;
    public static array $opens = [];
    public static array $writes = [];
    public function stream_open($path, $mode, $options, &$opened_path): bool {
        self::$opens[] = [$path, $mode];
        return $path === 'php://stderr';
    }
    public function stream_write($data): int {
        self::$writes[] = $data;
        return $GLOBALS['mode'] === 'failed' ? 0 : ($GLOBALS['mode'] === 'short' ? 1 : strlen($data));
    }
    public function stream_flush(): bool
    {
        return true;
    }
    public function stream_close(): void
    {
    }
}
$redactor = new class implements \Coretsia\Contracts\Security\SensitiveDataRedactorInterface {
    public function redactValue(string $value, \Coretsia\Contracts\Security\RedactionKind $kind, \Coretsia\Contracts\Security\RedactionContext $context): \Coretsia\Contracts\Security\RedactedValue
    {
        throw new \LogicException('unused');
    }
    public function redactJsonLike(mixed $value, \Coretsia\Contracts\Security\RedactionContext $context): mixed
    {
        return $value;
    }
};
$policy = new \Coretsia\Platform\Logging\Record\LogRecordPolicy($redactor, new class implements \Coretsia\Contracts\Security\SensitiveKeyClassifierInterface {
    public function classify(string $key): ?\Coretsia\Contracts\Security\RedactionKind
    {
        return null;
    }
});
$processor = new \Coretsia\Platform\Logging\Processor\ContextBagProcessor(
    new class implements \Coretsia\Contracts\Context\ContextAccessorInterface {
        public function has(string $key): bool
        {
            return false;
        }
        public function get(string $key): mixed
        {
            throw new \LogicException('unused');
        }
    },
    new class implements \Coretsia\Contracts\Observability\CorrelationIdProviderInterface {
        public function correlationId(): ?string
        {
            return null;
        }
    },
);
$clock = new class implements \Psr\Clock\ClockInterface {
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-31T12:00:00Z');
    }
};
$GLOBALS['mode'] = $argv[2];
stream_wrapper_unregister('php');
stream_wrapper_register('php', ShortWritePhpWrapper::class);
$logger = new \Coretsia\Platform\Logging\Logger\StreamLogger(
    $policy, $processor,
    $argv[3] === 'jsonl' ? new \Coretsia\Platform\Logging\Formatter\JsonLineFormatter() : new \Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter(),
    $clock, 'debug', 65536, 'web', 'web',
);
$logger->info('kernel.boot');
stream_wrapper_restore('php');
echo json_encode(['opens' => ShortWritePhpWrapper::$opens, 'writes' => ShortWritePhpWrapper::$writes], JSON_THROW_ON_ERROR);
PHP_CODE;
        $source = (string) \file_get_contents(new \ReflectionClass(StreamLogger::class)->getFileName());
        self::assertSame(1, \substr_count($source, '@\\fwrite($this->stream, $serialized)'));
        foreach (['jsonl', 'logfmt'] as $format) {
            foreach (['failed', 'short', 'full'] as $mode) {
                $process = \proc_open(
                    [\PHP_BINARY, '-r', $script, $autoload, $mode, $format],
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
                $result = \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR);
                self::assertSame([['php://stderr', 'ab']], $result['opens']);
                if ($mode === 'short') {
                    // PHP's stream implementation may retry a short user-wrapper
                    // callback internally within the single fwrite() invocation.
                    self::assertGreaterThanOrEqual(1, \count($result['writes']));
                } else {
                    self::assertCount(1, $result['writes']);
                }
                self::assertTrue(\str_ends_with($result['writes'][0], "\n"));
                self::assertSame(1, \substr_count($result['writes'][0], "\n"));
            }
        }
    }

    public function testFinalPostRedactionUtcTimestampControlsFilenameWithoutRewritingRoot(): void
    {
        $directory = self::temporaryDirectory();
        try {
            [$policy, $redactor] = self::policy(static function (array $record): array {
                $record['ts_utc'] = '2026-11-01T00:00:01.000000Z';
                return $record;
            });
            $logger = self::logger($policy, self::clock(['2026-10-31T23:59:59Z']), new JsonLineFormatter(), $directory);
            $logger->info('kernel.boot');
            self::assertSame(1, $redactor->calls);
            self::assertSame('2026-10-31T23:59:59.000000Z', $redactor->inputs[0]['ts_utc']);
            $path = $directory . '/web-app-2026-11-01.jsonl';
            self::assertFileExists($path);
            self::assertFileDoesNotExist($directory . '/web-app-2026-10-31.jsonl');
            self::assertSame(
                '2026-11-01T00:00:01.000000Z',
                \json_decode((string) \file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR)['ts_utc'],
            );
        } finally {
            self::clean($directory);
        }
    }

    public function testFormatterFailureDoesNotWriteOrDiscloseDiagnosticsForEitherFormat(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $directory = self::temporaryDirectory();
        $script = <<<'PHP_CODE'
require $argv[1];
// Override the formatter class in this child process only, before autoload.
$name = $argv[2] === 'jsonl' ? 'JsonLineFormatter' : 'LogfmtLineFormatter';
eval('namespace Coretsia\\Platform\\Logging\\Formatter; final class ' . $name . ' {
    public function format(array $record): string
    {
        throw new \\RuntimeException("private-format-diagnostic");
    }
}');
$formatterClass = 'Coretsia\\Platform\\Logging\\Formatter\\' . $name;
$redactor = new class implements \Coretsia\Contracts\Security\SensitiveDataRedactorInterface {
    public int $calls = 0;
    public function redactValue(string $value, \Coretsia\Contracts\Security\RedactionKind $kind, \Coretsia\Contracts\Security\RedactionContext $context): \Coretsia\Contracts\Security\RedactedValue
    {
        throw new \LogicException('unused');
    }
    public function redactJsonLike(mixed $value, \Coretsia\Contracts\Security\RedactionContext $context): mixed
    {
        ++$this->calls; return $value;
    }
};
$policy = new \Coretsia\Platform\Logging\Record\LogRecordPolicy($redactor, new class implements \Coretsia\Contracts\Security\SensitiveKeyClassifierInterface {
    public function classify(string $key): ?\Coretsia\Contracts\Security\RedactionKind
    {
        return null;
    }
});
$processor = new \Coretsia\Platform\Logging\Processor\ContextBagProcessor(
    new class implements \Coretsia\Contracts\Context\ContextAccessorInterface {
        public function has(string $key): bool
        {
            return false;
        }
        public function get(string $key): mixed
        {
            throw new \LogicException('unused');
        }
    },
    new class implements \Coretsia\Contracts\Observability\CorrelationIdProviderInterface {
        public function correlationId(): ?string
        {
            return null;
        }
    },
);
$logger = new \Coretsia\Platform\Logging\Logger\StreamLogger(
    $policy, $processor, new $formatterClass(),
    new class implements \Psr\Clock\ClockInterface {
        public function now(): \DateTimeImmutable
        {
            return new \DateTimeImmutable('2026-10-31T12:00:00Z');
        }
    },
    'debug', 65536, 'web', 'web', 'file', $argv[3], 'app',
);
$logger->info('kernel.boot');
echo json_encode(['redactions' => $redactor->calls], JSON_THROW_ON_ERROR);
PHP_CODE;
        try {
            foreach (['jsonl', 'logfmt'] as $format) {
                $process = \proc_open(
                    [\PHP_BINARY, '-r', $script, $autoload, $format, $directory],
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
                self::assertSame(['redactions' => 1], \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR));
                self::assertSame([], \glob($directory . '/*'));
            }
        } finally {
            self::clean($directory);
        }
    }

    public function testInitialClassifierFailureNeverReadsClockRedactsOrOpensDestination(): void
    {
        $directory = self::temporaryDirectory();
        try {
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                $redactor = new class() implements SensitiveDataRedactorInterface {
                    public int $calls = 0;

                    public function redactValue(
                        string $value,
                        RedactionKind $kind,
                        RedactionContext $context,
                    ): RedactedValue {
                        throw new \LogicException('unused');
                    }

                    public function redactJsonLike(mixed $value, RedactionContext $context): mixed
                    {
                        ++$this->calls;
                        return $value;
                    }
                };
                $classifier = new class() implements SensitiveKeyClassifierInterface {
                    public function classify(string $key): ?RedactionKind
                    {
                        throw new \RuntimeException('private-classifier-diagnostic');
                    }
                };
                $clock = self::clock([]);
                $logger = self::logger(new LogRecordPolicy($redactor, $classifier), $clock, $formatter, $directory);
                $logger->info('kernel.boot', ['key_paths' => ['db.table']]);
                self::assertSame(0, $redactor->calls);
                self::assertSame(0, $clock->calls);
                self::assertSame([], \glob($directory . '/*'));
            }
        } finally {
            self::clean($directory);
        }
    }

    public function testPostRedactionClassifierFailureDoesNotEmitOrRetryRedaction(): void
    {
        $directory = self::temporaryDirectory();
        try {
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                $redactor = new class() implements SensitiveDataRedactorInterface {
                    public int $calls = 0;

                    public function redactValue(
                        string $value,
                        RedactionKind $kind,
                        RedactionContext $context,
                    ): RedactedValue {
                        throw new \LogicException('unused');
                    }

                    public function redactJsonLike(mixed $value, RedactionContext $context): mixed
                    {
                        ++$this->calls;
                        $value['context']['key_paths'] = ['db.secret[0]'];
                        return $value;
                    }
                };
                $classifier = new class() implements SensitiveKeyClassifierInterface {
                    public function classify(string $key): ?RedactionKind
                    {
                        if ($key === 'secret') {
                            throw new \RuntimeException('private-classifier-diagnostic');
                        }
                        return null;
                    }
                };
                $clock = self::clock(['2026-10-31T12:00:00Z']);
                $logger = self::logger(new LogRecordPolicy($redactor, $classifier), $clock, $formatter, $directory);
                $logger->info('kernel.boot', ['key_paths' => ['db.table']]);
                self::assertSame(1, $redactor->calls);
                self::assertSame(1, $clock->calls);
                self::assertSame([], \glob($directory . '/*'));
            }
        } finally {
            self::clean($directory);
        }
    }

    /** @return array{LogRecordPolicy, object, object} */
    private static function policy(?\Closure $transform = null): array
    {
        $redactor = new class($transform) implements SensitiveDataRedactorInterface {
            public int $calls = 0;
            public array $inputs = [];

            public function __construct(private ?\Closure $transform)
            {
            }

            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('unused');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                ++$this->calls;
                $this->inputs[] = $value;
                return $this->transform === null ? $value : ($this->transform)($value);
            }
        };
        $classifier = new class() implements SensitiveKeyClassifierInterface {
            public array $keys = [];

            public function classify(string $key): ?RedactionKind
            {
                $this->keys[] = $key;
                return null;
            }
        };
        return [new LogRecordPolicy($redactor, $classifier), $redactor, $classifier];
    }

    /** @param list<string|\Throwable> $dates */
    private static function clock(array $dates): ClockInterface
    {
        return new class($dates) implements ClockInterface {
            public int $calls = 0;

            public function __construct(private array $dates)
            {
            }

            public function now(): \DateTimeImmutable
            {
                $value = $this->dates[$this->calls] ?? new \RuntimeException('clock-failure');
                ++$this->calls;
                if ($value instanceof \Throwable) {
                    throw $value;
                }
                return new \DateTimeImmutable($value);
            }
        };
    }

    private static function logger(
        LogRecordPolicy $policy,
        ClockInterface $clock,
        JsonLineFormatter|LogfmtLineFormatter $formatter,
        string $directory,
        int $limit = 65536,
        string $level = 'debug',
    ): StreamLogger {
        return new StreamLogger(
            $policy,
            self::processor(),
            $formatter,
            $clock,
            $level,
            $limit,
            'web',
            'web',
            'file',
            $directory,
            'app',
        );
    }

    private static function processor(): ContextBagProcessor
    {
        return new ContextBagProcessor(
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
        );
    }

    /** @param array<string,mixed>|null $context
     * @return array<string,mixed>
     */
    private static function record(?array $context): array
    {
        return [
            'ts_utc' => null,
            'level' => 'info',
            'message' => 'kernel.boot',
            'service_name' => 'web',
            'app_target' => 'web',
            'correlation_id' => null,
            'uow_id' => null,
            'uow_type' => null,
            'context' => $context,
        ];
    }

    /** @return array<string,mixed> */
    private static function contextForLength(
        LogRecordPolicy $policy,
        JsonLineFormatter|LogfmtLineFormatter $formatter,
        int $expected,
    ): array {
        $limits = [
            'artifact_paths' => [12, 512],
            'directive_names' => [20, 160],
            'source_kinds' => [20, 160],
            'source_layers' => [20, 160],
            'source_types' => [20, 160],
        ];
        $context = [];
        foreach ($limits as $field => [$count]) {
            for ($i = 0; $i < $count; ++$i) {
                $context[$field][] = \sprintf('item%02d', $i);
            }
        }
        $remaining = $expected - \strlen($formatter->format(self::record($policy->admitContext($context))));
        self::assertGreaterThanOrEqual(0, $remaining);
        foreach ($limits as $field => [$count, $maximum]) {
            for ($i = 0; $i < $count && $remaining > 0; ++$i) {
                $added = \min($remaining, $maximum - \strlen($context[$field][$i]));
                $context[$field][$i] .= \str_repeat('a', $added);
                $remaining -= $added;
            }
        }
        self::assertSame(0, $remaining);
        self::assertSame($expected, \strlen($formatter->format(self::record($policy->admitContext($context)))));
        return $context;
    }

    private static function temporaryPath(): string
    {
        return \sys_get_temp_dir() . '/coretsia-stream-logger-' . \bin2hex(\random_bytes(10));
    }

    private static function temporaryDirectory(): string
    {
        $directory = self::temporaryPath();
        self::assertTrue(\mkdir($directory, 0700));
        return $directory;
    }

    private static function clean(string $directory): void
    {
        foreach (\glob($directory . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($directory);
    }
}
