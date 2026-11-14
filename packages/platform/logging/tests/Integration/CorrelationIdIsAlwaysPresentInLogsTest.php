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
use Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter;
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
            $processor->process(null),
        );
        $store->set(ContextKeys::CORRELATION_ID, self::FIRST_CORRELATION);
        $store->set(ContextKeys::UOW_TYPE, 'scheduler');
        self::assertSame(self::FIRST_CORRELATION, $processor->process(null)['correlation_id']);
        self::assertSame('scheduler', $processor->process(null)['uow_type']);
        $store->reset();
        self::assertSame(
            ['correlation_id' => null, 'uow_id' => null, 'uow_type' => null, 'context' => null],
            $processor->process(null),
        );
        self::assertFalse(new \ReflectionClass(ContextBagProcessor::class)->hasMethod('reset'));
        self::assertFalse(new \ReflectionClass(StreamLogger::class)->hasMethod('reset'));
    }

    public function testHttpCompletionRecordsPreserveBoundedValuesAcrossContextReset(): void
    {
        $directory = self::directory();
        try {
            $store = new ContextStore();
            $redactor = self::redactor();
            $logger = self::logger($store, new CorrelationIdProvider($store), $redactor, $directory);
            $context = [
                'method' => 'GET',
                'status' => 200,
                'outcome' => 'success',
                'duration_ms' => 7,
                'route_template' => 'unknown',
                'correlation_id' => self::NEXT_CORRELATION,
            ];
            $logger->info('http.request.completed', $context);

            $store->set(ContextKeys::CORRELATION_ID, self::FIRST_CORRELATION);
            $store->set(ContextKeys::UOW_ID, 'request-001');
            $store->set(ContextKeys::UOW_TYPE, 'http');
            $store->set(ContextKeys::PATH_TEMPLATE, '/users/{user_id}');
            $logger->info('http.request.completed', [
                ...$context,
                'status' => 503,
                'outcome' => 'handled_error',
                'duration_ms' => 4_294_967_295,
            ]);

            $store->reset();
            $logger->info('http.request.completed', [
                ...$context,
                'status' => 'unknown',
                'outcome' => 'fatal_error',
            ]);
            $store->set(ContextKeys::CORRELATION_ID, self::NEXT_CORRELATION);
            $store->set(ContextKeys::UOW_ID, 'request-002');
            $store->set(ContextKeys::UOW_TYPE, 'http');
            $logger->info('http.request.completed', [
                ...$context,
                'status' => 399,
            ]);
            $logger->info('worker.iteration', ['status' => 'ready', 'outcome' => 'success']);

            $records = self::records($directory);
            self::assertCount(5, $records);
            self::assertSame(5, $redactor->calls);
            foreach ($records as $record) {
                self::assertSame('web', $record['service_name']);
                self::assertSame('web', $record['app_target']);
                self::assertTrue(LogRecordPolicy::isValidCanonicalRecord($record));
            }
            foreach ([0, 2] as $index) {
                self::assertNull($records[$index]['correlation_id']);
                self::assertNull($records[$index]['uow_id']);
                self::assertNull($records[$index]['uow_type']);
            }
            self::assertSame(self::FIRST_CORRELATION, $records[1]['correlation_id']);
            self::assertSame('request-001', $records[1]['uow_id']);
            self::assertSame('http', $records[1]['uow_type']);
            self::assertSame(self::NEXT_CORRELATION, $records[3]['correlation_id']);
            self::assertSame('request-002', $records[3]['uow_id']);
            self::assertSame('http', $records[3]['uow_type']);

            foreach ([0, 1, 2, 3] as $index) {
                self::assertSame('http.request.completed', $records[$index]['message']);
                self::assertSame('GET', $records[$index]['context']['method']);
                self::assertSame('unknown', $records[$index]['context']['route_template']);
                self::assertSame(self::NEXT_CORRELATION, $records[$index]['context']['correlation_id']);
            }
            self::assertSame(200, $records[0]['context']['status']);
            self::assertSame('success', $records[0]['context']['outcome']);
            self::assertSame(7, $records[0]['context']['duration_ms']);
            self::assertSame(503, $records[1]['context']['status']);
            self::assertSame('handled_error', $records[1]['context']['outcome']);
            self::assertSame(4_294_967_295, $records[1]['context']['duration_ms']);
            self::assertSame('/users/{user_id}', $records[1]['context']['path_template']);
            self::assertSame('unknown', $records[2]['context']['status']);
            self::assertSame('fatal_error', $records[2]['context']['outcome']);
            self::assertArrayNotHasKey('path_template', $records[2]['context']);
            self::assertSame(399, $records[3]['context']['status']);
            self::assertArrayNotHasKey('path_template', $records[3]['context']);
            self::assertSame('ready', $records[4]['context']['status']);
            self::assertSame('worker.iteration', $records[4]['message']);
        } finally {
            self::clean($directory);
        }
    }

    public function testCanonicalHttpCompletionContextHasEquivalentJsonlAndLogfmtSemantics(): void
    {
        $directory = self::directory();
        try {
            $store = new ContextStore();
            $store->set(ContextKeys::CORRELATION_ID, self::FIRST_CORRELATION);
            $store->set(ContextKeys::UOW_ID, 'request-001');
            $store->set(ContextKeys::UOW_TYPE, 'http');
            $store->set(ContextKeys::PATH_TEMPLATE, '/users/{user_id}');
            $correlations = new CorrelationIdProvider($store);
            $context = [
                'method' => 'PATCH',
                'status' => 418,
                'outcome' => 'handled_error',
                'duration_ms' => 123,
                'route_template' => 'unknown',
                'correlation_id' => self::NEXT_CORRELATION,
            ];

            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                self::logger($store, $correlations, self::redactor(), $directory, $formatter)->info(
                    'http.request.completed',
                    $context,
                );
            }

            $jsonl = self::records($directory);
            self::assertCount(1, $jsonl);
            $expected = $jsonl[0]['context'];
            self::assertSame(418, $expected['status']);
            self::assertSame(123, $expected['duration_ms']);
            self::assertSame('PATCH', $expected['method']);
            self::assertSame('unknown', $expected['route_template']);
            self::assertSame('/users/{user_id}', $expected['path_template']);
            self::assertSame(self::NEXT_CORRELATION, $expected['correlation_id']);

            $logfmt = \file_get_contents(self::file($directory, 'logfmt'));
            self::assertIsString($logfmt);
            self::assertSame(1, \substr_count($logfmt, "\n"));
            self::assertSame(1, \substr_count($logfmt, ' context='));
            self::assertStringContainsString('message="http.request.completed"', $logfmt);
            self::assertStringContainsString('correlation_id="' . self::FIRST_CORRELATION . '"', $logfmt);
            self::assertStringContainsString('uow_id="request-001"', $logfmt);
            self::assertSame($expected, self::logfmtContext($logfmt));
            self::assertStringNotContainsString('password', $logfmt);
        } finally {
            self::clean($directory);
        }
    }

    public function testGenericLoggingSinkDoesNotDeriveHttpCompletionOutcomes(): void
    {
        $directory = self::directory();
        try {
            $store = new ContextStore();
            $logger = self::logger($store, new CorrelationIdProvider($store), self::redactor(), $directory);
            $logger->info('logging.operational_status', [
                'method' => 'GET',
                'status' => 500,
                'outcome' => 'success',
                'route_template' => 'unknown',
            ]);
            $logger->info('logging.operational_status', [
                'method' => 'POST',
                'status' => 200,
                'outcome' => 'fatal_error',
                'route_template' => 'unknown',
            ]);

            $logger->info('http.request.completed', [
                'method' => 'GET',
                'status' => 500,
                'outcome' => 'success',
                'duration_ms' => 4,
                'route_template' => 'unknown',
            ]);

            $logger->info('http.request.completed', [
                'method' => 'POST',
                'status' => 200,
                'outcome' => 'fatal_error',
                'duration_ms' => 4,
                'route_template' => 'unknown',
            ]);

            $logger->info('http.request.completed', [
                'method' => 'OTHER',
                'status' => 'ready',
                'outcome' => 'success',
                'duration_ms' => 4,
                'route_template' => 'unknown',
            ]);

            $records = self::records($directory);
            self::assertCount(5, $records);

            self::assertSame(500, $records[0]['context']['status']);
            self::assertSame('success', $records[0]['context']['outcome']);
            self::assertSame(200, $records[1]['context']['status']);
            self::assertSame('fatal_error', $records[1]['context']['outcome']);

            self::assertSame('http.request.completed', $records[2]['message']);
            self::assertSame(500, $records[2]['context']['status']);
            self::assertSame('success', $records[2]['context']['outcome']);

            self::assertSame('http.request.completed', $records[3]['message']);
            self::assertSame(200, $records[3]['context']['status']);
            self::assertSame('fatal_error', $records[3]['context']['outcome']);

            self::assertSame('http.request.completed', $records[4]['message']);
            self::assertSame('ready', $records[4]['context']['status']);
            self::assertSame('success', $records[4]['context']['outcome']);
        } finally {
            self::clean($directory);
        }
    }

    public function testCallerContextCardinalityAndEnrichmentHaveDistinctFailurePaths(): void
    {
        $directory = self::directory();
        try {
            $store = new ContextStore();
            $redactor = self::redactor();
            $logger = self::logger($store, new CorrelationIdProvider($store), $redactor, $directory);
            $context = self::saturatedContext();
            self::assertCount(48, $context);

            $logger->info('logging.context_full', $context);
            $logger->info('logging.caller_oversized', [...$context, 'unknown' => 'ignored']);
            $records = self::records($directory);
            self::assertCount(2, $records);
            self::assertCount(48, $records[0]['context']);
            self::assertNull($records[1]['context']);
            self::assertSame(2, $redactor->calls);
            $before = \file_get_contents(self::file($directory));

            $store->set(ContextKeys::PATH_TEMPLATE, '/users/{user_id}');
            $logger->info('logging.enrichment_oversized', $context);
            self::assertSame(2, $redactor->calls);
            self::assertSame($before, \file_get_contents(self::file($directory)));

            $logger->info('logging.after_oversized', [
                'method' => 'GET',
                'status' => 200,
                'route_template' => 'unknown',
            ]);
            $records = self::records($directory);
            self::assertCount(3, $records);
            self::assertSame(3, $redactor->calls);
            self::assertSame('/users/{user_id}', $records[2]['context']['path_template']);
            self::assertSame('unknown', $records[2]['context']['route_template']);
            self::assertStringNotContainsString(
                'logging.enrichment_oversized',
                (string) \file_get_contents(self::file($directory)),
            );
        } finally {
            self::clean($directory);
        }
    }

    public function testRejectedCanonicalRecordsCannotReachTheFormatter(): void
    {
        $method = new \ReflectionMethod(StreamLogger::class, 'log');
        $path = $method->getFileName();
        self::assertIsString($path);
        $lines = \file($path);
        self::assertIsArray($lines);
        $body = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );

        $redaction = \strpos($body, '$redacted = $this->policy->redactRecord($record);');
        $rejection = \strpos($body, 'if ($redacted === null) {');
        $formatting = \strpos($body, '$serialized = $this->formatter->format($redacted);');
        self::assertNotFalse($redaction);
        self::assertNotFalse($rejection);
        self::assertNotFalse($formatting);
        self::assertTrue($redaction < $rejection && $rejection < $formatting);
        self::assertSame(1, \substr_count($body, '$this->formatter->format('));
    }

    public function testHttpRejectedFieldsNeverReappearThroughRedactionOrFallback(): void
    {
        $directory = self::directory();
        try {
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
                    $value['context']['method'] = '/private/method';
                    $value['context']['route_template'] = '/private/route';
                    return $value;
                }
            };
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                $store = new ContextStore();
                $logger = self::logger($store, new CorrelationIdProvider($store), $redactor, $directory, $formatter);
                $logger->info('http.request.completed', [
                    'method' => 'get',
                    'status' => 200,
                    'outcome' => 'success',
                    'duration_ms' => 4,
                    'route_template' => '/unsafe/route',
                ]);
                $extension = $formatter instanceof JsonLineFormatter ? 'jsonl' : 'logfmt';
                $bytes = \file_get_contents(self::file($directory, $extension));
                self::assertIsString($bytes);
                self::assertSame(1, \substr_count($bytes, "\n"));
                self::assertStringNotContainsString('/unsafe/route', $bytes);
                self::assertStringNotContainsString('/private/method', $bytes);
                self::assertStringNotContainsString('/private/route', $bytes);
                self::assertStringNotContainsString('"method"', $bytes);
                self::assertStringNotContainsString('"route_template"', $bytes);
            }
            self::assertSame(2, $redactor->calls);
        } finally {
            self::clean($directory);
        }
    }

    public function testMalformedRedactedHttpRecordAndRecordByteLimitFailClosed(): void
    {
        $directory = self::directory();
        try {
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
                    $value['context'] = 'private-invalid-container';
                    return $value;
                }
            };
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                $store = new ContextStore();
                self::logger($store, new CorrelationIdProvider($store), $redactor, $directory, $formatter)->info(
                    'http.request.completed',
                    ['method' => 'GET', 'status' => 200, 'outcome' => 'success', 'route_template' => 'unknown'],
                );
            }
            self::assertSame(2, $redactor->calls);
            self::assertSame([], \glob($directory . '/*'));

            $store = new ContextStore();
            $sizeRedactor = self::redactor();
            $logger = self::logger(
                $store,
                new CorrelationIdProvider($store),
                $sizeRedactor,
                $directory,
                new JsonLineFormatter(),
                1024,
            );
            $logger->info('logging.large_record', self::saturatedContext());
            self::assertSame(1, $sizeRedactor->calls);
            self::assertSame([], \glob($directory . '/*'));
        } finally {
            self::clean($directory);
        }
    }

    /** @return array<string, mixed> */
    private static function saturatedContext(): array
    {
        $context = [
            'operation' => 'boot',
            'outcome' => 'success',
            'event' => 'ready',
            'reason' => 'available',
            'driver' => 'process',
            'control_transport' => 'stdio',
            'presetName' => 'micro',
            'preset' => 'micro',
            'artifact_paths' => ['var/cache/app.php'],
            'artifact_reasons' => ['app.php:ready'],
            'bucket_names' => ['runtime'],
            'directive_names' => ['modules'],
            'moduleIds' => ['core.kernel'],
            'source_kinds' => ['source'],
            'source_layers' => ['core'],
            'source_paths' => ['src/app.php'],
            'source_types' => ['config'],
            'method' => 'GET',
            'status' => 200,
            'route_template' => 'unknown',
            'duration_ms' => 12,
            'exit_code' => 0,
            'pid' => 1,
            'artifact_basename' => 'app.php',
            'artifact_path' => 'var/cache/app.php',
            'endpoint_hash' => \str_repeat('a', 64),
        ];
        foreach (
            [
                'artifact_count',
                'bucket_count',
                'byte_count',
                'dirty_artifact_count',
                'env_overlay_mapping_count',
                'env_overlay_metadata_count',
                'existing_artifact_count',
                'expected_artifact_count',
                'groups_count',
                'invalid_artifact_count',
                'missing_artifact_count',
                'missing_source_candidate_count',
                'ready_worker_count',
                'ruleset_count',
                'services_count',
                'source_candidate_count',
                'source_entry_count',
                'unvalidated_root_count',
                'validated_root_count',
                'validation_subject_unvalidated_count',
                'validation_subject_validated_count',
                'worker_count',
            ] as $field
        ) {
            $context[$field] = 1;
        }
        return $context;
    }

    /** @return array<string, mixed>|null */
    private static function logfmtContext(string $line): ?array
    {
        $offset = \strrpos($line, ' context=');
        self::assertNotFalse($offset);
        $quoted = \substr($line, $offset + \strlen(' context='), -1);
        if ($quoted === 'null') {
            return null;
        }
        $json = \json_decode($quoted, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsString($json);
        return \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
    }

    private static function logger(
        ContextAccessorInterface $context,
        CorrelationIdProviderInterface $correlations,
        SensitiveDataRedactorInterface $redactor,
        string $directory,
        JsonLineFormatter|LogfmtLineFormatter $formatter = new JsonLineFormatter(),
        int $maxRecordBytes = 65536,
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
            $formatter,
            new class() implements ClockInterface {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-10-31T12:00:00Z');
                }
            },
            'debug',
            $maxRecordBytes,
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

    private static function file(string $directory, string $extension = 'jsonl'): string
    {
        return $directory . \DIRECTORY_SEPARATOR . 'web-app-2026-10-31.' . $extension;
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
            $lines,
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
