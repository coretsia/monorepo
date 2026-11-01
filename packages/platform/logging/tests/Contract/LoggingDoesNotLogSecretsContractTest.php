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
use Coretsia\Platform\Logging\Provider\LoggingServiceFactory;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class LoggingDoesNotLogSecretsContractTest extends TestCase
{
    public function testRawTransportCredentialsPiiAndUntrustedPayloadNeverReachEitherFileFormat(): void
    {
        $secret = 'SYNTHETIC_PRIVATE_CREDENTIAL_42';
        $context = [
            'operation' => 'boot',
            'authorization' => 'Bearer ' . $secret,
            'cookie' => 'session=' . $secret,
            'session_id' => $secret,
            'access_token' => $secret,
            'headers' => ['Authorization' => $secret],
            'request_body' => ['secret' => $secret],
            'body' => ['secret' => $secret],
            'query' => 'credential=' . $secret,
            'payload' => $secret,
            'raw_sql' => 'SELECT ' . $secret,
            'raw_env' => ['DB_PASSWORD' => $secret],
            'raw_path' => '/users/' . $secret,
            'host' => $secret . '.example.org',
            'user_agent' => 'agent/' . $secret,
            'client_ip' => '192.0.2.12',
            'customer_notes' => $secret,
            'email_address' => 'private@example.org',
            'reason' => ['unsafe' => new \stdClass()],
            'duration_ms' => 3.14,
        ];
        $directory = self::directory();
        try {
            foreach (['jsonl', 'logfmt'] as $format) {
                $logger = self::logger('file', $directory, $format);
                $logger->info('kernel.boot', $context);
                $path = $directory . '/web-app-2026-10-31.' . $format;
                self::assertFileExists($path);
                $frame = (string) \file_get_contents($path);
                self::assertSame(1, \substr_count($frame, "\n"));
                self::assertStringContainsString('kernel.boot', $frame);
                self::assertStringContainsString('boot', $frame);

                foreach (\array_keys($context) as $field) {
                    if ($field !== 'operation') {
                        self::assertStringNotContainsString($field, $frame, $format . '/' . $field);
                    }
                }

                foreach (
                    [
                        $secret,
                        'private@example.org',
                        '192.0.2.12',
                        'raw_sql',
                        'authorization',
                        'session_id',
                        'access_token',
                        'request_body',
                        'client_ip',
                        'raw_path',
                        'user_agent',
                        'headers',
                        'customer_notes',
                    ] as $forbidden
                ) {
                    self::assertStringNotContainsString($forbidden, $frame, $format . '/' . $forbidden);
                }
            }
        } finally {
            self::cleanup($directory);
        }
    }

    public function testSharedCanonicalAliasesAndStructuralComponentsRejectWholeKeyPathsField(): void
    {
        $classifier = new SensitiveKeyClassifier();
        foreach (
            [
                'password',
                'client_secret',
                'access_token',
                'email_address',
                'authorization',
                'cookie',
                'session_id',
            ] as $alias
        ) {
            self::assertNotNull($classifier->classify($alias));
        }
        $policy = self::policy();
        foreach (
            [
                'db.password',
                'db.client_secret[3]',
                'user.<password>',
                'db.access_token',
                'user.email_address',
                'db.@authorization',
            ] as $path
        ) {
            self::assertSame(
                ['operation' => 'boot'],
                $policy->admitContext(['key_paths' => ['logging.driver', $path], 'operation' => 'boot']),
                $path,
            );
        }
        self::assertSame(['key_paths' => ['logging.driver', 'worker.output.format']], $policy->admitContext([
            'key_paths' => ['worker.output.format', 'logging.driver'],
        ]));
        foreach (['db..secret', '../../credential', 'user/<password>', 'db.[[]'] as $path) {
            self::assertNull($policy->admitContext(['key_paths' => [$path]]));
        }
    }

    public function testUnclassifiedKeysRemainConstrainedByTheLoggingOwnedSinkSchema(): void
    {
        $policy = new LogRecordPolicy(
            self::redactor(),
            new class() implements SensitiveKeyClassifierInterface {
                public function classify(string $key): ?RedactionKind
                {
                    return null;
                }
            },
        );
        self::assertSame(['operation' => 'boot'], $policy->admitContext([
            'operation' => 'boot',
            'unclassified_customer_secret' => 'SYNTHETIC_PRIVATE_CREDENTIAL_42',
            'raw_path' => '/internal/private',
            'source_paths' => ['/etc/passwd'],
            'artifact_path' => '../../private',
            'status' => new \stdClass(),
            'worker_count' => 2.5,
        ]));
    }

    public function testInitialClassifierExceptionNeverProducesFileOrFallbackForEitherFormat(): void
    {
        $directory = self::directory();
        try {
            foreach (['jsonl', 'logfmt'] as $format) {
                $classifier = new class() implements SensitiveKeyClassifierInterface {
                    public function classify(string $key): ?RedactionKind
                    {
                        throw new \RuntimeException('SYNTHETIC_PRIVATE_CREDENTIAL_42');
                    }
                };
                $logger = self::logger('file', $directory, $format, new LogRecordPolicy(self::redactor(), $classifier));
                $logger->info('kernel.boot', ['key_paths' => ['db.table']]);
                self::assertSame([], \glob($directory . '/*'));
            }
        } finally {
            self::cleanup($directory);
        }
    }

    public function testPostRedactionReintroductionOrRedactorFailureCannotEmitFallback(): void
    {
        foreach (['jsonl', 'logfmt'] as $format) {
            $directory = self::directory();
            try {
                foreach (['inject', 'throw'] as $case) {
                    $redactor = new class($case) implements SensitiveDataRedactorInterface {
                        public int $calls = 0;

                        public function __construct(private string $case)
                        {
                        }

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
                            if ($this->case === 'throw') {
                                throw new \RuntimeException('SYNTHETIC_PRIVATE_CREDENTIAL_42');
                            }
                            $value['context']['key_paths'] = ['db.password[0]'];
                            return $value;
                        }
                    };
                    $logger = self::logger(
                        'file',
                        $directory,
                        $format,
                        new LogRecordPolicy($redactor, new SensitiveKeyClassifier()),
                    );
                    $logger->info('kernel.boot', ['key_paths' => ['db.table'], 'operation' => 'boot']);
                    self::assertSame(1, $redactor->calls);
                    self::assertSame([], \glob($directory . '/*'));
                }
            } finally {
                self::cleanup($directory);
            }
        }
    }

    public function testUnsafeMessagesAndStringableObjectsNeverExposeRawContent(): void
    {
        $directory = self::directory();
        $evaluations = 0;
        $message = new class($evaluations) implements \Stringable {
            public function __construct(private int &$evaluations)
            {
            }

            public function __toString(): string
            {
                ++$this->evaluations;
                throw new \RuntimeException('PRIVATE_MESSAGE');
            }
        };
        try {
            foreach (['jsonl', 'logfmt'] as $format) {
                $logger = self::logger('file', $directory, $format);
                $logger->info("Bearer SYNTHETIC_PRIVATE_CREDENTIAL_42\n", ['operation' => 'boot']);
                $logger->warning($message, ['operation' => 'boot']);
                $data = (string) \file_get_contents($directory . '/web-app-2026-10-31.' . $format);
                self::assertSame(2, \substr_count($data, "\n"));
                self::assertSame(2, \substr_count($data, 'logging.message_rejected'));
                self::assertStringNotContainsString('SYNTHETIC_PRIVATE_CREDENTIAL_42', $data);
                self::assertStringNotContainsString('PRIVATE_MESSAGE', $data);
                self::assertSame(0, $evaluations);
            }
        } finally {
            self::cleanup($directory);
        }
    }

    public function testStreamAndFileUseTheSameAdmissionAndRedactionRulesForBothFormats(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $script = <<<'PHP_CODE'
require $argv[1];
require $argv[2];
$method = new \ReflectionMethod(\Coretsia\Platform\Logging\Tests\Contract\LoggingDoesNotLogSecretsContractTest::class, 'logger');
$logger = $method->invoke(null, 'stream', '', $argv[3]);
$logger->info('kernel.boot', ['operation' => 'boot', 'Authorization' => 'SYNTHETIC_PRIVATE_CREDENTIAL_42', 'raw_path' => '/internal/private', 'key_paths' => ['db.password']]);
PHP_CODE;
        foreach (['jsonl', 'logfmt'] as $format) {
            $process = \proc_open(
                [\PHP_BINARY, '-r', $script, $autoload, __FILE__, $format],
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
            self::assertStringContainsString('kernel.boot', $stderr);
            foreach (
                [
                    'SYNTHETIC_PRIVATE_CREDENTIAL_42',
                    'raw_path',
                    'key_paths',
                    'db.password',
                    'Authorization',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $stderr);
            }
            $directory = self::directory();
            try {
                self::logger('file', $directory, $format)->info('kernel.boot', [
                    'operation' => 'boot',
                    'Authorization' => 'SYNTHETIC_PRIVATE_CREDENTIAL_42',
                    'raw_path' => '/internal/private',
                    'key_paths' => ['db.password'],
                ]);
                self::assertSame($stderr, (string) \file_get_contents($directory . '/web-app-2026-10-31.' . $format));
            } finally {
                self::cleanup($directory);
            }
        }
    }

    public function testMandatoryRedactionPrecedesEmbeddedContextJsonInBothFormats(): void
    {
        $directory = self::directory();
        try {
            foreach (['jsonl', 'logfmt'] as $format) {
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
                        $value['context']['operation'] = 'worker:restart';
                        return $value;
                    }
                };
                $policy = new LogRecordPolicy($redactor, new SensitiveKeyClassifier());
                self::logger('file', $directory, $format, $policy)->info(
                    'kernel.boot',
                    ['operation' => 'config:compile'],
                );
                $frame = (string) \file_get_contents($directory . '/web-app-2026-10-31.' . $format);
                self::assertSame(1, $redactor->calls);
                self::assertStringContainsString('worker:restart', $frame);
                self::assertStringNotContainsString('config:compile', $frame);
                self::assertSame(1, \substr_count($frame, "\n"));
            }
        } finally {
            self::cleanup($directory);
        }
    }

    public function testFilesystemFailureNeverPrintsRawPathOrWarnings(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $script = <<<'PHP_CODE'
require $argv[1];
require $argv[2];
$method = new \ReflectionMethod(\Coretsia\Platform\Logging\Tests\Contract\LoggingDoesNotLogSecretsContractTest::class, 'logger');
$logger = $method->invoke(null, 'file', $argv[3], $argv[4]);
$logger->info('kernel.boot', ['operation' => 'boot']);
echo 'complete';
PHP_CODE;
        foreach (['jsonl', 'logfmt'] as $format) {
            $missing = \rtrim(\sys_get_temp_dir(), '/\\') . \DIRECTORY_SEPARATOR
                . 'coretsia-sensitive-path-' . \bin2hex(\random_bytes(8));
            $process = \proc_open(
                [\PHP_BINARY, '-d', 'display_errors=1', '-r', $script, $autoload, __FILE__, $missing, $format],
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
            self::assertSame('complete', $stdout);
            self::assertSame('', $stderr);
            self::assertDirectoryDoesNotExist($missing);
        }
    }

    public function testNoLoggingOwnedClassifierAliasesOrConfigurableRedactionEscapeHatch(): void
    {
        $policy = (string) \file_get_contents(new \ReflectionClass(LogRecordPolicy::class)->getFileName());
        $factory = (string) \file_get_contents(
            new \ReflectionClass(LoggingServiceFactory::class)->getFileName(),
        );
        self::assertStringNotContainsString('Coretsia\\Platform\\Redaction\\', $policy);
        self::assertStringNotContainsString('new SensitiveKeyClassifier(', $policy);
        self::assertStringNotContainsString('ALIASES', $policy);
        self::assertStringNotContainsString('logging.redaction', $factory);
        self::assertStringNotContainsString(
            'logging.redaction',
            (string) \file_get_contents(\dirname(__DIR__, 2) . '/config/rules.php'),
        );
        self::assertStringNotContainsString(
            'logging.redaction',
            (string) \file_get_contents(\dirname(__DIR__, 2) . '/config/logging.php'),
        );
    }

    private static function logger(
        string $driver,
        string $directory,
        string $format,
        ?LogRecordPolicy $policy = null,
    ): StreamLogger {
        return new StreamLogger(
            $policy ?? self::policy(),
            new ContextBagProcessor(
                new class() implements ContextAccessorInterface {
                    public function has(string $key): bool
                    {
                        return false;
                    }

                    public function get(string $key): mixed
                    {
                        throw new \LogicException('no-context');
                    }
                },
                new class() implements CorrelationIdProviderInterface {
                    public function correlationId(): ?string
                    {
                        return null;
                    }
                },
            ),
            $format === 'jsonl' ? new JsonLineFormatter() : new LogfmtLineFormatter(),
            new class() implements ClockInterface {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-10-31T12:00:00Z');
                }
            },
            'debug',
            65536,
            'web',
            'web',
            $driver,
            $driver === 'file' ? $directory : null,
            $driver === 'file' ? 'app' : null,
        );
    }

    private static function policy(): LogRecordPolicy
    {
        return new LogRecordPolicy(self::redactor(), new SensitiveKeyClassifier());
    }

    private static function redactor(): SensitiveDataRedactorInterface
    {
        return new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
    }

    private static function directory(): string
    {
        $path = \sys_get_temp_dir() . '/coretsia-secret-contract-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($path, 0700));
        return $path;
    }

    private static function cleanup(string $directory): void
    {
        foreach (\glob($directory . '/*') ?: [] as $path) {
            \unlink($path);
        }
        \rmdir($directory);
    }
}
