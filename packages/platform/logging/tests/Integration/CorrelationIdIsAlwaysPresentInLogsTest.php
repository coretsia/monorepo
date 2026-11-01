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

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Observability\CorrelationIdProvider;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Processor\ContextBagProcessor;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class CorrelationIdIsAlwaysPresentInLogsTest extends TestCase
{
    private const string FIRST_CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string NEXT_CORRELATION = '01BX5ZZKBKACTAV9WEVGEMMVRZ';

    public function testBeforeDuringAfterAndNextUowUseOnlyTheCurrentContextStore(): void
    {
        $directory = self::directory();
        try {
            $store = new ContextStore();
            $redactor = self::redactor();
            $logger = self::logger($store, new CorrelationIdProvider($store), $redactor, $directory);
            $logger->info('kernel.boot');

            $store->set(ContextKeys::CORRELATION_ID, self::FIRST_CORRELATION);
            $store->set(ContextKeys::UOW_ID, 'request-001');
            $store->set(ContextKeys::UOW_TYPE, 'http');
            $store->set(ContextKeys::PATH_TEMPLATE, '/users/{user_id}');
            $logger->info('request.started', [
                'correlation_id' => self::NEXT_CORRELATION,
                'uow_id' => 'caller-999',
                'uow_type' => 'cli',
            ]);

            $store->reset();
            $logger->info('request.finished');
            $logger->info('kernel.after_reset', [
                'correlation_id' => self::FIRST_CORRELATION,
                'uow_id' => 'request-001',
                'uow_type' => 'http',
            ]);

            $store->set(ContextKeys::CORRELATION_ID, self::NEXT_CORRELATION);
            $store->set(ContextKeys::UOW_ID, 'job-002');
            $store->set(ContextKeys::UOW_TYPE, 'queue');
            $logger->info('queue.started');
            $records = self::records($directory);
            self::assertCount(5, $records);
            self::assertSame(5, $redactor->calls);

            foreach ($records as $record) {
                foreach (['correlation_id', 'uow_id', 'uow_type'] as $key) {
                    self::assertArrayHasKey($key, $record);
                }
                self::assertSame('web', $record['service_name']);
                self::assertSame('web', $record['app_target']);
            }
            foreach ([0, 2, 3] as $index) {
                self::assertNull($records[$index]['correlation_id']);
                self::assertNull($records[$index]['uow_id']);
                self::assertNull($records[$index]['uow_type']);
            }
            self::assertNull($records[0]['context']);
            self::assertSame(self::FIRST_CORRELATION, $records[1]['correlation_id']);
            self::assertSame('request-001', $records[1]['uow_id']);
            self::assertSame('http', $records[1]['uow_type']);
            self::assertSame('/users/{user_id}', $records[1]['context']['path_template']);
            self::assertSame(self::NEXT_CORRELATION, $records[1]['context']['correlation_id']);
            self::assertSame('caller-999', $records[1]['context']['uow_id']);
            self::assertSame('cli', $records[1]['context']['uow_type']);
            self::assertNull($records[2]['context']);
            self::assertSame(self::FIRST_CORRELATION, $records[3]['context']['correlation_id']);
            self::assertSame('request-001', $records[3]['context']['uow_id']);
            self::assertSame('http', $records[3]['context']['uow_type']);
            self::assertArrayNotHasKey('path_template', $records[3]['context']);
            self::assertSame(self::NEXT_CORRELATION, $records[4]['correlation_id']);
            self::assertSame('job-002', $records[4]['uow_id']);
            self::assertSame('queue', $records[4]['uow_type']);
            self::assertNull($records[4]['context']);
            self::assertStringNotContainsString('request-001', \json_encode($records[4], \JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString(
                self::FIRST_CORRELATION,
                \json_encode($records[4], \JSON_THROW_ON_ERROR),
            );
        } finally {
            self::clean($directory);
        }
    }

    public function testCanonicalIdentifiersDoNotDependOnTheStaticEventIdentifier(): void
    {
        $directory = self::directory();
        try {
            $store = new ContextStore();
            $logger = self::logger($store, new CorrelationIdProvider($store), self::redactor(), $directory);
            $store->set(ContextKeys::CORRELATION_ID, self::FIRST_CORRELATION);
            $store->set(ContextKeys::UOW_ID, 'request-001');
            $store->set(ContextKeys::UOW_TYPE, 'http');

            foreach (['kernel.boot', 'request.started', 'queue.started'] as $event) {
                $logger->info($event, ['operation' => 'boot']);
            }

            $store->reset();
            $logger->info('kernel.boot', ['correlation_id' => self::FIRST_CORRELATION]);
            $records = self::records($directory);
            self::assertCount(4, $records);

            foreach (['kernel.boot', 'request.started', 'queue.started'] as $index => $event) {
                self::assertSame($event, $records[$index]['message']);
                self::assertSame(self::FIRST_CORRELATION, $records[$index]['correlation_id']);
                self::assertSame('request-001', $records[$index]['uow_id']);
                self::assertSame('http', $records[$index]['uow_type']);
            }

            self::assertNull($records[3]['correlation_id']);
            self::assertNull($records[3]['uow_id']);
            self::assertNull($records[3]['uow_type']);
            self::assertSame(self::FIRST_CORRELATION, $records[3]['context']['correlation_id']);
        } finally {
            self::clean($directory);
        }
    }

    public function testInvalidAndThrowingCanonicalReadsRemainNullableWithoutStoppingLogging(): void
    {
        $directory = self::directory();
        try {
            $store = new ContextStore();
            $store->set(ContextKeys::CORRELATION_ID, 'invalid-correlation');
            $store->set(ContextKeys::UOW_ID, '/invalid/id');
            $store->set(ContextKeys::UOW_TYPE, 'untrusted');
            $store->set(ContextKeys::PATH_TEMPLATE, '/users/1234');
            $logger = self::logger($store, new CorrelationIdProvider($store), self::redactor(), $directory);
            $logger->info('kernel.invalid_context');

            $throwing = new class() implements ContextAccessorInterface {
                public function has(string $key): bool
                {
                    throw new \RuntimeException('private-context-error');
                }

                public function get(string $key): mixed
                {
                    throw new \RuntimeException('private-context-error');
                }
            };
            self::logger(
                $throwing,
                new class() implements CorrelationIdProviderInterface {
                    public function correlationId(): ?string
                    {
                        throw new \RuntimeException('private-correlation-error');
                    }
                },
                self::redactor(),
                $directory,
            )->warning('kernel.throwing_context');

            $records = self::records($directory);
            self::assertCount(2, $records);
            foreach ($records as $record) {
                self::assertArrayHasKey('correlation_id', $record);
                self::assertArrayHasKey('uow_id', $record);
                self::assertArrayHasKey('uow_type', $record);
                self::assertNull($record['correlation_id']);
                self::assertNull($record['uow_id']);
                self::assertNull($record['uow_type']);
                self::assertNull($record['context']);
            }
            self::assertStringNotContainsString('private-context-error', \file_get_contents(self::file($directory)));
            self::assertStringNotContainsString('invalid-correlation', \file_get_contents(self::file($directory)));
        } finally {
            self::clean($directory);
        }
    }

    public function testThrowingContextGetCannotSuppressTheCurrentLogRecord(): void
    {
        $directory = self::directory();
        try {
            $context = new class() implements ContextAccessorInterface {
                public int $reads = 0;

                public function has(string $key): bool
                {
                    return true;
                }

                public function get(string $key): mixed
                {
                    ++$this->reads;
                    throw new \RuntimeException('private-get-error');
                }
            };
            $logger = self::logger($context, new CorrelationIdProvider($context), self::redactor(), $directory);
            $logger->info('kernel.context_unavailable', ['operation' => 'boot']);
            $records = self::records($directory);

            self::assertCount(1, $records);
            self::assertSame('kernel.context_unavailable', $records[0]['message']);
            self::assertNull($records[0]['correlation_id']);
            self::assertNull($records[0]['uow_id']);
            self::assertNull($records[0]['uow_type']);
            self::assertSame(['operation' => 'boot'], $records[0]['context']);
            self::assertSame(4, $context->reads);
            self::assertStringNotContainsString(
                'private-get-error',
                (string) \file_get_contents(self::file($directory)),
            );
        } finally {
            self::clean($directory);
        }
    }

    public function testFoundationOwnedContextPortsFeedLoggingWithoutAnyKernelImplementation(): void
    {
        $directory = self::directory();
        try {
            $builder = new ContainerBuilder(['foundation' => []]);
            $builder->register(new FoundationServiceProvider());
            $container = $builder->build();
            $store = $container->get(ContextStore::class);
            $accessor = $container->get(ContextAccessorInterface::class);
            $correlations = $container->get(CorrelationIdProviderInterface::class);
            self::assertSame($store, $accessor);
            self::assertInstanceOf(CorrelationIdProvider::class, $correlations);

            $logger = self::logger($accessor, $correlations, self::redactor(), $directory);
            $store->set(ContextKeys::CORRELATION_ID, self::FIRST_CORRELATION);
            $store->set(ContextKeys::UOW_ID, 'first-operation');
            $store->set(ContextKeys::UOW_TYPE, 'cli');
            $logger->info('cli.started');
            $store->reset();
            $logger->info('cli.completed');

            $records = self::records($directory);
            self::assertCount(2, $records);
            self::assertSame(self::FIRST_CORRELATION, $records[0]['correlation_id']);
            self::assertSame('first-operation', $records[0]['uow_id']);
            self::assertSame('cli', $records[0]['uow_type']);
            self::assertNull($records[1]['correlation_id']);
            self::assertNull($records[1]['uow_id']);
            self::assertNull($records[1]['uow_type']);
        } finally {
            self::clean($directory);
        }
    }

    public function testContextStoreResetDoesNotNeedLoggerReconstructionOrOwnedResetHooks(): void
    {
        $store = new ContextStore();
        $processor = new ContextBagProcessor($store, new CorrelationIdProvider($store));
        self::assertSame(
            ['correlation_id' => null, 'uow_id' => null, 'uow_type' => null, 'context' => null],
            $processor->process(null)
        );
        $store->set(ContextKeys::CORRELATION_ID, self::FIRST_CORRELATION);
        $store->set(ContextKeys::UOW_TYPE, 'scheduler');
        self::assertSame(self::FIRST_CORRELATION, $processor->process(null)['correlation_id']);
        self::assertSame('scheduler', $processor->process(null)['uow_type']);
        $store->reset();
        self::assertSame(
            ['correlation_id' => null, 'uow_id' => null, 'uow_type' => null, 'context' => null],
            $processor->process(null)
        );
        self::assertFalse(new \ReflectionClass(ContextBagProcessor::class)->hasMethod('reset'));
        self::assertFalse(new \ReflectionClass(StreamLogger::class)->hasMethod('reset'));
    }

    private static function logger(
        ContextAccessorInterface $context,
        CorrelationIdProviderInterface $correlations,
        SensitiveDataRedactorInterface $redactor,
        string $directory,
    ): StreamLogger {
        return new StreamLogger(
            new LogRecordPolicy(
                $redactor,
                new class() implements SensitiveKeyClassifierInterface {
                    public function classify(string $key): ?RedactionKind
                    {
                        return null;
                    }
                },
            ),
            new ContextBagProcessor($context, $correlations),
            new JsonLineFormatter(),
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
            'file',
            $directory,
            'app',
        );
    }

    private static function redactor(): SensitiveDataRedactorInterface
    {
        return new class() implements SensitiveDataRedactorInterface {
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
    }

    private static function directory(): string
    {
        $directory = \rtrim(\sys_get_temp_dir(), '/\\') . \DIRECTORY_SEPARATOR
            . 'coretsia-correlation-integration-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($directory, 0700));
        return $directory;
    }

    private static function file(string $directory): string
    {
        return $directory . \DIRECTORY_SEPARATOR . 'web-app-2026-10-31.jsonl';
    }

    /** @return list<array<string,mixed>> */
    private static function records(string $directory): array
    {
        $contents = \file_get_contents(self::file($directory));
        self::assertIsString($contents);
        self::assertTrue(\str_ends_with($contents, "\n"));
        $lines = \explode("\n", \substr($contents, 0, -1));
        return \array_map(
            static fn (string $line): array => \json_decode($line, true, 512, \JSON_THROW_ON_ERROR),
            $lines
        );
    }

    private static function clean(string $directory): void
    {
        foreach (\glob($directory . '/*') ?: [] as $file) {
            \unlink($file);
        }
        \rmdir($directory);
    }
}
